<?php 
class JsonStreamingParser_ParsingError extends Exception {

  /**
   * @param int $line
   * @param int $char
   * @param string $message
   */
  public function __construct($line, $char, $message) {
    parent::__construct("Parsing error in [$line:$char]. " . $message);
  }
}
