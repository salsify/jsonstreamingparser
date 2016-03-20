<?php
namespace JsonStreamingParser\Listener;

use JsonStreamingParser\Listener;

abstract class SubsetConsumerListener implements Listener
{
    protected $keyValueStack;
    protected $key;

    /**
     * @param mixed $data
     * @return boolean if data was consumed and can be discarded
     */
    abstract protected function consume($data);

    public function startDocument()
    {
        $this->keyValueStack = [];
    }

    public function endDocument()
    {
    }

    public function startObject()
    {
        $this->keyValueStack[] = is_null($this->key) ? [[]] : [$this->key => []];
        $this->key = null;
    }

    public function endObject()
    {
        $keyValue = array_pop($this->keyValueStack);
        $obj = reset($keyValue);
        $this->key = key($keyValue);
        $hasBeenConsumed = $this->consume($obj);

        if (!empty($this->keyValueStack)) {
            $this->value($hasBeenConsumed ? '*consumed*' : $obj);
        }
    }

    public function startArray()
    {
        $this->startObject();
    }

    public function endArray()
    {
        $this->endObject();
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
            $keyValue[$objKey][] = $value;
        }
        $this->keyValueStack[] = $keyValue;
    }

    public function whitespace($whitespace)
    {
        // noop
    }
}
