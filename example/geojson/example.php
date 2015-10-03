<?php
require_once dirname(__FILE__) . '/../../src/JsonStreamingParser/Listener.php';
require_once dirname(__FILE__) . '/../../src/JsonStreamingParser/Parser.php';

/**
 * This basic geojson implementation of a listener simply constructs an in-memory
 * representation of the JSON document at the second level, this is useful so only
 * a single Feature will be kept in memory rather than the whole FeatureCollection.
 */
class GeoJsonParser implements JsonStreamingParser_Listener {
    private $_json;

    private $_stack;
    private $_key;
    // Level is required so we know how nested we are.
    private $_level;

    public function file_position($line, $char) {

    }

    public function get_json() {
        return $this->_json;
    }

    public function start_document() {
        $this->_stack = array();
        $this->_level = 0;
        // Key is an array so that we can can remember keys per level to avoid it being reset when processing child keys.
        $this->_key = array();
    }

    public function end_document() {
        // w00t!
    }

    public function start_object() {
        $this->_level++;
        $this->_stack[] = array();
        // Reset the stack when entering the second level
        if($this->_level == 2) {
            $this->_stack = array();
            $this->_key[$this->_level] = null;
        }
    }

    public function end_object() {
        $this->_level--;
        $obj = array_pop($this->_stack);
        if (empty($this->_stack)) {
            // doc is DONE!
            $this->_json = $obj;
        } else {
            $this->value($obj);
        }
        // Output the stack when returning to the second level
        if($this->_level == 2) {
            var_dump($this->_json);
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
        $this->_key[$this->_level] = $key;
    }

    // Note that value may be a string, integer, boolean, null
    public function value($value) {
        $obj = array_pop($this->_stack);
        if ($this->_key[$this->_level]) {
            $obj[$this->_key[$this->_level]] = $value;
            $this->_key[$this->_level] = null;
        } else {
            $obj[] = $value;
        }
        $this->_stack[] = $obj;
    }

    public function whitespace($whitespace) {
        // do nothing
    }
}

$testfile = dirname(__FILE__).'/example.geojson';

$listener = new GeoJsonParser();
$stream = fopen($testfile, 'r');
try {
    $parser = new JsonStreamingParser_Parser($stream, $listener);
    $parser->parse();
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}
