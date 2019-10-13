<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

$testfile = __DIR__.'/../tests/data/example.json';

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
