<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

$testfile = dirname(__FILE__) . '/../tests/data/example.geojson';

$listener = new \JsonStreamingParser\Listener\GeoJsonListener(function ($item) {
    var_dump($item);
});
$stream = fopen($testfile, 'r');
try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}
