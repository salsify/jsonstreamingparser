<?php

namespace JsonStreamingParser\Tests\Listener;

use JsonStreamingParser\Listener\SubsetConsumer;

class Consumer extends SubsetConsumer
{
    public $data;

    protected function consume($data)
    {
        $this->data = $data;
        return false;
    }
}
