<?php namespace JsonStreamingParser\Listener;
use JsonStreamingParser\Listener;

/**
 * Base listener which does nothing
 */
class IdleListener implements Listener
{
  public function start_document() {}

  public function end_document() {}

  public function start_object() {}

  public function end_object() {}

  public function start_array() {}

  public function end_array() {}

  public function key($key) {}

  public function value($value) {}

  public function whitespace($whitespace) {}
}
