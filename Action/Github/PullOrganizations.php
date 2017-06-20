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
        
        $position = 0;
        do {
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $response = $client->request('GET', '/organizations', [
                'query' => ['since' => $position],
                'verify'=>false,
            ])->getBody();
            $response = json_decode($response, true);
            if ( empty($response) ) {
                break;
            }
            
            foreach ( $response as $item ) {
                if ( Organization::exists(array('id'=>$item['id'])) ) {
                    $this->info('%s %s [exists]', $item['id'], $item['login']);
                    continue;
                }
                
                $detailResponse = $client->request('GET', "orgs/{$item['login']}", ['verify'=>false])->getBody();
                $orgDetail = json_decode($detailResponse, true);
                $this->info('%s %s %s', $orgDetail['id'], $orgDetail['login'], $orgDetail['name']);
                
                $org = new Organization();
                $org->set_attributes($orgDetail);
                $org->save();
            }
        } while ( true );
        
        $this->info("Done Pulling : %d Orgs", Organization::count());
    }
}