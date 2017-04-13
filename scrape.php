#!/usr/bin/env php
<?php
require './vendor/autoload.php';

$config = require './config.php';
if (!file_exists($config['cache_dir'])) {
    mkdir($config['cache_dir']);
}

$c = new \Colors\Color();
$opt = new Commando\Command();
$opt->argument()
    ->description('target URI')
    ->must(function ($uri) {
        return preg_match('%^https?://(www\.)?mediaarts-db\.jp/an/anime_series/[0-9]+$%', $uri);
    });

$opt->option('o')
    ->alias('out')
    ->description('output file name')
    ->default('result');

$opt->option('k')
    ->alias('key')
    ->description('key for json format')
    ->default('アニメシリーズID');

$supported_formats = ['json'];
if (function_exists('yaml_emit_file')) {
    $supported_formats[] = 'yaml';
}

$opt->option('f')
    ->alias('format')
    ->description('format for output' . PHP_EOL . 'available: ' . implode(', ', $supported_formats))
    ->must(function ($format) use ($supported_formats) {
        return in_array($format, $supported_formats);
    })
    ->default('json');

$opt->flag('no-cache')
    ->description('crawl without using cache files.')
    ->boolean();


$client = new MADB\Client([
    'use_cache' => !$opt['no-cache'],
    'cache_dir' => $config['cache_dir'],
    'user_agent' => $config['user_agent'],
    'interval' => $config['interval']
]);


$anime = new MADB\Extractor\Anime($client);

if (!$opt[0]) {
    echo 'extracting series URIs...' . PHP_EOL;
    $list = $anime->extract_list();
    $filename = 'series_list_' . date('Ymd') . '.txt';
    file_put_contents($config['cache_dir'] . DIRECTORY_SEPARATOR . $filename, implode(PHP_EOL, $list));
} else {
    $list = [$opt[0]];
}

$total = count($list);
$result = [];

echo PHP_EOL . 'extracting series information...' . PHP_EOL;
foreach ($list as $idx => $uri) {
    echo "${idx} / ${total}\r";
    try {
        $data = $anime->extract_series($uri);
        $key = $data[$opt['key']];
        $result[$key] = $data;
        echo $c('OK ')->green()->bold() . $uri . PHP_EOL;;
    } catch (\Exception $e) {
        echo $c('NG ')->red()->bold() . $uri . PHP_EOL;;
        fprintf(STDERR, $e->getMessage() . PHP_EOL);
        fprintf(STDERR, $e->getTraceAsString() . PHP_EOL);
    }
}

if (substr($opt['out'], -(strlen($opt['format']) + 1)) !== '.' . $opt['format'])
    $filename = $opt['out'] . '.' . $opt['format'];
else
    $filename = $opt['out'];

switch ($opt['format']) {
    case 'json':
        file_put_contents($filename, json_encode($result, JSON_UNESCAPED_UNICODE));
        break;
    case 'yaml':
        yaml_emit_file($filename, $result, YAML_UTF8_ENCODING);
        break;
}

echo $c('Done!')->bg_green() . PHP_EOL;