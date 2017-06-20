<?php
namespace Action\Github;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Github\Organization;
use function GuzzleHttp\json_decode;
class PullOrganizations extends CommandActionAbstract {
    /**
     * 拉取Github的组织信息并且存储到本地数据库中。
     * @return void
     */
    protected function run( ) {
        date_default_timezone_set('Asia/Shanghai');
        
        $dbTmplPath = OhaCore::system()->getPath('Data/Github/data.tmpl.db');
        $dbPath = OhaCore::system()->getPath('Data/Github/data.db', '/');
        if ( !file_exists($dbPath) ) {
            copy($dbTmplPath, $dbPath);
        }
        $dbPath = urlencode($dbPath);
        
        $cfg = \ActiveRecord\Config::instance();
        $cfg->set_model_directory(OhaCore::system()->getPath('Model/Github'));
        $cfg->set_connections(array('dev'=>"sqlite://{$dbPath}?urlencoded=true"));
        $cfg->set_default_connection('dev');
        
        $mainConfig = OhaCore::system()->getConfig();
        $position = 0;
        do {
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $response = $client->request('GET', '/organizations', [
                'query' => [
                    'since' => $position, 
                    'client_id'=>$mainConfig['Github']['ClientID'], 
                    'client_secret'=>$mainConfig['Github']['ClientSecret']],
                'verify'=>false,
            ]);
            if ( $this->checkRateRemainsAndBreakLimit($response) ) {
                # 如果到了限制并且已经突破，则需要重新请求。
                continue;
            }
            
            $responseJson = json_decode($response->getBody(), true);
            if ( empty($responseJson) ) {
                break;
            }
            
            for ( $index=0; $index<count($responseJson); $index++ ) {
                $item = $responseJson[$index];
                if ( Organization::exists(array('id'=>$item['id'])) ) {
                    $this->info('%s %s [exists]', $item['id'], $item['login']);
                    continue;
                }
                
                $detailResponse = $client->request('GET', "orgs/{$item['login']}", ['verify'=>false]);
                if ( $this->checkRateRemainsAndBreakLimit($detailResponse) ) {
                    $index --;
                    # 如果到了限制并且已经突破，则需要重新请求。
                    continue;
                }
                
                $orgDetail = json_decode($detailResponse->getBody(), true);
                $name = isset($orgDetail['name']) ? $orgDetail['name'] : 'No-Name';
                $this->info('%s %s %s', $orgDetail['id'], $orgDetail['login'], $name);
                
                $org = new Organization();
                $org->set_attributes($orgDetail);
                $org->save();
                $position = $item['id'];
            }
        } while ( true );
        
        $this->info("Done Pulling : %d Orgs", Organization::count());
    }
    
    /**
     * @param unknown $response
     * @return boolean
     */
    private function checkRateRemainsAndBreakLimit( $response ) {
        $rateLimit = $response->getHeader('X-RateLimit-Limit');
        $rateRemain = $response->getHeader('X-RateLimit-Remaining');
        $rateResetTime = $response->getHeader('X-RateLimit-Reset');
        if ( 10 < $rateRemain[0] ) {
            return false;
        }
        
        $waitSeconds = $rateResetTime[0] - time();
        $this->info("Rate Limit Remains lower than 10.");
        while ( $waitSeconds > 0 ) {
            $this->info("You need to wait for {$waitSeconds} secs to back to {$rateLimit[0]}r/h.");
            $waitSeconds --;
            sleep(1);
        }
        return true;
    }
}