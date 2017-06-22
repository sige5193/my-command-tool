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
    /** 是否已经达到限制需要更换请求者 */
    private $isSwitchRequesterRequired = false;
    /** 当前请求者信息 */
    private $currentRequester = array(
        'index' => null,
        'Name' => null,
        'ClientID' => null,
        'ClientSecret' => null,
        'Proxy' => null,
        'Concurrency' => null,
    );
    
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
        $lastOrg = Organization::find('first', array('order'=>'id DESC','limit'=>1));
        if ( null !== $lastOrg ) {
            $this->position = $lastOrg->id;
        }
        
        $this->switchRequester();
        do {
            $this->info("Query List offset={$this->position}");
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $response = $client->request('GET', '/organizations', $this->getRequestOption(array('since'=>$this->position)));
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
            if ( null !== $this->currentRequester['Concurrency'] ) {
                $poolOption['concurrency'] = $this->currentRequester['Concurrency'];
            }
            $poolOption['options'] = $this->getRequestOption();
            $pool = new Pool($client, $requestJobs,$poolOption);
            $promise = $pool->promise();
            $promise->wait();
            
            if ( $this->isSwitchRequesterRequired ) {
                $this->position = $responseJson[0]['id'];
                $this->switchRequester();
            }
        } while ( true );
        
        $this->info("Done Pulling : %d Orgs", Organization::count());
    }
    
    /** 获取组织详情信息成功时调用。 */
    public function onPullOrgDetailSuccessed( $response,$index ) {
        $rateMessage = '';
        if ( !$this->checkLimitRateRemains($response, $rateMessage) ) {
            $this->isSwitchRequesterRequired = true;
            return;
        }
        
        $orgDetail = json_decode($response->getBody(), true);
        $name = isset($orgDetail['name']) ? $orgDetail['name'] : 'No-Name';
        $this->info('|%d| %s %s %s %s', $this->orgCounter, $rateMessage, $orgDetail['id'], $orgDetail['login'], $name);
        
        if ( Organization::exists(array('id'=>$orgDetail['id'])) ) {
            return;
        }
        
        $org = new Organization();
        $org->set_attributes($orgDetail);
        $org->save();
        
        if ( $orgDetail['id'] > $this->position ) {
            $this->position = $orgDetail['id'];
        }
        $this->orgCounter ++;
    }
    
    /** 获取组织信息失败时调用 */
    public function onPullOrgDetailFailed($reason, $index) {
        $this->info($reason);
    }
    
    /** 获取请求配置信息 */
    private function getRequestOption($query=array()) {
        $option = array();
        $option['verify'] = false;
        $option['query'] = $query;
        $option['query']['client_id'] = $this->currentRequester['ClientID'];
        $option['query']['client_secret'] = $this->currentRequester['ClientSecret'];
        if ( null !== $this->currentRequester['Proxy'] ) {
            $option['proxy'] = $this->currentRequester['Proxy'];
        }
        return $option;
    }
    
    /** 更换请求者 */
    private function switchRequester() {
        $this->info("Switch requester : start.");
        $mainConfig = OhaCore::system()->getConfig();
        $githubApps = $mainConfig['Github']['AppInfos'];
        
        $activeIndex = $this->currentRequester['index'];
        if ( null === $activeIndex ) {
            $activeIndex = 0;
        } else if ( $activeIndex+1 >= count($githubApps) ) {
            $activeIndex = 0;
        } else {
            $activeIndex ++;
        }
        
        $minWaitTime = null;
        $minWaitTimeIndex = null;
        for ( ; $activeIndex<count($githubApps); $activeIndex++ ) {
            $this->currentRequester = array(
                'index' => $activeIndex,
                'Name' => $githubApps[$activeIndex]['Name'],
                'ClientID' => $githubApps[$activeIndex]['ClientID'],
                'ClientSecret' => $githubApps[$activeIndex]['ClientSecret'],
                'Proxy' => $githubApps[$activeIndex]['Proxy'],
                'Concurrency' => $githubApps[$activeIndex]['Concurrency'],
            );
            
            try {
                $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
                $response = $client->request('GET', 'orgs/github', $this->getRequestOption());
            } catch ( \GuzzleHttp\Exception\ClientException $e ) {
                $this->info("Switch requester : %s blocked.", $githubApps[$activeIndex]['Name']);
                continue;
            }

            $rateMessage = null;
            $waitTime = 0;
            if ( $this->checkLimitRateRemains($response, $rateMessage, $waitTime) ) {
                $this->info("Switch requester : %s available.", $githubApps[$activeIndex]['Name']);
                $this->isSwitchRequesterRequired = false;
                break;
            }
            
            $this->info("Switch requester : %s remains %d secs.", $githubApps[$activeIndex]['Name'], $waitTime);
            if ( null===$minWaitTime || $waitTime<$minWaitTime ){
                $minWaitTime = $waitTime;
                $minWaitTimeIndex = $activeIndex;
            }
        }
        
        if ( $this->isSwitchRequesterRequired ) {
            $minWaitTime += 5;
            while ( $minWaitTime > 0 ) {
                $this->info("You need to wait for {$minWaitTime} secs to back to top limit.");
                $minWaitTime --;
                sleep(1);
            }
            
            $this->currentRequester = array(
                'index' => $minWaitTimeIndex,
                'Name' => $githubApps[$minWaitTimeIndex]['Name'],
                'ClientID' => $githubApps[$minWaitTimeIndex]['ClientID'],
                'ClientSecret' => $githubApps[$minWaitTimeIndex]['ClientSecret'],
                'Proxy' => $githubApps[$minWaitTimeIndex]['Proxy'],
                'Concurrency' => $githubApps[$minWaitTimeIndex]['Concurrency'],
            );
        }
    }
    
    /**
     * @param unknown $response
     * @return boolean
     */
    private function checkLimitRateRemains( $response, &$message=null, &$rateResetSeconds=null ) {
        $rateLimit = $response->getHeader('X-RateLimit-Limit');
        $rateRemain = $response->getHeader('X-RateLimit-Remaining');
        $rateResetTime = $response->getHeader('X-RateLimit-Reset');
        if ( null !== $message ) {
            $message = "[U:{$this->currentRequester['Name']} L:{$rateLimit[0]} R:{$rateRemain[0]}]";
        }
        
        $waitSeconds = $rateResetTime[0] - time();
        if ( null !== $rateResetSeconds ) {
            $rateResetSeconds = $waitSeconds;
        }
        
        if ( 40 > $rateRemain[0] ) {
            return 0;
        }
        return $rateRemain[0];
    }
}