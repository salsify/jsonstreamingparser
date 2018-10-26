<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\SubsetConsumerListener;

class IdealConsumer extends SubsetConsumerListener
{
    public $data = [];

    /**
     * Consumes anything.
     *
     * @return bool if data was consumed and can be discarded
     */
    protected function consume($data)
    {
        $this->data = $data;

        return false;
    }
}
