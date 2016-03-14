<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\InMemoryListener;

class InMemoryListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testExampleJson()
    {
        $testfile = dirname(__FILE__) . '/data/example.json';
        $this->assertParsesCorrectly($testfile);
    }

    public function testGeoJson()
    {
        $testfile = dirname(__FILE__) . '/data/example.geojson';
        $this->assertParsesCorrectly($testfile);
    }

    private function assertParsesCorrectly($testfile)
    {
        // Parse using an InMemoryListener instance
        $listener = new InMemoryListener();
        $stream = fopen($testfile, 'r');
        try {
            $parser = new Parser($stream, $listener);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw $e;
        }
        $actual = $listener->getJson();

        // Parse using json_decode
        $expected = json_decode(file_get_contents($testfile), true);

        // Make sure the two produce the same object structure
        $this->assertSame($expected, $actual);
    }
}
