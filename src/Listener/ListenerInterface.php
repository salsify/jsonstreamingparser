<?php

declare(strict_types=1);

namespace JsonStreamingParser\Listener;

interface ListenerInterface
{
    public function startDocument();

    public function endDocument();

    public function startObject();

    public function endObject();

    public function startArray();

    public function endArray();

    public function key(string $key);

    /**
     * @param mixed $value the value as read from the parser, it may be a string, integer, boolean, etc
     */
    public function value($value);

    public function whitespace(string $whitespace);
}
