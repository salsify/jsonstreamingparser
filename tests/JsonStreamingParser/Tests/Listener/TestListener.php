<?php

namespace JsonStreamingParser\Tests\Listener;

use JsonStreamingParser\Listener;

class TestListener implements Listener
{
    public $order = array();

    public $positions = array();

    public function onDocumentStart()
    {
        $this->order[] = __FUNCTION__;
    }

    public function onDocumentEnd()
    {
        $this->order[] = __FUNCTION__;
    }

    public function onObjectStart()
    {
        $this->order[] = __FUNCTION__;
    }

    public function onObjectEnd()
    {
        $this->order[] = __FUNCTION__;
    }

    public function onArrayStart()
    {
        $this->order[] = __FUNCTION__;
    }

    public function onArrayEnd()
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
        $this->positions[] = array('value' => $value);
    }

    private static function stringify($value)
    {
        return strlen($value) ? $value : var_export($value, true);
    }
}
