<?php
namespace JsonStreamingParser\Listener;

use JsonStreamingParser\Listener;

/**
 * Base listener which does nothing
 */
class IdleListener implements Listener
{
    public function startDocument()
    {
    }

    public function endDocument()
    {
    }

    public function startObject()
    {
    }

    public function endObject()
    {
    }

    public function startArray()
    {
    }

    public function endArray()
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
