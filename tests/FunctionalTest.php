<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    public function testTraverseOrder()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(fopen(__DIR__ . '/data/example.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray',
                'startObject',
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
                'endObject',
                'startObject',
                'key = name',
                'value = another object',
                'key = cooler than first object?',
                'value = 1',
                'key = nested object',
                'startObject',
                'key = nested object?',
                'value = 1',
                'key = is nested array the same combination i have on my luggage?',
                'value = 1',
                'key = nested array',
                'startArray',
                'value = 1',
                'value = 2',
                'value = 3',
                'value = 4',
                'value = 5',
                'endArray',
                'endObject',
                'key = false',
                'value = false',
                'endObject',
                'endArray',
                'endDocument',
            ],
            $listener->order
        );
    }

    public function testListenerGetsNotifiedAboutPositionInFileOfDataRead()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(fopen(__DIR__ . '/data/dateRanges.json', 'r'), $listener);
        $parser->parse();

        $this->assertSame(
            [
                ['value' => '2013-10-24', 'line' => 5, 'char' => 34,],
                ['value' => '2013-10-25', 'line' => 5, 'char' => 59,],
                ['value' => '2013-10-26', 'line' => 6, 'char' => 34,],
                ['value' => '2013-10-27', 'line' => 6, 'char' => 59,],
                ['value' => '2013-11-01', 'line' => 10, 'char' => 44,],
                ['value' => '2013-11-10', 'line' => 10, 'char' => 69,],
            ],
            $listener->positions
        );
    }

    public function testCountsLongLinesCorrectly()
    {
        $value = str_repeat('!', 10000);
        $longStream = self::getMemoryStream(<<<JSON
[
  "$value",
  "$value"
]
JSON
        );

        $listener = new Listener\TestListener();
        $parser = new Parser($longStream, $listener);
        $parser->parse();

        unset($listener->positions[0]['value']);
        unset($listener->positions[1]['value']);

        $this->assertSame(
            [
                ['line' => 2, 'char' => 10004,],
                ['line' => 3, 'char' => 10004,],
            ],
            $listener->positions
        );
    }

    public function testThrowsParingError()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream('{ invalid json }'), $listener);

        $this->setExpectedException('JsonStreamingParser\\ParsingError', 'Parsing error in [1:3]');
        $parser->parse();
    }

    public function testUnicodeSurrogatePair()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream('["Treble clef: \\uD834\\uDD1E!"]'), $listener);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray',
                'value = Treble clef: 𝄞!',
                'endArray',
                'endDocument'
            ],
            $listener->order
        );
    }

    public function testMalformedUnicodeLowSurrogate()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream('["\\uD834abc"]'), $listener);

        $this->setExpectedException(
            'JsonStreamingParser\\ParsingError',
            "Expected '\\u' following a Unicode high surrogate. Got: ab"
        );
        $parser->parse();
    }

    public function testInvalidUnicodeHighSurrogate()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream('["\\uAAAA\\uDD1E"]'), $listener);

        $this->setExpectedException(
            'JsonStreamingParser\\ParsingError',
            'Missing high surrogate for Unicode low surrogate.'
        );
        $parser->parse();
    }

    public function testInvalidUnicodeLowSurrogate()
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream('["\\uD834\\uAAAA"]'), $listener);

        $this->setExpectedException(
            'JsonStreamingParser\\ParsingError',
            'Invalid low surrogate following Unicode high surrogate.'
        );
        $parser->parse();
    }

    public function testFilePositionIsCalledIfDefined()
    {
        $stub = new Listener\FilePositionListener();

        $parser = new Parser(fopen(__DIR__ . '/data/example.json', 'r'), $stub);
        $parser->parse();

        $this->assertTrue($stub->called);
    }

    public function testStopEarly()
    {
        $listener = new Listener\StopEarlyListener();
        $parser = new Parser(self::getMemoryStream('["abc","def"]'), $listener);
        $listener->setParser($parser);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray'
            ],
            $listener->order
        );
    }

    /**
     * @dataProvider providerTestVariousErrors
     * @param string $data
     * @param string $errorMessage
     */
    public function testVariousErrors($data, $errorMessage)
    {
        $listener = new Listener\TestListener();
        $parser = new Parser(self::getMemoryStream($data), $listener);

        $this->setExpectedException(
            'JsonStreamingParser\\ParsingError',
            $errorMessage
        );
        $parser->parse();
    }

    public function providerTestVariousErrors()
    {
        return [
            [
                '{"a"}',
                "Expected ':' after key."
            ], [
                '{"a":"b"]',
                "Expected ',' or '}' while parsing object. Got: ]"
            ], [
                '["a","b".',
                "Expected ',' or ']' while parsing array. Got: ."
            ], [
                '{"price":29..95}',
                "Cannot have multiple decimal points in a number."
            ], [
                '{"count":10e1.5}',
                "Cannot have a decimal point in an exponent."
            ], [
                '{"count":10e15e10}',
                "Cannot have multiple exponents in a number."
            ], [
                '{"count":10-15}',
                "Can only have '+' or '-' after the 'e' or 'E' in a number."
            ], [
                '123',
                "Document must start with object or array."
            ], [
                '[123,456]]',
                "Expected end of document."
            ], [
                '["\x7f"]',
                "Expected escaped character after backslash. Got: x"
            ],
        ];
    }

    /**
     * @param string $content
     * @return resource
     */
    private static function getMemoryStream($content)
    {
        $stream = fopen('php://memory', 'rw');
        fwrite($stream, $content);
        fseek($stream, 0);
        return $stream;
    }
}
