<?php
namespace JsonStreamingParser;

class Parser
{
    const STATE_START_DOCUMENT = 0;
    const STATE_DONE = -1;
    const STATE_IN_ARRAY = 1;
    const STATE_IN_OBJECT = 2;
    const STATE_END_KEY = 3;
    const STATE_AFTER_KEY = 4;
    const STATE_IN_STRING = 5;
    const STATE_START_ESCAPE = 6;
    const STATE_UNICODE = 7;
    const STATE_IN_NUMBER = 8;
    const STATE_IN_TRUE = 9;
    const STATE_IN_FALSE = 10;
    const STATE_IN_NULL = 11;
    const STATE_AFTER_VALUE = 12;
    const STATE_UNICODE_SURROGATE = 13;

    const STACK_OBJECT = 0;
    const STACK_ARRAY = 1;
    const STACK_KEY = 2;
    const STACK_STRING = 3;

    const UTF8_BOM = 1;
    const UTF16_BOM = 2;
    const UTF32_BOM = 3;

    /**
     * @var int
     */
    private $state;

    /**
     * @var array
     */
    private $stack;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var Listener
     */
    private $listener;

    /**
     * @var bool
     */
    private $emitWhitespace;

    /**
     * @var bool
     */
    private $emitFilePosition;

    /**
     * @var string
     */
    private $buffer;

    /**
     * @var int
     */
    private $bufferSize;

    /**
     * @var array
     */
    private $unicodeBuffer;

    /**
     * @var int
     */
    private $unicodeHighSurrogate;

    /**
     * @var string
     */
    private $unicodeEscapeBuffer;

    /**
     * @var string
     */
    private $lineEnding;

    /**
     * @var int
     */
    private $lineNumber;

    /**
     * @var int
     */
    private $charNumber;

    /**
     * @var bool
     */
    private $stopParsing = false;

    /**
     * @var bool
     */
    private $utfBom = 0;

    /**
     * @param resource $stream
     * @param object $listener
     * @param string $lineEnding
     * @param bool $emitWhitespace
     * @param int $bufferSize
     */
    public function __construct($stream, $listener, $lineEnding = "\n", $emitWhitespace = false, $bufferSize = 8192)
    {
        if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new \InvalidArgumentException("Invalid stream provided");
        }

        if (!in_array("JsonStreamingParser\\Listener", class_implements(get_class($listener)))) {
            throw new \InvalidArgumentException("Listener must implement JsonStreamingParser\\Listener");
        }

        $this->stream = $stream;
        $this->listener = $listener;
        $this->emitWhitespace = $emitWhitespace;
        $this->emitFilePosition = method_exists($listener, 'filePosition');

        $this->state = self::STATE_START_DOCUMENT;
        $this->stack = [];

