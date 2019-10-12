<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

class SubsetConsumerTest extends TestCase
{
    public function testProposesAJsonSubsetToConsume(): void
    {
        $listener = new Listener\DatesRangeConsumer();
        $parser = new Parser(fopen(__DIR__.'/data/dateRanges.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            [
                [
                    'startDate' => '2013-10-24',
                    'endDate' => '2013-10-25',
                ], [
                    'startDate' => '2013-10-26',
                    'endDate' => '2013-10-27',
                ], [
                    'startDate' => '2013-11-01',
                    'endDate' => '2013-11-10',
                ],
            ],
            $listener->dateRanges
        );
    }

    /**
     * @dataProvider differentJsonFiles
     *
     * @param string $fileToProcess
     */
    public function testCollectsStructureCorrectly($fileToProcess): void
    {
        $listener = new Listener\IdealConsumer();
        $parser = new Parser(fopen($fileToProcess, 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            json_decode(file_get_contents($fileToProcess), true),
            $listener->data
        );
    }

    public static function differentJsonFiles()
    {
        $dataDir = __DIR__.'/data';

        return [
            [$dataDir.'/example.json'],
            [$dataDir.'/plain.json'],
            [$dataDir.'/dateRanges.json'],
            [$dataDir.'/escapedChars.json'],
        ];
    }
}
