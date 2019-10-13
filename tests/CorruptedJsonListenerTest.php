<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Listener\CorruptedJsonListener;
use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

class CorruptedJsonListenerTest extends TestCase
{
    public function testExample(): void
    {
        $filePath = __DIR__.'/data/example.corrupted.json';

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
                        'coordinates' => [102.0, 0.6],
                    ],
                    'properties' => [
                        'prop0' => 'value0',
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [
                                [100.0, 0.0], [101.0, 0.0], [101.0, 1.0], [100.0, 1.0],
                                [100.0],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->assertSame($expectedJson, $repairedJson);
    }
}
