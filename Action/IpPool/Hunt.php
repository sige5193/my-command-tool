<?php
namespace Action\IpPool;
use Core\CommandActionAbstract;
use Core\OhaCore;
use Model\Proxy;
class Hunt extends CommandActionAbstract {
    /**
     * 抓取代理IP信息并保存到IP池中。
     * @return void
     */
    protected function run( ) {
        date_default_timezone_set('Asia/Shanghai');
        
        $dbTmplPath = OhaCore::system()->getPath('Data/IpPool/pool.tmpl.db');
        $dbPath = OhaCore::system()->getPath('Data/IpPool/pool.db', '/');
        if ( !file_exists($dbPath) ) {
            copy($dbTmplPath, $dbPath);
        }
        $dbPath = urlencode($dbPath);
        
        $cfg = \ActiveRecord\Config::instance();
        $cfg->set_connections(array('dev'=>"sqlite://{$dbPath}?urlencoded=true"));
        $cfg->set_default_connection('dev');
        
        Proxy::delete_all(array('conditions'=>'1=1'));
        $proxyCounter = 0;
        
        # 抓取 http://www.goubanjia.com
        $this->info('Hunting  http://www.goubanjia.com');
        for ( $page=1; $page<=10; $page++ ) {
            $this->info("Hunting : http://www.goubanjia.com/index{$page}.shtml");
            $client = new \GuzzleHttp\Client([
                'base_uri' => 'http://www.goubanjia.com',
                'headers' => ['User-agent' => 'mozilla/5.0 (windows nt 10.0; win64; x64; rv:53.0) gecko/20100101 firefox/53.0'],
                'cookies' => true,
            ]);
            $response = $client->request('GET', "index{$page}.shtml");
            $htmlPage = str_get_html($response->getBody());
            $list = $htmlPage->find('table', 0)->find('tr');
            foreach ( $list as $index => $item ) {
                if ( 0 === $index ) {
                    continue;
                }
        
                $proxy = new Proxy();
                $ipAndPort = $item->find('td', 0)->children();
                foreach ( $ipAndPort as $index => $ipAndPortItem ) {
                    $style = $ipAndPortItem->getAttribute('style');
                    $style = str_replace(array(' ',';',':'), '', $style);
                    $class = $ipAndPortItem->getAttribute('class');
                    if ( false !== strpos($style, 'displaynone') ) {
                        $ipAndPort[$index] = '';
                    } else if ( false !== strpos($class, 'port') ) {
                        $ipAndPort[$index] = ":".trim($ipAndPort[$index]->text());
                    } else {
                        $ipAndPort[$index] = trim($ipAndPort[$index]->text());
                    }
                }
                $ipAndPort = implode('', $ipAndPort);
        
                $ipAndPort = str_replace(' ', '', $ipAndPort);
                list($proxy->ip, $proxy->port) = explode(':', $ipAndPort);
                $proxy->type = trim($item->find('td', 1)->text());
                $proxy->location = trim($item->find('td', 3)->text());
                $proxy->anonymous = trim($item->find('td', 1)->text());
                $proxy->created_at = date('Y-m-d H:i:s');
                $proxy->save();
                $proxyCounter ++;
                $this->info("[%d] %s:%s", $proxyCounter, $proxy->ip, $proxy->port);
            }
        }
        
        # 抓取http://www.ip181.com
        $this->info('Hunting http://www.ip181.com');
        for ( $page=1; $page<=5; $page++ ) {
            $this->info("Hunting : http://www.ip181.com/daili/{$page}.html");
            $client = new \GuzzleHttp\Client(['base_uri' => 'http://www.ip181.com']);
            $response = $client->request('GET', "daili/{$page}.html");
            $response = iconv("GB2312", "UTF-8//IGNORE", $response->getBody());
            $htmlPage = str_get_html($response);
            $list = $htmlPage->find('table', 0)->find('tr');
            foreach ( $list as $index => $item ) {
                if ( 0 === $index ) {
                    continue;
                }
                $proxy = new Proxy();
                $proxy->ip = trim($item->find('td', 0)->text());
                $proxy->port = trim($item->find('td', 1)->text());
                $proxy->type = trim($item->find('td', 3)->text());
                $proxy->location = trim($item->find('td', 5)->text());
                $proxy->anonymous = trim($item->find('td', 2)->text());
                $proxy->created_at = date('Y-m-d H:i:s');
                $proxy->save();
                $proxyCounter ++;
                $this->info("[%d] %s:%s", $proxyCounter, $proxy->ip, $proxy->port);
            }
        }
        
        # 抓取http://www.xdaili.cn
        $this->info('Hunting http://www.xdaili.cn');
        $response = file_get_contents('http://www.xdaili.cn/ipagent//freeip/getFreeIps?page=1&rows=100');
        $response = json_decode($response, true);
        foreach ( $response['rows'] as $item ) {
            $proxy = new Proxy();
            $proxy->ip = $item['ip'];
            $proxy->port = $item['port'];
            $proxy->type = $item['type'];
            $proxy->location = $item['position'];
            $proxy->anonymous = $item['anony'];
            $proxy->created_at = date('Y-m-d H:i:s');
            $proxy->save();
            $proxyCounter ++;
            $this->info("[%d] %s:%s", $proxyCounter, $proxy->ip, $proxy->port);
        }
        
        # 抓取http://www.89ip.cn
        $this->info('Hunting http://www.89ip.cn');
        $response = file_get_contents('http://www.89ip.cn/apijk/?&tqsl=100&sxa=&sxb=&tta=&ports=&ktip=&cf=1');
        preg_match_all('#(?P<proxy>\d+?\.\d+?\.\d+?\.\d+?:\d+)#is', $response, $proxyMatch);
        foreach ( $proxyMatch['proxy'] as $proxyMatch ) {
            $proxy = new Proxy();
            list($proxy->ip, $proxy->port) = explode(':', $proxyMatch);
            $proxy->created_at = date('Y-m-d H:i:s');
            $proxy->save();
            $proxyCounter ++;
            $this->info("[%d] %s:%s", $proxyCounter, $proxy->ip, $proxy->port);
        }
        
        # 抓取http://www.xicidaili.com前五页
        $this->info("Hunting www.xicidaili.com");
        foreach (array('nn','nt','wn','wt') as $category ){
            for ( $page=1; $page<=5; $page++ ) {
                $this->info("Hunting : http://www.xicidaili.com/{$category}/{$page}");
                $client = new \GuzzleHttp\Client(['base_uri' => 'http://www.xicidaili.com']);
                $response = $client->request('GET', "{$category}/{$page}");
                $htmlPage = str_get_html($response->getBody());
                $list = $htmlPage->find('#ip_list', 0)->find('tr');
                foreach ( $list as $index => $item ) {
                    if ( 0 === $index ) { 
                        continue; 
                    }
                    $proxy = new Proxy();
                    $proxy->ip = trim($item->find('td', 1)->text());
                    $proxy->port = trim($item->find('td', 2)->text());
                    $proxy->type = trim($item->find('td', 5)->text());
                    $proxy->location = trim($item->find('td', 3)->text());
                    $proxy->anonymous = trim($item->find('td', 4)->text());
                    $proxy->created_at = date('Y-m-d H:i:s');
                    $proxy->save();
                    $proxyCounter ++;
                    $this->info("[%d] %s:%s", $proxyCounter, $proxy->ip, $proxy->port);
                }
            }
        }
        
        $this->info("Done, {$proxyCounter} proxies found.");
    }
}