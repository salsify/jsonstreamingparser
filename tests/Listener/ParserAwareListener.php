<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\IdleListener;
use JsonStreamingParser\Listener\ParserAwareInterface;
use JsonStreamingParser\Parser;

class ParserAwareListener extends IdleListener implements ParserAwareInterface
{
    public $called = false;

    public function setParser(Parser $parser): void
    {
        $this->called = true;
    }
}