        $this->buffer = '';
        $this->bufferSize = $bufferSize;
        $this->unicodeBuffer = [];
        $this->unicodeEscapeBuffer = '';
        $this->unicodeHighSurrogate = -1;
        $this->lineEnding = $lineEnding;
    }

    public function parse()
    {
        $this->lineNumber = 1;
        $this->charNumber = 1;
        $eof = false;

        while (!feof($this->stream) && !$eof) {
            $pos = ftell($this->stream);
            $line = stream_get_line($this->stream, $this->bufferSize, $this->lineEnding);
            $ended = (bool)(ftell($this->stream) - strlen($line) - $pos);
            // if we're still at the same place after stream_get_line, we're done
            $eof = ftell($this->stream) == $pos;

            $byteLen = strlen($line);
            for ($i = 0; $i < $byteLen; $i++) {
                if ($this->emitFilePosition) {
                    $this->listener->filePosition($this->lineNumber, $this->charNumber);
                }
                $this->consumeChar($line[$i]);
                $this->charNumber++;

                if ($this->stopParsing) {
                    return;
                }
            }

            if ($ended) {
                $this->lineNumber++;
                $this->charNumber = 1;
            }

        }
    }

    public function stop()
    {
        $this->stopParsing = true;
    }

    /**
     * @param string $c
     * @throws ParsingError
     */
    private function consumeChar($c)
    {
        // see https://en.wikipedia.org/wiki/Byte_order_mark
        if ($this->lineNumber == 1 && $this->checkAndSkipUtfBom($c)) {
            return;
        }

        // valid whitespace characters in JSON (from RFC4627 for JSON) include:
        // space, horizontal tab, line feed or new line, and carriage return.
        // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
        if (($c === " " || $c === "\t" || $c === "\n" || $c === "\r") &&
            !($this->state === self::STATE_IN_STRING ||
                $this->state === self::STATE_UNICODE ||
                $this->state === self::STATE_START_ESCAPE ||
                $this->state === self::STATE_IN_NUMBER)
        ) {
            // we wrap this so that we don't make a ton of unnecessary function calls
            // unless someone really, really cares about whitespace.
            if ($this->emitWhitespace) {
                $this->listener->whitespace($c);
            }
            return;
        }

        switch ($this->state) {

            case self::STATE_IN_STRING:
                if ($c === '"') {
                    $this->endString();
                } elseif ($c === '\\') {
                    $this->state = self::STATE_START_ESCAPE;
                } elseif (($c < "\x1f") || ($c === "\x7f")) {
                    $this->throwParseError("Unescaped control character encountered: " . $c);
                } else {
                    $this->buffer .= $c;
                }
                break;

            case self::STATE_IN_ARRAY:
                if ($c === ']') {
                    $this->endArray();
                } else {
                    $this->startValue($c);
                }
                break;

            case self::STATE_IN_OBJECT:
                if ($c === '}') {
                    $this->endObject();
                } elseif ($c === '"') {
                    $this->startKey();
                } else {
                    $this->throwParseError("Start of string expected for object key. Instead got: " . $c);
                }
                break;

            case self::STATE_END_KEY:
                if ($c !== ':') {
                    $this->throwParseError("Expected ':' after key.");
                }
                $this->state = self::STATE_AFTER_KEY;
                break;

            case self::STATE_AFTER_KEY:
                $this->startValue($c);
                break;

            case self::STATE_START_ESCAPE:
                $this->processEscapeCharacter($c);
                break;

            case self::STATE_UNICODE:
                $this->processUnicodeCharacter($c);
                break;

            case self::STATE_UNICODE_SURROGATE:
                $this->unicodeEscapeBuffer .= $c;
                if (mb_strlen($this->unicodeEscapeBuffer) == 2) {
                    $this->endUnicodeSurrogateInterstitial();
                }
                break;

            case self::STATE_AFTER_VALUE:
                $within = end($this->stack);
                if ($within === self::STACK_OBJECT) {
                    if ($c === '}') {
                        $this->endObject();
                    } elseif ($c === ',') {
                        $this->state = self::STATE_IN_OBJECT;
                    } else {
                        $this->throwParseError("Expected ',' or '}' while parsing object. Got: " . $c);
                    }
                } elseif ($within === self::STACK_ARRAY) {
                    if ($c === ']') {
                        $this->endArray();
                    } elseif ($c === ',') {
                        $this->state = self::STATE_IN_ARRAY;
                    } else {
                        $this->throwParseError("Expected ',' or ']' while parsing array. Got: " . $c);
                    }
                } else {
                    $this->throwParseError(
                        "Finished a literal, but unclear what state to move to. Last state: " . $within
                    );
                }
                break;

            case self::STATE_IN_NUMBER:
                if (ctype_digit($c)) {
                    $this->buffer .= $c;
                } elseif ($c === '.') {
                    if (strpos($this->buffer, '.') !== false) {
                        $this->throwParseError("Cannot have multiple decimal points in a number.");
                    } elseif (stripos($this->buffer, 'e') !== false) {
                        $this->throwParseError("Cannot have a decimal point in an exponent.");
                    }
                    $this->buffer .= $c;
                } elseif ($c === 'e' || $c === 'E') {
                    if (stripos($this->buffer, 'e') !== false) {
                        $this->throwParseError("Cannot have multiple exponents in a number.");
                    }
                    $this->buffer .= $c;
                } elseif ($c === '+' || $c === '-') {
                    $last = mb_substr($this->buffer, -1);
                    if (!($last === 'e' || $last === 'E')) {
                        $this->throwParseError("Can only have '+' or '-' after the 'e' or 'E' in a number.");
                    }
                    $this->buffer .= $c;
                } else {
                    $this->endNumber();
                    // we have consumed one beyond the end of the number
                    $this->consumeChar($c);
                }
                break;

            case self::STATE_IN_TRUE:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 4) {
                    $this->endTrue();
                }
                break;

            case self::STATE_IN_FALSE:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 5) {
                    $this->endFalse();
                }
                break;

            case self::STATE_IN_NULL:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 4) {
                    $this->endNull();
                }
                break;

            case self::STATE_START_DOCUMENT:
                $this->listener->startDocument();
                if ($c === '[') {
                    $this->startArray();
                } elseif ($c === '{') {
                    $this->startObject();
                } else {
                    $this->throwParseError("Document must start with object or array.");
                }
                break;

            case self::STATE_DONE:
                $this->throwParseError("Expected end of document.");
                break;

            default:
                $this->throwParseError("Internal error. Reached an unknown state: " . $this->state);
                break;
        }
    }

    private function checkAndSkipUtfBom($c) {
        if ($this->charNumber == 1) {
            if ($c == chr(239)) {
                $this->utfBom = self::UTF8_BOM;
            } elseif ($c == chr(254) || $c == chr(255)) {
                // NOTE: could also be UTF32_BOM
                // second character will tell
                $this->utfBom = self::UTF16_BOM;
            } elseif ($c == chr(0)) {
                $this->utfBom = self::UTF32_BOM;
            }
        }

        if ($this->utfBom == self::UTF16_BOM && $this->charNumber == 2 &&
              $c == chr(254)) {
            $this->utfBom = self::UTF32_BOM;
        }

        if ($this->utfBom == self::UTF8_BOM && $this->charNumber < 4) {
            // UTF-8 BOM starts with chr(239) . chr(187) . chr(191)
            return true;
        } elseif ($this->utfBom == self::UTF16_BOM && $this->charNumber < 3) {
            return true;
        } elseif ($this->utfBom == self::UTF32_BOM && $this->charNumber < 5) {
            return true;
        }

        return false;
    }

    /**
     * @param string $c
     * @return bool
     */
    private function isHexCharacter($c)
    {
        return ctype_xdigit($c);
    }

    /**
     * @see http://stackoverflow.com/questions/1805802/php-convert-unicode-codepoint-to-utf-8
     * @param $num
     * @return string
     */
    private function convertCodepointToCharacter($num)
    {
        if ($num <= 0x7F) {
            return chr($num);
        }
        if ($num <= 0x7FF) {
            return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        }
        if ($num <= 0xFFFF) {
            return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        }
        if ($num <= 0x1FFFFF) {
            return chr(($num >> 18) + 240)
                . chr((($num >> 12) & 63) + 128)
                . chr((($num >> 6) & 63) + 128)
                . chr(($num & 63) + 128);
        }
        return '';
    }

    /**
     * @param $c
     * @return bool
     */
    private function isDigit($c)
    {
        // Only concerned with the first character in a number.
        return ctype_digit($c) || $c === '-';
    }

    /**
     * @param $c
     * @throws ParsingError
     */
    private function startValue($c)
    {
        if ($c === '[') {
            $this->startArray();
        } elseif ($c === '{') {
            $this->startObject();
        } elseif ($c === '"') {
            $this->startString();
        } elseif ($this->isDigit($c)) {
            $this->startNumber($c);
        } elseif ($c === 't') {
            $this->state = self::STATE_IN_TRUE;
            $this->buffer .= $c;
        } elseif ($c === 'f') {
            $this->state = self::STATE_IN_FALSE;
            $this->buffer .= $c;
        } elseif ($c === 'n') {
            $this->state = self::STATE_IN_NULL;
            $this->buffer .= $c;
        } else {
            $this->throwParseError("Unexpected character for value: " . $c);
        }
    }

    private function startArray()
    {
        $this->listener->startArray();
        $this->state = self::STATE_IN_ARRAY;
        $this->stack[] = self::STACK_ARRAY;
    }

    private function endArray()
    {
        $popped = array_pop($this->stack);
        if ($popped !== self::STACK_ARRAY) {
            $this->throwParseError("Unexpected end of array encountered.");
        }
        $this->listener->endArray();
        $this->state = self::STATE_AFTER_VALUE;

        if (empty($this->stack)) {
            $this->endDocument();
        }
    }

    private function startObject()
    {
        $this->listener->startObject();
        $this->state = self::STATE_IN_OBJECT;
        $this->stack[] = self::STACK_OBJECT;
    }

    private function endObject()
    {
        $popped = array_pop($this->stack);
        if ($popped !== self::STACK_OBJECT) {
            $this->throwParseError("Unexpected end of object encountered.");
        }
        $this->listener->endObject();
        $this->state = self::STATE_AFTER_VALUE;

        if (empty($this->stack)) {
            $this->endDocument();
        }
    }

    private function startString()
    {
        $this->stack[] = self::STACK_STRING;
        $this->state = self::STATE_IN_STRING;
    }

    private function startKey()
    {
        $this->stack[] = self::STACK_KEY;
        $this->state = self::STATE_IN_STRING;
    }

    private function endString()
    {
        $popped = array_pop($this->stack);
        if ($popped === self::STACK_KEY) {
            $this->listener->key($this->buffer);
            $this->state = self::STATE_END_KEY;
        } elseif ($popped === self::STACK_STRING) {
            $this->listener->value($this->buffer);
            $this->state = self::STATE_AFTER_VALUE;
        } else {
            $this->throwParseError("Unexpected end of string.");
        }
        $this->buffer = '';
    }

    /**
     * @param string $c
     * @throws ParsingError
     */
    private function processEscapeCharacter($c)
    {
        if ($c === '"') {
            $this->buffer .= '"';
        } elseif ($c === '\\') {
            $this->buffer .= '\\';
        } elseif ($c === '/') {
            $this->buffer .= '/';
        } elseif ($c === 'b') {
            $this->buffer .= "\x08";
        } elseif ($c === 'f') {
            $this->buffer .= "\f";
        } elseif ($c === 'n') {
            $this->buffer .= "\n";
        } elseif ($c === 'r') {
            $this->buffer .= "\r";
        } elseif ($c === 't') {
            $this->buffer .= "\t";
        } elseif ($c === 'u') {
            $this->state = self::STATE_UNICODE;
        } else {
            $this->throwParseError("Expected escaped character after backslash. Got: " . $c);
        }

        if ($this->state !== self::STATE_UNICODE) {
            $this->state = self::STATE_IN_STRING;
        }
    }

    /**
     * @param string $c
     * @throws ParsingError
     */
    private function processUnicodeCharacter($c)
    {
        if (!$this->isHexCharacter($c)) {
            $this->throwParseError(
                "Expected hex character for escaped Unicode character. "
                . "Unicode parsed: " . implode($this->unicodeBuffer) . " and got: " . $c
            );
        }
        $this->unicodeBuffer[] = $c;
        if (count($this->unicodeBuffer) === 4) {
            $codepoint = hexdec(implode($this->unicodeBuffer));

            if ($codepoint >= 0xD800 && $codepoint < 0xDC00) {
                $this->unicodeHighSurrogate = $codepoint;
                $this->unicodeBuffer = [];
                $this->state = self::STATE_UNICODE_SURROGATE;
            } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
                if ($this->unicodeHighSurrogate === -1) {
                    $this->throwParseError("Missing high surrogate for Unicode low surrogate.");
                }
                $combinedCodepoint = (($this->unicodeHighSurrogate - 0xD800) * 0x400) + ($codepoint - 0xDC00) + 0x10000;

                $this->endUnicodeCharacter($combinedCodepoint);
            } else {
                if ($this->unicodeHighSurrogate != -1) {
                    $this->throwParseError("Invalid low surrogate following Unicode high surrogate.");
                } else {
                    $this->endUnicodeCharacter($codepoint);
                }
            }
        }
    }

    private function endUnicodeSurrogateInterstitial()
    {
        $unicode_escape = $this->unicodeEscapeBuffer;
        if ($unicode_escape != '\\u') {
            $this->throwParseError("Expected '\\u' following a Unicode high surrogate. Got: " . $unicode_escape);
        }
        $this->unicodeEscapeBuffer = '';
        $this->state = self::STATE_UNICODE;
    }

    /**
     * @param $codepoint
     */
    private function endUnicodeCharacter($codepoint)
    {
        $this->buffer .= $this->convertCodepointToCharacter($codepoint);
        $this->unicodeBuffer = [];
        $this->unicodeHighSurrogate = -1;
        $this->state = self::STATE_IN_STRING;
    }

    /**
     * @param $c
     */
    private function startNumber($c)
    {
        $this->state = self::STATE_IN_NUMBER;
        $this->buffer .= $c;
    }

    private function endNumber()
    {
        $num = $this->buffer;

        // thanks to #andig for the fix for big integers
        if (ctype_digit($num) && ((float)$num === (float)((int)$num))) {
            // natural number PHP_INT_MIN < $num < PHP_INT_MAX
            $num = (int)$num;
        } else {
            // real number or natural number outside PHP_INT_MIN ... PHP_INT_MAX
            $num = (float)$num;
        }

        $this->listener->value($num);

        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    private function endTrue()
    {
        $this->endSpecialValue(true, 'true');
    }

    private function endFalse()
    {
        $this->endSpecialValue(false, 'false');
    }

    private function endNull()
    {
        $this->endSpecialValue(null, 'null');
    }

    private function endSpecialValue($value, $stringValue)
    {
        if ($this->buffer === $stringValue) {
            $this->listener->value($value);
        } else {
            $this->throwParseError("Expected 'null'. Got: " . $this->buffer);
        }
        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    private function endDocument()
    {
        $this->listener->endDocument();
        $this->state = self::STATE_DONE;
    }

    /**
     * @param string $message
     * @throws ParsingError
     */
    private function throwParseError($message)
    {
        throw new ParsingError(
            $this->lineNumber,
            $this->charNumber,
            $message
        );
    }
}
