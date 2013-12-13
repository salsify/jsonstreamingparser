<?php

namespace JsonStreamingParser\Listener;

abstract class SubsetConsumer implements \JsonStreamingParser_Listener
{
  private $keyValueStack;
  private $key;

  /**
   * @param mixed $data
   * @return boolean if data was consumed and can be discarded
   */
  abstract protected function consume($data);

  public function file_position($line, $char) {

  }

  public function start_document()
  {
    $this->keyValueStack = array();
  }

  public function end_document()
  {
  }

  public function start_object()
  {
    array_push($this->keyValueStack, is_null($this->key) ? array(array()) : array($this->key => array()));
    $this->key = null;
  }

  public function end_object()
  {
    $keyValue = array_pop($this->keyValueStack);
    $obj = reset($keyValue);
    $this->key = key($keyValue);
    $hasBeenConsumed = $this->consume($obj);

    if (!empty($this->keyValueStack)) {
      $this->value($hasBeenConsumed ? '*consumed*' : $obj);
    }

  }

  public function start_array()
  {
    $this->start_object();
  }

  public function end_array()
  {
    $this->end_object();
  }

  public function key($key)
  {
    $this->key = $key;
  }

  public function value($value)
  {
    $keyValue = array_pop($this->keyValueStack);
    $objKey = key($keyValue);

    if ($this->key) {
      $keyValue[$objKey][$this->key] = $value;
    } else {
      array_push($keyValue[$objKey], $value);
    }
    array_push($this->keyValueStack, $keyValue);
  }
}