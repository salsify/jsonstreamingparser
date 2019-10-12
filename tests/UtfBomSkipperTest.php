<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Listener\IdleListener;
use JsonStreamingParser\Parser;
use PHPUnit\Framework\TestCase;

class UtfBomSkipperTest extends TestCase
{
    public function testExample(): void
    {
        $listener = new IdleListener();
        $stream = fopen(__DIR__.'/data/utf8bom.json', 'r');
        try {
            $parser = new Parser($stream, $listener, PHP_EOL, true);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw $e;
        }

        // Ensure no exception is thrown
        $this->assertTrue(true);
    }
}
