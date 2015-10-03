<?php

require_once dirname(__FILE__) . '/../src/JsonStreamingParser/Parser.php';
require_once dirname(__FILE__).'/InMemoryListener.php';

$testfile = dirname(__FILE__).'/example.json';

$listener = new InMemoryListener();
$stream = fopen($testfile, 'r');
try {
  $parser = new JsonStreamingParser_Parser($stream, $listener);
  $parser->parse();
} catch (Exception $e) {
  fclose($stream);
  throw $e;
}

var_dump($listener->get_json());