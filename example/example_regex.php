<?php

declare(strict_types=1);

use JsonStreamingParser\Listener\RegexListener;
use JsonStreamingParser\Parser;

require_once __DIR__.'/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$filename = __DIR__.'/../tests/data/example.json';

echo "Check where the 'name' elements are ('(.*/name)')...".PHP_EOL;
$listener = new RegexListener(['(.*/name)' => function ($data, $path): void {
    echo 'Location is '.$path.' value is '.$data.PHP_EOL;
}]);
$fp = fopen($filename, 'r');
$parser = new Parser($fp, $listener);
$parser->parse();
fclose($fp);

echo PHP_EOL."Extract the second 'name' element ('/1/name')...".PHP_EOL;
$listener = new RegexListener(['/1/name' => function ($data): void {
    echo "Value for '/1/name' is ".$data.PHP_EOL;
}]);
$fp = fopen($filename, 'r');
$parser = new Parser($fp, $listener);
$parser->parse();
fclose($fp);

echo PHP_EOL."Extract each base element ('(/\d*)') and print 'name' element of this...".PHP_EOL;
$listener = new RegexListener(['(/\d*)' => function ($data, $path): void {
    echo 'Location is '.$path.' value is '.$data['name'].PHP_EOL;
}]);
$fp = fopen($filename, 'r');
$parser = new Parser($fp, $listener);
$parser->parse();
fclose($fp);

echo PHP_EOL."Extract 'nested array' element ('(/.*/nested array)')...".PHP_EOL;
$listener = new RegexListener(['(/.*/nested array)' => function ($data, $path): void {
    echo 'Location is '.$path.' value is '.print_r($data, true).PHP_EOL;
}]);
$fp = fopen($filename, 'r');
$parser = new Parser($fp, $listener);
$parser->parse();
fclose($fp);

echo PHP_EOL.PHP_EOL.'Combine above...'.PHP_EOL;
$listener = new RegexListener([
    '/1/name' => function ($data): void {
        echo PHP_EOL."Extract the second 'name' element...".PHP_EOL;
        echo '/1/name='.print_r($data, true).PHP_EOL;
    },
    '(/\d*)' => function ($data, $path): void {
        echo PHP_EOL."Extract each base element and print 'name'...".PHP_EOL;
        echo $path.'='.$data['name'].PHP_EOL;
    },
    '(/.*/nested array)' => function ($data, $path): void {
        echo PHP_EOL."Extract 'nested array' element...".PHP_EOL;
        echo $path.'='.print_r($data, true).PHP_EOL;
    },
]);
$parser = new Parser(fopen($filename, 'r'), $listener);
$parser->parse();

$filename = __DIR__.'/../tests/data/ratherBig.json';

echo 'With a large file extract totals from header and stop...'.PHP_EOL;
$listener = new RegexListener();
$parser = new Parser(fopen($filename, 'r'), $listener);
$listener->setMatch(['/total_rows' => function ($data) use ($parser): void {
    echo '/total_rows='.$data.PHP_EOL;
    $parser->stop();
}]);
$parser->parse();
