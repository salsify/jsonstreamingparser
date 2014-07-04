<?php

namespace JsonStreamingParser\Tests;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Tests\Listener\TestListener;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    public function testTraverseOrder()
    {
        $listener = new TestListener();
        $parser = new Parser(fopen(__DIR__ . '/example/example.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            array(
                'onDocumentStart',
                'onArrayStart',
                'onObjectStart',
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
                'onObjectEnd',
                'onObjectStart',
                'key = name',
                'value = another object',
                'key = cooler than first object?',
                'value = 1',
                'key = nested object',
                'onObjectStart',
                'key = nested object?',
                'value = 1',
                'key = is nested array the same combination i have on my luggage?',
                'value = 1',
                'key = nested array',
                'onArrayStart',
                'value = 1',
                'value = 2',
                'value = 3',
                'value = 4',
                'value = 5',
                'onArrayEnd',
                'onObjectEnd',
                'key = false',
                'value = false',
                'onObjectEnd',
                'onArrayEnd',
                'onDocumentEnd',
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
                array('value' => '2013-10-24'),
                array('value' => '2013-10-25'),
                array('value' => '2013-10-26'),
                array('value' => '2013-10-27'),
                array('value' => '2013-11-01'),
                array('value' => '2013-11-10'),
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
