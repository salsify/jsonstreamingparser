<?php
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
    public function setParser($parser)
    {
        $this->parser = $parser;
    }

    public function startArray()
    {
        parent::startArray();
        $this->parser->stop();
    }
}
