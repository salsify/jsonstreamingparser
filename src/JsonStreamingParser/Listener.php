<?php

namespace JsonStreamingParser;

interface Listener
{
    public function onFilePositionChanged($line, $char);

    public function onDocumentStart();

    public function onDocumentEnd();

    public function onObjectStart();

    public function onObjectEnd();

    public function onArrayStart();

    public function onArrayEnd();

    // Key will always be a string
    public function key($key);

    // Note that value may be a string, integer, boolean, array, etc.
    public function value($value);

    public function whitespace($whitespace);
}
