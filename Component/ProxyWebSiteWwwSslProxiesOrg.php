<?php
namespace Component;
use Core\Util;
class ProxyWebSiteWwwSslProxiesOrg {
    /** 网址*/
    const URL = 'https://www.sslproxies.org/';
    /** 代理检查网址 */
    const PROXY_CHECK_URL = 'http://ip-api.com/';
    /** 缓存的代理列表 */
    private $proxies = array();
    
    /**
     * 获取有效代理字符串
     * @return string
     */
    public function getAnAvailableProxyString() {
        if ( 80 > count($this->proxies) ) {
            $this->pullProxyList();
        }
        
        Util::printf("Proxy Count : %d\n", count($this->proxies));
        $proxyString = null;
        foreach ( $this->proxies as $index => $item ) {
            unset($this->proxies[$index]);
            $proxyString = "https://{$item['ip']}:{$item['port']}";
            Util::printf("Checking proxy : {$proxyString}\n");
            $client = new \GuzzleHttp\Client(['base_uri' => self::PROXY_CHECK_URL]);
            try {
                $response = $client->request('GET', "/json", ['proxy'=>$proxyString,'connect_timeout'=>5]);
            } catch ( \Exception $e ) {
                continue;
            }
            $json = json_decode($response->getBody(), true);
            if ( null === $json ) {
                $proxyString = null;
            } else {
                break;
            }
        }
        
        if ( null !== $proxyString ) {
            Util::printf("Find availabel proxy : {$proxyString}\n");
        } else {
            Util::printf("Find availabel proxy ---.---.---.---\n");
        }
        return $proxyString;
    }
    
    /** 获取缓存列表 */
    private function pullProxyList() {
        Util::printf("Pulling Proxy List...\n");
        $client = new \GuzzleHttp\Client([
            'base_uri' => self::URL,
            'headers' => ['User-agent' => 'mozilla/5.0 (windows nt 10.0; win64; x64; rv:53.0) gecko/20100101 firefox/53.0'],
            'cookies' => true,
        ]);
        
        $this->proxies = array();
        $response = $client->request('GET', "/", ['verify'=>false]);
        $htmlPage = str_get_html($response->getBody());
        
        $list = $htmlPage->find('#proxylisttable',0)->find('tr');
        foreach ( $list as $index => $item ) {
            if ( 0 == $index || $index==count($list)-1 ) {
                continue;
            }
            
            $proxy = array();
            $proxy['ip'] = trim($item->find('td',0)->text());
            $proxy['port'] = trim($item->find('td',1)->text());
            $proxy['country'] = trim($item->find('td',3)->text());
            $proxy['https'] = trim($item->find('td',6)->text());
            $this->proxies[] = $proxy;
        }
    }
}