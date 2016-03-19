<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

$testfile = dirname(__FILE__) . '/../tests/data/example.json';

$listener = new \JsonStreamingParser\Listener\InMemoryListener();
$stream = fopen($testfile, 'r');
try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}

var_dump($listener->getJson());
