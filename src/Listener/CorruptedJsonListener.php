<?php

namespace JsonStreamingParser\Listener;
use JsonStreamingParser\Listener;

/**
 * This implementation of a listener constructs an in-memory
 * representation of the JSON document, this is useful to repair
 * cut json documents (has unexpected end of the files).
 */
class CorruptedJsonListener implements Listener {
    protected $json;

    protected $stack;
    protected $key;

    // Level is required so we know how nested we are.
    protected $level;

    public function getJson() {
        return $this->json;
    }

    public function startDocument() {
        $this->stack = [];
        $this->level = 0;
        // Key is an array so that we can can remember keys per level to avoid
        // it being reset when processing child keys.
        $this->key = [];
    }

    public function endDocument() {
    }

    public function startArray() {
        $this->startObject();
    }

    public function startObject() {
        $this->level++;
        $this->stack[] = [];
    }

    public function endArray() {
        $this->endObject();
    }

    public function endObject() {
        $this->level--;
        $obj = array_pop($this->stack);
        if (empty($this->stack)) {
            // doc is DONE!
            $this->json = $obj;
        } else {
            $this->value($obj);
        }
    }

    /**
     * Value may be a string, integer, boolean, null
     *
     * @param mixed $value
     */
    public function value($value) {
        $obj = array_pop($this->stack);
        if (!empty($this->key[$this->level])) {
            $obj[$this->key[$this->level]] = $value;
            $this->key[$this->level] = null;
        } else {
            $obj[] = $value;
        }
        $this->stack[] = $obj;
    }

    /**
     * @param string $key
     */
    public function key($key) {
        $this->key[$this->level] = $key;
    }

    public function whitespace($whitespace) {
        // do nothing
    }

    /**
     * Forcefully finish the document, end all objects and arrays
     * and set final object to the json property
     */
    public function forceEndDocument() {
        if (empty($this->stack)) {
            return;
        }

        $key = $this->key;
        for($i = $this->level - 1; $i > 0; $i--){
            $value = array_pop($this->stack);
            //value
            $obj = array_pop($this->stack);
            if (!empty($key[$i])) {
                $obj[$key[$i]] = $value;
                $key[$i] = null;
            } else {
                $obj[] = $value;
            }
            if($i > 1) {
                $this->stack[] = $obj;
            }else{
                $this->json = $obj;
            }
        }
    }

    /**
     * Return stack of keys before force ending documents
     * @return mixed
     */
    public function getKey() {
        return $this->key;
    }
}
