<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\IdleListener;

class InvalidArgumentsTest extends \PHPUnit_Framework_TestCase
{
    public function testInvalidStream()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid stream provided');
        $parser = new Parser(null, new IdleListener());
        $parser->parse();
    }

    public function testInvalidListener()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            "Listener must implement JsonStreamingParser\\Listener"
        );
        $stream = fopen('php://memory', 'r');
        $parser = new Parser($stream, new \stdClass());
        $parser->parse();
    }
}
