<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\IdleListener;

class IdleListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testExample()
    {
        $listener = new IdleListener();
        $stream = fopen(dirname(__FILE__) . '/data/example.json', 'r');
        try {
            $parser = new Parser($stream, $listener, PHP_EOL, true);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw $e;
        }
    }
}
