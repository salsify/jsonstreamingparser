<?php

namespace JsonStreamingParser\Tests;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Tests\Listener\TestListener;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    public function testTraverseOrder()
    {
        $listener = new TestListener();
        $parser = new Parser(fopen(__DIR__ . '/../example/example.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            array(
                'start_document',
                'start_array',
                'start_object',
                'key = name',
                'value = example document for wicked fast parsing of huge json docs',
                'key = integer',
                'value = 123',
                'key = totally sweet scientific notation',
                'value = -1.23123',
                'key = unicode? you betcha!',
                'value = ú™£¢∞§♥',
                'key = zero character',
                'value = 0',
                'key = null is boring',
                'value = NULL',
                'end_object',
                'start_object',
                'key = name',
                'value = another object',
                'key = cooler than first object?',
                'value = 1',
                'key = nested object',
                'start_object',
                'key = nested object?',
                'value = 1',
                'key = is nested array the same combination i have on my luggage?',
                'value = 1',
                'key = nested array',
                'start_array',
                'value = 1',
                'value = 2',
                'value = 3',
                'value = 4',
                'value = 5',
                'end_array',
                'end_object',
                'key = false',
                'value = false',
                'end_object',
                'end_array',
                'end_document',
            ),
            $listener->order
        );
    }

    public function testListenerGetsNotifiedAboutPositionInFileOfDataRead()
    {
        $listener = new TestListener();
        $parser = new Parser(fopen(__DIR__ . '/Listener/data/dateRanges.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            array(
                array('value' => '2013-10-24', 'line' => null, 'char' => null,),
                array('value' => '2013-10-25', 'line' => null, 'char' => null,),
                array('value' => '2013-10-26', 'line' => null, 'char' => null,),
                array('value' => '2013-10-27', 'line' => null, 'char' => null,),
                array('value' => '2013-11-01', 'line' => null, 'char' => null,),
                array('value' => '2013-11-10', 'line' => null, 'char' => null,),
            ),
            $listener->positions
        );
    }

    public function testCountsLongLinesCorrectly()
    {
        $value = str_repeat('!', 10000);
        $longStream = self::inMemoryStream(
            <<<JSON
                [
      "$value",
      "$value"
    ]
JSON
        );

        $listener = new TestListener();
        $parser = new Parser($longStream, $listener);
        $parser->parse();

        unset($listener->positions[0]['value']);
        unset($listener->positions[1]['value']);

        $this->assertSame(
            array(
                array('line' => null, 'char' => null,),
                array('line' => null, 'char' => null,),
            ),
            $listener->positions
        );
    }

    public function testThrowsParingError()
    {
        $listener = new TestListener();
        $parser = new Parser(self::inMemoryStream('{ invalid json }'), $listener);

        $this->setExpectedException('JsonStreamingParser\ParsingError', 'Parsing error in [1:3]');
        $parser->parse();
    }

    private static function inMemoryStream($content)
    {
        $stream = fopen('php://memory', 'rw');
        fwrite($stream, $content);
        fseek($stream, 0);
        return $stream;
    }
}
