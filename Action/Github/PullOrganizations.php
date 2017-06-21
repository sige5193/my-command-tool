<?php
namespace Action\Github;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Github\Organization;
use function GuzzleHttp\json_decode;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Pool;
class PullOrganizations extends CommandActionAbstract {
    /** 拉取信息时的偏移值 */
    private $position = 0;
    /** 已获组织数量 */
    private $orgCounter = 0;
    
    /**
     * 拉取Github的组织信息并且存储到本地数据库中。
     * @return void
     */
    protected function run( ) {
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
        
        $this->orgCounter = Organization::count(array('conditions' => '1=1'));
        
        $mainConfig = OhaCore::system()->getConfig();
        $lastOrg = Organization::find('first', array('order'=>'id DESC','limit'=>1));
        if ( null !== $lastOrg ) {
            $this->position = $lastOrg->id;
        }
        do {
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $response = $client->request('GET', '/organizations', [
                'query' => [
                    'client_id'=>$mainConfig['Github']['ClientID'], 
                    'client_secret'=>$mainConfig['Github']['ClientSecret'],
                    'since' => $this->position,],
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
            
            $requestJobs = array();
            foreach ( $responseJson as $responseJsonItem ) {
                $requestJobs[] = new Request('GET', "orgs/{$responseJsonItem['login']}");
            }
            
            $poolOption = array();
            $poolOption['fulfilled'] = array($this,'onPullOrgDetailSuccessed');
            $poolOption['rejected'] = array($this,'onPullOrgDetailFailed');
            $poolOption['concurrency'] = count($responseJson);
            $poolOption['options']['verify'] = false;
            $poolOption['options']['query']['client_id'] = $mainConfig['Github']['ClientID'];
            $poolOption['options']['query']['client_secret'] = $mainConfig['Github']['ClientSecret'];
            //$poolOption['options']['proxy'] = 'tcp://localhost:8125';
            $pool = new Pool($client, $requestJobs,$poolOption);
            $promise = $pool->promise();
            $promise->wait();
        } while ( true );
        
        $this->info("Done Pulling : %d Orgs", Organization::count());
    }
    
    /** 获取组织详情信息成功时调用。 */
    public function onPullOrgDetailSuccessed( $response,$index ) {
        $rateMessage = '';
        $this->checkRateRemainsAndBreakLimit($response, $rateMessage);
        
        $orgDetail = json_decode($response->getBody(), true);
        $name = isset($orgDetail['name']) ? $orgDetail['name'] : 'No-Name';
        $this->info('%s %s %s %s', $rateMessage, $orgDetail['id'], $orgDetail['login'], $name);
        
        $org = new Organization();
        $org->set_attributes($orgDetail);
        $org->save();
        
        $this->position = $orgDetail['id'];
        $this->orgCounter ++;
    }
    
    /** 获取组织信息失败时调用 */
    public function onPullOrgDetailFailed($reason, $index) {
        $this->info($reason);
    }
    
    /**
     * @param unknown $response
     * @return boolean
     */
    private function checkRateRemainsAndBreakLimit( $response, &$message=null ) {
        $rateLimit = $response->getHeader('X-RateLimit-Limit');
        $rateRemain = $response->getHeader('X-RateLimit-Remaining');
        $rateResetTime = $response->getHeader('X-RateLimit-Reset');
        if ( null !== $message ) {
            $message = "[L:{$rateLimit[0]} R:{$rateRemain[0]}]";
        }
        
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