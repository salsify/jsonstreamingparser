<?php

declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\ListenerInterface;
use JsonStreamingParser\Listener\PositionAwareInterface;

class TestListener implements ListenerInterface, PositionAwareInterface
{
    public $order = [];

    public $positions = [];

    protected $currentLine;
    protected $currentChar;

    public function setFilePosition(int $line, int $char): void
    {
        $this->currentLine = $line;
        $this->currentChar = $char;
    }

    public function startDocument(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function endDocument(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function startObject(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function endObject(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function startArray(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function endArray(): void
    {
        $this->order[] = __FUNCTION__;
    }

    public function key(string $key): void
    {
        $this->order[] = __FUNCTION__.' = '.self::stringify($key);
    }

    public function value($value): void
    {
        $this->order[] = __FUNCTION__.' = '.self::stringify($value);
        $this->positions[] = ['value' => $value, 'line' => $this->currentLine, 'char' => $this->currentChar];
    }

    public function whitespace(string $whitespace): void
    {
    }

    private static function stringify($value)
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (null === $value) {
            return 'NULL';
        }

        return '' !== $value ? $value : var_export($value, true);
    }
}
