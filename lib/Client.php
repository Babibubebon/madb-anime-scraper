<?php
namespace MADB;

use Symfony\Component\DomCrawler\Crawler;

class Client
{
    protected static $defaut_config = [
        'use_cache' => true,
        'cache_dir' => 'cache',
        'user_agent' => 'Mozilla/5.0 (compatible; MADB-scraper)',
        'interval' => 3,
        'timeout' => 30
    ];

    protected $config = [];

    protected $client = null;

    protected static $last_request = 0;

    function __construct($config)
    {
        $this->config = array_merge(self::$defaut_config, $config);

        $this->client = new \Goutte\Client();
        $guzzleClient = new \GuzzleHttp\Client([
            'timeout' => $this->config['timeout'],
        ]);
        $this->client->setClient($guzzleClient);
        $this->client->setHeader('User-Agent', $this->config['user_agent']);
    }

    public function request($method, $uri, $parameters = [])
    {
        if ($this->config['use_cache']) {
            $uri_parts = parse_url($uri);
            $cache_dir = dirname($this->config['cache_dir'] . $uri_parts['path']);
            if (!file_exists($cache_dir)) {
                mkdir($cache_dir, 0777, true);
            }
            $filename = str_replace(str_split('\/:*?"<>|'), '_', basename($uri));
            $path = $cache_dir . '/' . $filename . '.html';

            if (file_exists($path) && ($html = file_get_contents($path))) {
                // cache hit
                return new Crawler($html, $uri);
            }
        }

        $interval = $this->config['interval'];
        if (time() - self::$last_request < $interval) {
            sleep($interval);
        }

        try {
            $crawler = $this->client->request($method, $uri, $parameters);
            self::$last_request = time();
        } catch (\Zend_Http_Client_Adapter_Exception $e) {
            throw new \Exception('timeout');
        }

        if ($this->client->getResponse()->getStatus() !== 200) {
            throw new \Exception('error response');
        }

        if ($this->config['use_cache']) {
            file_put_contents($path, $crawler->html());
        }

        return $crawler;
    }
}