<?php

declare(strict_types=1);

namespace JsonStreamingParser\Listener;

use JsonStreamingParser\Parser;

interface ParserAwareInterface
{
    public function setParser(Parser $parser): void;
}
