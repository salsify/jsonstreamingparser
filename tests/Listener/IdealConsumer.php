<?php
namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\SubsetConsumerListener;

class IdealConsumer extends SubsetConsumerListener
{
    public $data = [];

    /**
     * Consumes anything
     *
     * @param mixed $data
     * @return boolean if data was consumed and can be discarded
     */
    protected function consume($data)
    {
        $this->data = $data;
        return false;
    }
}
