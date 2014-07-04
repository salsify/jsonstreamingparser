<?php

namespace JsonStreamingParser\Tests\Listener;

use JsonStreamingParser\Listener;

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
