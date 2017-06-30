<?php
namespace Action\Github;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Github\Organization;
use function GuzzleHttp\json_decode;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Pool;
use Component\ProxyWebSiteWwwSslProxiesOrg;
class PullOrganizations extends CommandActionAbstract {
    /** 拉取信息时的偏移值 */
    private $position = 0;
    /** 已获组织数量 */
    private $orgCounter = 0;
    /** 当前任务获取组织的数量 */
    private $currentTaskOrgCounter = 0;
    /** 当前任务开始时间 */
    private $taskStartTime = null;
    /** 是否已经达到限制需要更换请求者 */
    private $isSwitchRequesterRequired = false;
    /** 当前请求者信息 */
    private $currentRequester = array(
        'Name' => null,
        'ClientID' => null,
        'ClientSecret' => null,
        'Proxy' => null,
    );
    /** 待批量插入的组织数据 */
    private $batchOrgs = array();
    /** 接口调用限制信息 */
    private $requestRateLimitMessage = null;
    /** 任务列表,false=全部任务结束，true=正在获取中, array=获取完成 */
    private $taskList = null;
    /** @var ProxyWebSiteWwwSslProxiesOrg */
    private $proxyManager = null;
    
    /**
     * 拉取Github的组织信息并且存储到本地数据库中。
     * @param int $position 拉取起始偏移量
     * @return void
     */
    protected function run( $position=null ) {
        $this->proxyManager = new ProxyWebSiteWwwSslProxiesOrg();
        $this->taskStartTime = time();
        
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
        if ( null !== $position ) {
            $this->position = $position;
        }
        
        $this->isSwitchRequesterRequired = true;
        $this->switchRequester();
        do {
            if ( false === $this->taskList ) {
                $this->info("No task any more.");
                break;
            }
            if ( null === $this->taskList ) {
                do {
                    $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
                    try {
                        $response = $client->request('GET', '/organizations', $this->getRequestOption(array('since'=>$this->position)));
                    } catch ( \Exception $e ) { }
                    $this->onAPICallSuccessed($response, 0);
                    break;
                } while( true );
                continue;
            }
            
            $responseJson = $this->taskList;
            $this->taskList = null;
            
            $requestJobs = array();
            $requestJobs[] = new Request('GET', "/organizations");
            foreach ( $responseJson as $responseJsonItem ) {
                $requestJobs[] = new Request('GET', "orgs/{$responseJsonItem['login']}");
            }
            
            $poolOption = array();
            $poolOption['fulfilled'] = array($this,'onAPICallSuccessed');
            $poolOption['rejected'] = array($this,'onAPICallFailed');
            $poolOption['concurrency'] = (null===$this->currentRequester['Proxy']) ? count($requestJobs) : 3;
            $poolOption['options'] = $this->getRequestOption(array('since'=>$this->position));
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $pool = new Pool($client, $requestJobs,$poolOption);
            $promise = $pool->promise();
            
            echo $this->getDisplayPrefix();
            $promise->wait();
            echo "\n";
            
            Organization::insertBatch($this->batchOrgs);
            $this->batchOrgs = array();
            if ( $this->isSwitchRequesterRequired ) {
                $this->position = $responseJson[0]['id'];
                $this->switchRequester();
            }
        } while ( true );
        
        $this->info("Done Pulling : %d Orgs", Organization::count());
    }
    
    /** 获取显示信息前缀*/
    private function getDisplayPrefix() {
        $timeSpend = time()-$this->taskStartTime;
        if ( 0 === $timeSpend ) {
            $timeSpend = 1;
        }
        $speed = sprintf('%.2f', $this->currentTaskOrgCounter / (time()-$this->taskStartTime));
        if ( 4 >= strlen($speed) ) {
            $speed = "0{$speed}";
        }
        $speed = sprintf('%sorg/s', $speed);
        $printPrefix = sprintf("@%s |C:%s P:%d| %s", $speed, $this->orgCounter, $this->position, $this->requestRateLimitMessage);
        return $printPrefix;
    }
    
    /** 获取组织详情信息成功时调用。 */
    public function onAPICallSuccessed( $response, $index ) {
        if ( $this->isSwitchRequesterRequired || !$this->checkLimitRateRemains($response) ) {
            $this->isSwitchRequesterRequired = true;
            return;
        }
        
        $responseJson = json_decode($response->getBody(), true);
        if ( isset($responseJson['id']) ) {
            echo ".";
            $this->batchOrgs[] = $responseJson;
            $this->orgCounter ++;
            $this->currentTaskOrgCounter ++;
        } else {
            echo "*";
            if ( empty($responseJson) ) {
                $this->taskList = false;
            } else {
                $this->taskList = $responseJson;
                $this->position = $responseJson[count($responseJson)-1]['id'];
            }
        }
    }
    
    /** 
     * 获取组织信息失败时调用 
     * @param \GuzzleHttp\Exception\RequestException $reason
     * */
    public function onAPICallFailed($reason, $index) {
        echo "X";
        $this->isSwitchRequesterRequired = true;
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
        
        while ( $this->isSwitchRequesterRequired ) {
            foreach ( $githubApps as $githubApp ) {
                $this->currentRequester = array(
                    'Name' => $githubApp['Name'],
                    'ClientID' => $githubApp['ClientID'],
                    'ClientSecret' => $githubApp['ClientSecret'],
                    'Proxy' => $this->proxyManager->getAnAvailableProxyString(),
                );
            
                try {
                    $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
                    $response = $client->request('GET', 'orgs/github', $this->getRequestOption());
                } catch ( \Exception $e ) {
                    continue;
                }
            
                $waitTime = 0;
                if ( $this->checkLimitRateRemains($response, $waitTime) ) {
                    $this->info("Switch requester : %s available.", $githubApp['Name']);
                    $this->isSwitchRequesterRequired = false;
                    break;
                }
            }
        }
        $this->taskStartTime = time() - 1;
        $this->currentTaskOrgCounter = 0;
    }
    
    /**
     * @param unknown $response
     * @return boolean
     */
    private function checkLimitRateRemains( $response, &$rateResetSeconds=null ) {
        $rateLimit = $response->getHeader('X-RateLimit-Limit');
        $rateRemain = $response->getHeader('X-RateLimit-Remaining');
        $rateResetTime = $response->getHeader('X-RateLimit-Reset');
        
        $this->requestRateLimitMessage = "[U:{$this->currentRequester['Name']} P:{$this->currentRequester['Proxy']} L:{$rateLimit[0]} R:{$rateRemain[0]}]";
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