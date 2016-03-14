<?php

require_once __DIR__ . '/../vendor/autoload.php';

use JsonStreamingParser\Parser;
use JsonStreamingParser\Test\Listener\IdealConsumer;

mb_regex_encoding('UTF-8');
mb_internal_encoding("UTF-8");

$filePath = __DIR__ . '/data/ratherBig.json';

$expected = json_decode(file_get_contents($filePath), true);
assert($expected);

$consumer = new IdealConsumer;
$parser = new Parser(fopen($filePath, 'r'), $consumer);

$time = microtime(true);
$parser->parse();

echo "JSON size: " . stat($filePath)['size'] . " bytes\n";
echo "Time: " . sprintf('%.4f', microtime(true) - $time) . " sec.\n\n";

assert($expected == $consumer->data);
