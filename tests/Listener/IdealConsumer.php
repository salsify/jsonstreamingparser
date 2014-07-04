<?php

namespace JsonStreamingParser\Tests\Listener;

use JsonStreamingParser\Listener\SubsetConsumer;

class IdealConsumer extends SubsetConsumer
{

    public $data = array();

    /**
     * Consumes anything
     *
     * @param mixed $data
     *
     * @return boolean if data was consumed and can be discarded
     */
    protected function consume($data)
    {
        $this->data = $data;
        return false;
    }
}
