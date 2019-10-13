<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Listener\GeoJsonListener;
use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

class GeoJsonListenerTest extends TestCase
{
    public function testExample(): void
    {
        $filePath = __DIR__.'/data/example.geojson';

        $coordsCount = 0;
        $figures = [];

        $listener = new GeoJsonListener(function ($item) use (&$coordsCount, &$figures): void {
            $coordsCount += \count($item['geometry']['coordinates']);
            $figures[] = $item['geometry']['type'];
        });
        $stream = fopen($filePath, 'r');
        try {
            $parser = new Parser($stream, $listener);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw $e;
        }

        $this->assertSame(9, $coordsCount);

        $expectedFigures = ['Point', 'LineString', 'Polygon', 'Point'];
        $this->assertSame($expectedFigures, $figures);
    }
}
