<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\GeoJsonListener;

class GeoJsonListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testExample()
    {
        $filePath = dirname(__FILE__) . '/data/example.geojson';

        $coordsCount = 0;
        $figures = [];

        $listener = new GeoJsonListener(function ($item) use (&$coordsCount, &$figures) {
            $coordsCount += count($item['geometry']['coordinates']);
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

        $this->assertEquals(7, $coordsCount);

        $expectedFigures = ['Point', 'LineString', 'Polygon'];
        $this->assertEquals($expectedFigures, $figures);
    }
}
