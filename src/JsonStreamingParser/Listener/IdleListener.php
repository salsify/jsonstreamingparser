<?php

namespace JsonStreamingParser\Listener;

use JsonStreamingParser\Listener;

/**
 * Base listener which does nothing
 */
class IdleListener implements Listener
{
    public function onFilePositionChanged($line, $char)
    {
    }

    public function onDocumentStart()
    {
    }

    public function onDocumentEnd()
    {
    }

    public function onObjectStart()
    {
    }

    public function onObjectEnd()
    {
    }

    public function onArrayStart()
    {
    }

    public function onArrayEnd()
    {
    }

    public function key($key)
    {
    }

    public function value($value)
    {
    }

    public function whitespace($whitespace)
    {
    }
}
