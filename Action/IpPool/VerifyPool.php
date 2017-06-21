<?php
namespace Action\IpPool;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Proxy;
class VerifyPool extends CommandActionAbstract {
    /**
     * 验证IP池中的代理IP，并清除掉不可用的代理。
     * @return void
     */
    protected function run( ) {
        $currentIPInfo = $this->getMyIpInfoOnTheWeb();
        $this->info('Current : IP=%s, Location=%s', $currentIPInfo['ip'], $currentIPInfo['location']);
        
        $dbPath = OhaCore::system()->getPath('Data/IpPool/pool.db', '/');
        $dbPath = urlencode($dbPath);
        $cfg = \ActiveRecord\Config::instance();
        $cfg->set_connections(array('dev'=>"sqlite://{$dbPath}?urlencoded=true"));
        $cfg->set_default_connection('dev');
        
        $deltedCount = 0;
        $availableCount = 0;
        $proxies = Proxy::find('all');
        $this->info("%d proxies found, start to verify...", count($proxies));
        foreach ( $proxies as $index => $proxy ) {
            $proxy->location = html_entity_decode($proxy->location);
            $proxy->location = str_replace(array(" ","\n","\t","\r"), '', $proxy->location);
            $isValidated = $proxyIpInfo = $this->getMyIpInfoOnTheWeb($proxy);
            if ( false === $proxyIpInfo ) {
                $proxyIpInfo = array('ip'=>'0.0.0.0', 'location'=>'none');
            }
            $this->info("[%d/%d,O:%d,X:%d] [%s@%s] => [%s:%s@%s] => [%s@%s]",
                $index+1, count($proxies), $availableCount, $deltedCount,
                $currentIPInfo['ip'], $currentIPInfo['location'],
                $proxy->ip, $proxy->port, $proxy->location,
                $proxyIpInfo['ip'], $proxyIpInfo['location']);
            if ( false === $isValidated ) {
                $proxy->delete();
                $deltedCount ++;
            } else {
                $availableCount ++;
            }
        }
        
        $this->info("Veriy done. Available=%d, Deleted=%d",$availableCount,$deltedCount);
    }
    
    /**
     * @return boolean|string[]
     */
    private function getMyIpInfoOnTheWeb( $proxy=null ) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://www.ipip.net/share.html');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        if ( null !== $proxy ) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy->ip);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        
        $response = strip_tags($response);
        $response = str_replace(array(' ',"\n","\r","\t"), '', $response);
        
        preg_match('#IP地址:(?P<ip>\d+?\.\d+?\.\d+?\.\d+?)IP库数据:(?P<location>.*?)IP区县库#is', $response, $match);
        if ( empty($match) ) {
            return false;
        }
        return array('ip'=>$match['ip'], 'location'=>$match['location']);
    }
}