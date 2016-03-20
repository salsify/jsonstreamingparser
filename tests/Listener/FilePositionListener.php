<?php
namespace JsonStreamingParser\Test\Listener;

class FilePositionListener extends TestListener
{
    public $called = false;

    public function filePosition($line, $char)
    {
        $this->called = true;
    }
}
