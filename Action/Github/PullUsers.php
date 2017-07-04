<?php
namespace Action\Github;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Github\Organization;
use function GuzzleHttp\json_decode;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Pool;
use Model\Github\User;
use Component\ProxyWebSiteWwwSslProxiesOrg;
class PullUsers extends CommandActionAbstract {
    /** 拉取信息时的偏移值 */
    private $position = 0;
    /** 已获用户数量 */
    private $userCounter = 0;
    /** 当前任务获取用户的数量 */
    private $currentTaskUserCounter = 0;
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
    /** 待批量插入的用户数据 */
    private $batchUsers = array();
    /** 接口调用限制信息 */
    private $requestRateLimitMessage = null;
    /** 任务列表,false=全部任务结束，true=正在获取中, array=获取完成 */
    private $taskList = null;
    
    /**
     * 拉取Github的用户信息并且存储到本地数据库中。
     * @return void
     */
    protected function run( $position=null ) {
        $this->proxyManager = new ProxyWebSiteWwwSslProxiesOrg();
        $this->taskStartTime = time();
        
        $dbTmplPath = OhaCore::system()->getPath('Data/Github/users.tmpl.db');
        $dbPath = OhaCore::system()->getPath('Data/Github/users.db', '/');
        if ( !file_exists($dbPath) ) {
            copy($dbTmplPath, $dbPath);
        }
        $dbPath = urlencode($dbPath);
        
        $cfg = \ActiveRecord\Config::instance();
        $cfg->set_model_directory(OhaCore::system()->getPath('Model/Github'));
        $cfg->set_connections(array('dev'=>"sqlite://{$dbPath}?urlencoded=true"));
        $cfg->set_default_connection('dev');
        
        $this->userCounter = User::count(array('conditions' => '1=1'));
        $lastUser = User::find('first', array('order'=>'id DESC','limit'=>1));
        if ( null !== $lastUser ) {
            $this->position = $lastUser->id;
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
                    try {
                        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
                        $response = $client->request('GET', '/users', $this->getRequestOption(array('since'=>$this->position)));
                        $this->onAPICallSuccessed($response, 0);
                        break;
                    }catch ( \Exception $e ) {}
                } while( true );
                continue;
            }
            
            $responseJson = $this->taskList;
            $this->taskList = null;
            
            $requestJobs = array();
            $requestJobs[] = new Request('GET', "/users");
            foreach ( $responseJson as $responseJsonItem ) {
                $requestJobs[] = new Request('GET', "users/{$responseJsonItem['login']}");
            }
            
            $poolOption = array();
            $poolOption['fulfilled'] = array($this,'onAPICallSuccessed');
            $poolOption['rejected'] = array($this,'onAPICallFailed');
            $poolOption['concurrency'] = (null===$this->currentRequester['Proxy']) ? count($requestJobs) : 10;
            $poolOption['options'] = $this->getRequestOption(array('since'=>$this->position));
            $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
            $pool = new Pool($client, $requestJobs,$poolOption);
            $promise = $pool->promise();
            
            echo $this->getDisplayPrefix();
            $promise->wait();
            echo "\n";
            
            User::insertBatch($this->batchUsers);
            $this->batchUsers = array();
            if ( $this->isSwitchRequesterRequired ) {
                $this->position = $responseJson[0]['id'];
                $this->switchRequester();
            }
        } while ( true );
        
        $this->info("Done Pulling : %d Users", Organization::count());
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
            $this->batchUsers[] = $responseJson;
            $this->userCounter ++;
            $this->currentTaskUserCounter ++;
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
    
    /**
     * @param unknown $response
     * @return boolean
     */
    private function checkLimitRateRemains( $response, &$rateResetSeconds=null ) {
        $rateLimit = $response->getHeader('X-RateLimit-Limit');
        $rateRemain = $response->getHeader('X-RateLimit-Remaining');
        $rateResetTime = $response->getHeader('X-RateLimit-Reset');
        
        $this->requestRateLimitMessage = "[U:{$this->currentRequester['Name']} L:{$rateLimit[0]} R:{$rateRemain[0]}]";
        $waitSeconds = $rateResetTime[0] - time();
        if ( null !== $rateResetSeconds ) {
            $rateResetSeconds = $waitSeconds;
        }
        
        if ( 40 > $rateRemain[0] ) {
            return 0;
        }
        return $rateRemain[0];
    }
    
    /** @var ProxyWebSiteWwwSslProxiesOrg */
    private $proxyManager = null;
    
    /** 更换请求者 */
    private function switchRequester() {
        $this->info("Switch requester : start.");
        $mainConfig = OhaCore::system()->getConfig();
        $githubApps = $mainConfig['Github']['AppInfos'];
    
        $isNoPorxyTried = false;
        while ( $this->isSwitchRequesterRequired ) {
            foreach ( $githubApps as $githubApp ) {
                $this->currentRequester = array(
                    'Name' => $githubApp['Name'],
                    'ClientID' => $githubApp['ClientID'],
                    'ClientSecret' => $githubApp['ClientSecret'],
                    'Proxy' => $isNoPorxyTried ? $this->proxyManager->getAnAvailableProxyString() : null,
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
                $isNoPorxyTried = true;
            }
        }
        $this->taskStartTime = time() - 1;
        $this->currentTaskUserCounter = 0;
    }
    
    /** 获取请求配置信息 */
    private function getRequestOption($query=array()) {
        $option = array();
        $option['connect_timeout'] = 3;
        $option['verify'] = false;
        $option['query'] = $query;
        $option['query']['client_id'] = $this->currentRequester['ClientID'];
        $option['query']['client_secret'] = $this->currentRequester['ClientSecret'];
        if ( null !== $this->currentRequester['Proxy'] ) {
            $option['proxy'] = $this->currentRequester['Proxy'];
        }
        return $option;
    }
    
    /** 获取显示信息前缀*/
    private function getDisplayPrefix() {
        $timeSpend = time()-$this->taskStartTime;
        if ( 0 === $timeSpend ) {
            $timeSpend = 1;
        }
        $speed = sprintf('%.2f', $this->currentTaskUserCounter / (time()-$this->taskStartTime));
        if ( 4 >= strlen($speed) ) {
            $speed = "0{$speed}";
        }
        $speed = sprintf('%sorg/s', $speed);
        $printPrefix = sprintf("@%s |C:%s P:%d| %s", $speed, $this->userCounter, $this->position, $this->requestRateLimitMessage);
        return $printPrefix;
    }
}