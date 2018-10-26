<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

class FilePositionListener extends TestListener
{
    public $called = false;

    public function setFilePosition(int $line, int $char): void
    {
        $this->called = true;
    }
}
