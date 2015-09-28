<?php

use JsonStreamingParser\Parser;

require_once dirname(__FILE__).'/../example/InMemoryListener.php';

class InMemoryListenerTest extends \PHPUnit_Framework_TestCase
{
  public function testExampleJson() {
    $testfile = dirname(__FILE__).'/../example/example.json';
    $this->assertParsesCorrectly($testfile);
  }

  public function testGeoJson() {
    $testfile = dirname(__FILE__).'/../example/geojson/example.geojson';
    $this->assertParsesCorrectly($testfile);
  }

  private function assertParsesCorrectly($testfile) {
    // Parse using an InMemoryListener instance
    $listener = new InMemoryListener();
    $stream = fopen($testfile, 'r');
    try {
      $parser = new Parser($stream, $listener);
      $parser->parse();
      fclose($stream);
    } catch (Exception $e) {
      fclose($stream);
      throw $e;
    }
    $actual = $listener->get_json();

    // Parse using json_decode
    $expected = json_decode(file_get_contents($testfile), true);

    // Make sure the two produce the same object structure
    $this->assertSame($expected, $actual);
  }
}
