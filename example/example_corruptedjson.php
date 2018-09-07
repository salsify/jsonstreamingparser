<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

$testfile = dirname(__FILE__) . '/../tests/data/example.geojson';

$listener = new \JsonStreamingParser\Listener\CorruptedJsonListener();
$stream = fopen($testfile, 'r');
try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (\Exception $e) {
    fclose($stream);
    throw $e;
}
$listener->forceEndDocument();
//get repaired json
$repairedJson = $listener->getJson();

print_r($repairedJson);
