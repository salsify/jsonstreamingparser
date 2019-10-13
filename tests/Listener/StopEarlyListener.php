<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\ParserAwareInterface;
use JsonStreamingParser\Parser;

class StopEarlyListener extends TestListener implements ParserAwareInterface
{
    /**
     * @var Parser;
     */
    protected $parser;

    public function setParser(Parser $parser): void
    {
        $this->parser = $parser;
    }

    public function startArray(): void
    {
        parent::startArray();
        $this->parser->stop();
    }
}
