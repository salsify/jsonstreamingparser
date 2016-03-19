<?php
namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener;

class TestListener implements Listener
{

    public $order = [];

    public $positions = [];

    protected $currentLine;
    protected $currentChar;

    public function filePosition($line, $char)
    {
        $this->currentLine = $line;
        $this->currentChar = $char;
    }

    public function startDocument()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endDocument()
    {
        $this->order[] = __FUNCTION__;
    }

    public function startObject()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endObject()
    {
        $this->order[] = __FUNCTION__;
    }

    public function startArray()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endArray()
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
        $this->positions[] = ['value' => $value, 'line' => $this->currentLine, 'char' => $this->currentChar];
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
