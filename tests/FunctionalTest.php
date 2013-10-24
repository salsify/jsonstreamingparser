<?php

namespace JsonStreamingParser;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
  public function testTraverseOrder()
  {
    $listener = new TestListener();
    $parser = new \JsonStreamingParser_Parser(fopen(__DIR__ . '/../example/example.json', 'r'), $listener);
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
        'key = null is boring',
        'value = ',
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
        'value = ',
        'end_object',
        'end_array',
        'end_document',
      ),
      $listener->order
    );
  }
}

//======================================================================================================================

class TestListener implements \JsonStreamingParser_Listener
{

  public $order = array();

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
    $this->order[] = __FUNCTION__ . ' = ' . $key;
  }

  public function value($value)
  {
    $this->order[] = __FUNCTION__ . ' = ' . $value;
  }
}
