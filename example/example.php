<?php
require_once dirname(__FILE__).'/../src/JsonStreamingParser/Listener.php';
require_once dirname(__FILE__).'/../src/JsonStreamingParser/Parser.php';

/**
 * This basic implementation of a listener simply constructs an in-memory
 * representation of the JSON document, which is a little silly since the whole
 * point of a streaming parser is to avoid doing just that. However, it gets
 * the point across.
 */
class ArrayMaker implements JsonStreamingParser_Listener {
  private $_json;

  private $_stack;
  private $_key;

  public function file_position($line, $char) {

  }

  public function get_json() {
    return $this->_json;
  }

  public function start_document() {
    $this->_stack = array();

    $this->_key = null;
  }

  public function end_document() {
    // w00t!
  }

  public function start_object() {
    array_push($this->_stack, array());
  }

  public function end_object() {
    $obj = array_pop($this->_stack);
    if (empty($this->_stack)) {
      // doc is DONE!
      $this->_json = $obj;
    } else {
      $this->value($obj);
    }
  }

  public function start_array() {
    $this->start_object();
  }

  public function end_array() {
    $this->end_object();
  }

  // Key will always be a string
  public function key($key) {
    $this->_key = $key;
  }

  // Note that value may be a string, integer, boolean, null
  public function value($value) {
    $obj = array_pop($this->_stack);
    if ($this->_key) {
      $obj[$this->_key] = $value;
      $this->_key = null;
    } else {
      array_push($obj, $value);
    }
    array_push($this->_stack, $obj);
  }

  public function whitespace($whitespace) {
    // do nothing
  }
}

$testfile = dirname(__FILE__).'/example.json';

$listener = new ArrayMaker();
$stream = fopen($testfile, 'r');
try {
  $parser = new JsonStreamingParser_Parser($stream, $listener);
  $parser->parse();
} catch (Exception $e) {
  fclose($stream);
  throw $e;
}

var_dump($listener->get_json());