<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Parser;

class StopEarlyListener extends TestListener
{
    /**
     * @var Parser;
     */
    protected $parser;

    /**
     * @param Parser $parser
     */
    public function setParser($parser): void
    {
        $this->parser = $parser;
    }

    public function startArray(): void
    {
        parent::startArray();
        $this->parser->stop();
    }
}
