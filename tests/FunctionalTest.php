<?php
use JsonStreamingParser\Listener;
use JsonStreamingParser\Parser;

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
        array('value' => '2013-10-24', 'line' => 5, 'char' => 34,),
        array('value' => '2013-10-25', 'line' => 5, 'char' => 59,),
        array('value' => '2013-10-26', 'line' => 6, 'char' => 34,),
        array('value' => '2013-10-27', 'line' => 6, 'char' => 59,),
        array('value' => '2013-11-01', 'line' => 10, 'char' => 44,),
        array('value' => '2013-11-10','line' => 10, 'char' => 69,),
      ),
      $listener->positions
    );
  }

  public function testCountsLongLinesCorrectly()
  {
    $value = str_repeat('!', 10000);
    $longStream = self::inMemoryStream(<<<JSON
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

    $this->assertSame(array(
        array('line' => 2, 'char' => 10004,),
        array('line' => 3, 'char' => 10004,),
      ),
      $listener->positions
    );
  }

  public function testThrowsParingError()
  {
    $listener = new TestListener();
    $parser = new Parser(self::inMemoryStream('{ invalid json }'), $listener);

    $this->setExpectedException('JsonStreamingParser\\ParsingError', 'Parsing error in [1:3]');
    $parser->parse();
  }

  public function testUnicodeSurrogatePair()
  {
    $listener = new TestListener();
    $parser = new Parser(self::inMemoryStream('["Treble clef: \\uD834\\uDD1E!"]'), $listener);
    $parser->parse();

    $this->assertSame(
      array(
        'start_document',
        'start_array',
        'value = Treble clef: 𝄞!',
        'end_array',
        'end_document'
      ),
      $listener->order
    );
  }

  public function testMalformedUnicodeLowSurrogate()
  {
    $listener = new TestListener();
    $parser = new Parser(self::inMemoryStream('["\\uD834abc"]'), $listener);

    $this->setExpectedException('JsonStreamingParser\\ParsingError',
      "Expected '\\u' following a Unicode high surrogate. Got: ab");
    $parser->parse();
  }

  public function testInvalidUnicodeHighSurrogate()
  {
    $listener = new TestListener();
    $parser = new Parser(self::inMemoryStream('["\\uAAAA\\uDD1E"]'), $listener);

    $this->setExpectedException('JsonStreamingParser\\ParsingError',
      'Missing high surrogate for Unicode low surrogate.');
    $parser->parse();
  }

  public function testInvalidUnicodeLowSurrogate()
  {
    $listener = new TestListener();
    $parser = new Parser(self::inMemoryStream('["\\uD834\\uAAAA"]'), $listener);

    $this->setExpectedException('JsonStreamingParser\\ParsingError',
      'Invalid low surrogate following Unicode high surrogate.');
    $parser->parse();
  }

  public function testFilePositionIsCalledIfDefined() {
    $stub = new TestFilePositionListener();

    $parser = new Parser(fopen(__DIR__ . '/../example/example.json', 'r'), $stub);
    $parser->parse();

    $this->assertTrue($stub->called);
  }

  private static function inMemoryStream($content)
  {
    $stream = fopen('php://memory', 'rw');
    fwrite($stream, $content);
    fseek($stream, 0);
    return $stream;
  }
}

//======================================================================================================================

class TestListener implements Listener
{

  public $order = array();

  public $positions = array();

  private $currentLine;
  private $currentChar;

  public function file_position($line, $char)
  {
    $this->currentLine = $line;
    $this->currentChar = $char;
  }

  public function start_document()
  {
    $this->order[] = __FUNCTION__;
  }

  public function end_document()
  {
    $this->order[] = __FUNCTION__;
  }

  public function start_object()
  {
    $this->order[] = __FUNCTION__;
  }

  public function end_object()
  {
    $this->order[] = __FUNCTION__;
  }

  public function start_array()
  {
    $this->order[] = __FUNCTION__;
  }

  public function end_array()
  {
    $this->order[] = __FUNCTION__;
  }

  public function key($key)
  {
    $this->order[] = __FUNCTION__ . ' = ' . self::stringify($key);
  }

  public function value($value)
  {
    $this->order[] = __FUNCTION__ . ' = ' . self::stringify($value);
    $this->positions[] = array('value' => $value, 'line' => $this->currentLine, 'char' => $this->currentChar);
  }

  public function whitespace($whitespace)
  {
    // do nothing
  }

  private static function stringify($value)
  {
    return strlen($value) ? $value : var_export($value, true);
  }
}

class TestFilePositionListener extends TestListener {
  public $called = false;

  public function file_position($line, $char) {
    $this->called = true;
  }
}
