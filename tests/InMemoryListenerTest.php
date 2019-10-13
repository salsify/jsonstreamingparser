<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

class InMemoryListenerTest extends TestCase
{
    public function testExampleJson(): void
    {
        $testfile = __DIR__.'/data/example.json';
        $this->assertParsesCorrectly($testfile);
    }

    public function testGeoJson(): void
    {
        $testfile = __DIR__.'/data/example.geojson';
        $this->assertParsesCorrectly($testfile);
    }

    private function assertParsesCorrectly($testfile): void
    {
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
