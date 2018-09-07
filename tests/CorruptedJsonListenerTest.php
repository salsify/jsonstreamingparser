<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\CorruptedJsonListener;

class CorruptedJsonListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testExample()
    {
        $filePath = dirname(__FILE__) . '/data/example.corrupted.json';
        
        $listener = new CorruptedJsonListener();
        $stream = fopen($filePath, 'r');
        try {
            $parser = new Parser($stream, $listener);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw $e;
        }

        $listener->forceEndDocument();

        $repairedJson = $listener->getJson();

        $expectedJson = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [102.0, 0.6]
                    ],
                    'properties' => [
                        'prop0' => 'value0'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [
                                [100.0, 0.0], [101.0, 0.0], [101.0, 1.0], [100.0, 1.0],
                                [100.0]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expectedJson, $repairedJson);
    }
}
