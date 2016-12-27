<?php
namespace MADB\Extractor;

use MADB\Client;

require_once __DIR__ . '/../Constants.php';

class Anime
{
    private $client = null;
    private $crawler = null;

    function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function extract_list()
    {
        $crawler = $this->client->request('GET', URI_ANIME_LIST);
        $list = [];
        do {
            $paging = $crawler->filter('#new_asf nav.resultsNavi > ul > li:nth-child(1)')->text();
            preg_match('/(.*)件（[0-9]+～([0-9]+)件/u', $paging, $m);
            $current = $m[2];
            $total = str_replace(',', '', $m[1]);
            echo "\r${current} / ${total}";

            $links = $crawler->filter('#new_asf table.resultsTbl td:nth-child(2) > a')->extract('href');
            $list = array_merge($list, array_map(function ($val) {
                return URI_BASE . $val;
            }, $links));

            $next = $crawler->filter('#new_asf nav.pager li.nxt > a');
        } while (count($next) > 0 && $crawler = $this->client->request('GET', $next->attr('href')));

        echo PHP_EOL;

        return $list;
    }

    public function extract_series($uri)
    {
        $this->crawler = $this->client->request('GET', $uri);
        $data = $this->extract_main();
        $data['各話情報'] = $this->extract_episodes();
        $data['関連するシリーズ'] = $this->extract_related();
        return self::clean($data);
    }

    public function extract_main($is_more = false)
    {
        $more = $is_more ? 'div.moreBlock' : '';
        $keys = $this->crawler->filter('div.main > ' . $more . ' section.block th')->extract('_text');
        $values = $this->crawler->filter('div.main > ' . $more . ' section.block td')->extract('_text');

        if (empty($keys) || empty($values)) {
            throw new \Exception('Failed to extract');
        }
        $data = array_combine($keys, $values);

        $tmp = $this->crawler->filter('div.main > ' . $more . ' #popup1 dl > dd')->extract('_text');
        $data['製作・制作典拠ID'] = empty($tmp) ? '' : $tmp[0];
        $tmp = $this->crawler->filter('div.main > ' . $more . ' #popup2 dl > dd')->extract('_text');
        $data['メインスタッフ典拠ID'] = empty($tmp) ? '' : $tmp[0];

        return $data;
    }

    public function extract_episodes()
    {
        $num_episodes = (int)mb_substr($this->crawler->filter('div.sub > section:nth-child(1) > h3 > span')->text(), 0, -1);
        if ($num_episodes === 0) {
            return [];
        }

        $episode_crawler = $this->client->request('GET', $this->crawler->getUri() . '/anime_episodes?asf%5Bper%5D=' . $num_episodes);
        $episodes_th = $episode_crawler->filter('table.storyTbl > thead th')->extract('_text');
        $episodes_td = $episode_crawler->filter('table.storyTbl > tbody td')->extract('_text');
        $td_interval = count($episodes_th);

        $data = [];
        foreach ($episodes_td as $idx => $val) {
            $data[$idx / $td_interval][$episodes_th[$idx % $td_interval]] = $val;
        }
        return $data;
    }

    public function extract_related()
    {
        $related_titles = $this->crawler->filter('div.sub > section:nth-child(4) > table.seriesTbl2 > tbody td.o a')->extract('_text');
        $related_urls = $this->crawler->filter('div.sub > section:nth-child(4) > table.seriesTbl2 > tbody td.o a')->extract('href');

        $data = [];
        foreach (array_combine($related_titles, $related_urls) as $title => $url) {
            $data[$title] = URI_BASE . $url;
        }

        return $data;
    }

    public static function clean($arr)
    {
        array_walk_recursive($arr, function (&$val, $key) {
            $val = trim(preg_replace('/(典拠ID.*|すべて表示省略表示)/us', '', $val));

            if (in_array($val, ['-', 'NULL'])) {
                $val = '';
            }
        });
        return $arr;
    }
}