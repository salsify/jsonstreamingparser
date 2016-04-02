<?php
namespace JsonStreamingParser\Test;

use JsonStreamingParser\Parser;
use JsonStreamingParser\Listener\IdleListener;

class UtfBomSkipperTest extends \PHPUnit_Framework_TestCase
{
    public function testExample()
    {
        $listener = new IdleListener();
        $stream = fopen(dirname(__FILE__) . '/data/utf8bom.json', 'r');
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
