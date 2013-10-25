<?php

use JsonStreamingParser\Listener\SubsetConsumer;

require_once __DIR__ . '/../vendor/autoload.php';

class Consumer extends SubsetConsumer
{
  public $data;

  protected function consume($data)
  {
    $this->data = $data;
    return false;
  }
}

//----------------------------------------------------------------------------------------------------------------------
mb_regex_encoding('UTF-8');
mb_internal_encoding("UTF-8");

$expected = json_decode(file_get_contents(__DIR__ . '/Listener/data/ratherBig.json'), true);
assert($expected);

$consumer = new Consumer;
$parser = new \JsonStreamingParser_Parser(fopen(__DIR__ . '/Listener/data/ratherBig.json', 'r'), $consumer);

$time = microtime(true);
$parser->parse();
echo "JSON size: " . stat(__DIR__ . '/Listener/data/ratherBig.json')['size'] . " bytes,
Time: " . (microtime(true) - $time) . " sec.\n\n";


assert($expected == $consumer->data);
