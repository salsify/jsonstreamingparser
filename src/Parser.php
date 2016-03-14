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

    private $_state;
    private $_stack;
    private $_stream;

    /**
     * @var Listener
     */
    private $_listener;
    private $_emit_whitespace;
    private $_emit_file_position;

    private $_buffer;
    private $_buffer_size;
    private $_unicode_buffer;
    private $_unicode_high_surrogate;
    private $_unicode_escape_buffer;
    private $_line_ending;

    private $_line_number;
    private $_char_number;

    private $_stop_parsing = false;

    public function __construct($stream, $listener, $line_ending = "\n", $emit_whitespace = false, $buffer_size = 8192)
    {
        if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new \InvalidArgumentException("Invalid stream provided");
        }
        if (!in_array("JsonStreamingParser\\Listener", class_implements(get_class($listener)))) {
            throw new \InvalidArgumentException("Listener must implement JsonStreamingParser\\Listener");
        }

        $this->_stream = $stream;
        $this->_listener = $listener;
        $this->_emit_whitespace = $emit_whitespace;
        $this->_emit_file_position = method_exists($listener, 'file_position');

        $this->_state = self::STATE_START_DOCUMENT;
        $this->_stack = array();

        $this->_buffer = '';
        $this->_buffer_size = $buffer_size;
        $this->_unicode_buffer = array();
        $this->_unicode_escape_buffer = '';
        $this->_unicode_high_surrogate = -1;
        $this->_line_ending = $line_ending;
    }

    public function parse()
    {
        $this->_line_number = 1;
        $this->_char_number = 1;
        $eof = false;

        while (!feof($this->_stream) && !$eof) {
            $pos = ftell($this->_stream);
            $line = stream_get_line($this->_stream, $this->_buffer_size, $this->_line_ending);
            $ended = (bool)(ftell($this->_stream) - strlen($line) - $pos);
            // if we're still at the same place after stream_get_line, we're done
            $eof = ftell($this->_stream) == $pos;

            $byteLen = strlen($line);
            for ($i = 0; $i < $byteLen; $i++) {
                if ($this->_emit_file_position) {
                    $this->_listener->file_position($this->_line_number, $this->_char_number);
                }
                $this->_consume_char($line[$i]);
                $this->_char_number++;

                if ($this->_stop_parsing) {
                    return;
                }
            }

            if ($ended) {
                $this->_line_number++;
                $this->_char_number = 1;
            }

        }
    }

    public function stop()
    {
        $this->_stop_parsing = true;
    }

    private function _consume_char($c)
    {
        // valid whitespace characters in JSON (from RFC4627 for JSON) include:
        // space, horizontal tab, line feed or new line, and carriage return.
        // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
        if (($c === " " || $c === "\t" || $c === "\n" || $c === "\r") &&
            !($this->_state === self::STATE_IN_STRING ||
                $this->_state === self::STATE_UNICODE ||
                $this->_state === self::STATE_START_ESCAPE ||
                $this->_state === self::STATE_IN_NUMBER ||
                $this->_state === self::STATE_START_DOCUMENT)
        ) {
            // we wrap this so that we don't make a ton of unnecessary function calls
            // unless someone really, really cares about whitespace.
            if ($this->_emit_whitespace) {
                $this->_listener->whitespace($c);
            }
            return;
        }

        switch ($this->_state) {

            case self::STATE_IN_STRING:
                if ($c === '"') {
                    $this->_end_string();
                } elseif ($c === '\\') {
                    $this->_state = self::STATE_START_ESCAPE;
                } elseif (($c < "\x1f") || ($c === "\x7f")) {
                    $this->throwParseError("Unescaped control character encountered: " . $c);
                } else {
                    $this->_buffer .= $c;
                }
                break;

            case self::STATE_IN_ARRAY:
                if ($c === ']') {
                    $this->_end_array();
                } else {
                    $this->_start_value($c);
                }
                break;

            case self::STATE_IN_OBJECT:
                if ($c === '}') {
                    $this->_end_object();
                } elseif ($c === '"') {
                    $this->_start_key();
                } else {
                    $this->throwParseError("Start of string expected for object key. Instead got: " . $c);
                }
                break;

            case self::STATE_END_KEY:
                if ($c !== ':') {
                    $this->throwParseError("Expected ':' after key.");
                }
                $this->_state = self::STATE_AFTER_KEY;
                break;

            case self::STATE_AFTER_KEY:
                $this->_start_value($c);
                break;

            case self::STATE_START_ESCAPE:
                $this->_process_escape_character($c);
                break;

            case self::STATE_UNICODE:
                $this->_process_unicode_character($c);
                break;

            case self::STATE_UNICODE_SURROGATE:
                $this->_unicode_escape_buffer .= $c;
                if (mb_strlen($this->_unicode_escape_buffer) == 2) {
                    $this->_end_unicode_surrogate_interstitial();
                }
                break;

            case self::STATE_AFTER_VALUE:
                $within = end($this->_stack);
                if ($within === self::STACK_OBJECT) {
                    if ($c === '}') {
                        $this->_end_object();
                    } elseif ($c === ',') {
                        $this->_state = self::STATE_IN_OBJECT;
                    } else {
                        $this->throwParseError("Expected ',' or '}' while parsing object. Got: " . $c);
                    }
                } elseif ($within === self::STACK_ARRAY) {
                    if ($c === ']') {
                        $this->_end_array();
                    } elseif ($c === ',') {
                        $this->_state = self::STATE_IN_ARRAY;
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
                    $this->_buffer .= $c;
                } elseif ($c === '.') {
                    if (strpos($this->_buffer, '.') !== false) {
                        $this->throwParseError("Cannot have multiple decimal points in a number.");
                    } elseif (stripos($this->_buffer, 'e') !== false) {
                        $this->throwParseError("Cannot have a decimal point in an exponent.");
                    }
                    $this->_buffer .= $c;
                } elseif ($c === 'e' || $c === 'E') {
                    if (stripos($this->_buffer, 'e') !== false) {
                        $this->throwParseError("Cannot have multiple exponents in a number.");
                    }
                    $this->_buffer .= $c;
                } elseif ($c === '+' || $c === '-') {
                    $last = mb_substr($this->_buffer, -1);
                    if (!($last === 'e' || $last === 'E')) {
                        $this->throwParseError("Can only have '+' or '-' after the 'e' or 'E' in a number.");
                    }
                    $this->_buffer .= $c;
                } else {
                    $this->_end_number();
                    // we have consumed one beyond the end of the number
                    $this->_consume_char($c);
                }
                break;

            case self::STATE_IN_TRUE:
                $this->_buffer .= $c;
                if (mb_strlen($this->_buffer) === 4) {
                    $this->_end_true();
                }
                break;

            case self::STATE_IN_FALSE:
                $this->_buffer .= $c;
                if (mb_strlen($this->_buffer) === 5) {
                    $this->_end_false();
                }
                break;

            case self::STATE_IN_NULL:
                $this->_buffer .= $c;
                if (mb_strlen($this->_buffer) === 4) {
                    $this->_end_null();
                }
                break;

            case self::STATE_START_DOCUMENT:
                $this->_listener->start_document();
                if ($c === '[') {
                    $this->_start_array();
                } elseif ($c === '{') {
                    $this->_start_object();
                } else {
                    $this->throwParseError("Document must start with object or array.");
                }
                break;

            case self::STATE_DONE:
                $this->throwParseError("Expected end of document.");
                break;

            default:
                $this->throwParseError("Internal error. Reached an unknown state: " . $this->_state);
                break;
        }
    }

    private function _is_hex_character($c)
    {
        return ctype_xdigit($c);
    }

    // Thanks: http://stackoverflow.com/questions/1805802/php-convert-unicode-codepoint-to-utf-8
    private function _convert_codepoint_to_character($num)
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

    private function _is_digit($c)
    {
        // Only concerned with the first character in a number.
        return ctype_digit($c) || $c === '-';
    }

    private function _start_value($c)
    {
        if ($c === '[') {
            $this->_start_array();
        } elseif ($c === '{') {
            $this->_start_object();
        } elseif ($c === '"') {
            $this->_start_string();
        } elseif ($this->_is_digit($c)) {
            $this->_start_number($c);
        } elseif ($c === 't') {
            $this->_state = self::STATE_IN_TRUE;
            $this->_buffer .= $c;
        } elseif ($c === 'f') {
            $this->_state = self::STATE_IN_FALSE;
            $this->_buffer .= $c;
        } elseif ($c === 'n') {
            $this->_state = self::STATE_IN_NULL;
            $this->_buffer .= $c;
        } else {
            $this->throwParseError("Unexpected character for value: " . $c);
        }
    }

    private function _start_array()
    {
        $this->_listener->start_array();
        $this->_state = self::STATE_IN_ARRAY;
        $this->_stack[] = self::STACK_ARRAY;
    }

    private function _end_array()
    {
        $popped = array_pop($this->_stack);
        if ($popped !== self::STACK_ARRAY) {
            $this->throwParseError("Unexpected end of array encountered.");
        }
        $this->_listener->end_array();
        $this->_state = self::STATE_AFTER_VALUE;

        if (empty($this->_stack)) {
            $this->_end_document();
        }
    }

    private function _start_object()
    {
        $this->_listener->start_object();
        $this->_state = self::STATE_IN_OBJECT;
        $this->_stack[] = self::STACK_OBJECT;
    }

    private function _end_object()
    {
        $popped = array_pop($this->_stack);
        if ($popped !== self::STACK_OBJECT) {
            $this->throwParseError("Unexpected end of object encountered.");
        }
        $this->_listener->end_object();
        $this->_state = self::STATE_AFTER_VALUE;

        if (empty($this->_stack)) {
            $this->_end_document();
        }
    }

    private function _start_string()
    {
        $this->_stack[] = self::STACK_STRING;
        $this->_state = self::STATE_IN_STRING;
    }

    private function _start_key()
    {
        $this->_stack[] = self::STACK_KEY;
        $this->_state = self::STATE_IN_STRING;
    }

    private function _end_string()
    {
        $popped = array_pop($this->_stack);
        if ($popped === self::STACK_KEY) {
            $this->_listener->key($this->_buffer);
            $this->_state = self::STATE_END_KEY;
        } elseif ($popped === self::STACK_STRING) {
            $this->_listener->value($this->_buffer);
            $this->_state = self::STATE_AFTER_VALUE;
        } else {
            $this->throwParseError("Unexpected end of string.");
        }
        $this->_buffer = '';
    }

    private function _process_escape_character($c)
    {
        if ($c === '"') {
            $this->_buffer .= '"';
        } elseif ($c === '\\') {
            $this->_buffer .= '\\';
        } elseif ($c === '/') {
            $this->_buffer .= '/';
        } elseif ($c === 'b') {
            $this->_buffer .= "\x08";
        } elseif ($c === 'f') {
            $this->_buffer .= "\f";
        } elseif ($c === 'n') {
            $this->_buffer .= "\n";
        } elseif ($c === 'r') {
            $this->_buffer .= "\r";
        } elseif ($c === 't') {
            $this->_buffer .= "\t";
        } elseif ($c === 'u') {
            $this->_state = self::STATE_UNICODE;
        } else {
            $this->throwParseError("Expected escaped character after backslash. Got: " . $c);
        }

        if ($this->_state !== self::STATE_UNICODE) {
            $this->_state = self::STATE_IN_STRING;
        }
    }

    private function _process_unicode_character($c)
    {
        if (!$this->_is_hex_character($c)) {
            $this->throwParseError("Expected hex character for escaped Unicode character. Unicode parsed: " . implode($this->_unicode_buffer) . " and got: " . $c);
        }
        $this->_unicode_buffer[] = $c;
        if (count($this->_unicode_buffer) === 4) {
            $codepoint = hexdec(implode($this->_unicode_buffer));

            if ($codepoint >= 0xD800 && $codepoint < 0xDC00) {
                $this->_unicode_high_surrogate = $codepoint;
                $this->_unicode_buffer = array();
                $this->_state = self::STATE_UNICODE_SURROGATE;
            } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
                if ($this->_unicode_high_surrogate === -1) {
                    $this->throwParseError("Missing high surrogate for Unicode low surrogate.");
                }
                $combined_codepoint = (($this->_unicode_high_surrogate - 0xD800) * 0x400) + ($codepoint - 0xDC00) + 0x10000;

                $this->_end_unicode_character($combined_codepoint);
            } else {
                if ($this->_unicode_high_surrogate != -1) {
                    $this->throwParseError("Invalid low surrogate following Unicode high surrogate.");
                } else {
                    $this->_end_unicode_character($codepoint);
                }
            }
        }
    }

    private function _end_unicode_surrogate_interstitial()
    {
        $unicode_escape = $this->_unicode_escape_buffer;
        if ($unicode_escape != '\\u') {
            $this->throwParseError("Expected '\\u' following a Unicode high surrogate. Got: " . $unicode_escape);
        }
        $this->_unicode_escape_buffer = '';
        $this->_state = self::STATE_UNICODE;
    }

    private function _end_unicode_character($codepoint)
    {
        $this->_buffer .= $this->_convert_codepoint_to_character($codepoint);
        $this->_unicode_buffer = array();
        $this->_unicode_high_surrogate = -1;
        $this->_state = self::STATE_IN_STRING;
    }

    private function _start_number($c)
    {
        $this->_state = self::STATE_IN_NUMBER;
        $this->_buffer .= $c;
    }

    private function _end_number()
    {
        $num = $this->_buffer;

        // thanks to #andig for the fix for big integers
        if (ctype_digit($num) && ((float)$num === (float)((int)$num))) {
            // natural number PHP_INT_MIN < $num < PHP_INT_MAX
            $num = (int)$num;
        } else {
            // real number or natural number outside PHP_INT_MIN ... PHP_INT_MAX
            $num = (float)$num;
        }

        $this->_listener->value($num);

        $this->_buffer = '';
        $this->_state = self::STATE_AFTER_VALUE;
    }

    private function _end_true()
    {
        $true = $this->_buffer;
        if ($true === 'true') {
            $this->_listener->value(true);
        } else {
            $this->throwParseError("Expected 'true'. Got: " . $true);
        }
        $this->_buffer = '';
        $this->_state = self::STATE_AFTER_VALUE;
    }

    private function _end_false()
    {
        $false = $this->_buffer;
        if ($false === 'false') {
            $this->_listener->value(false);
        } else {
            $this->throwParseError("Expected 'false'. Got: " . $false);
        }
        $this->_buffer = '';
        $this->_state = self::STATE_AFTER_VALUE;
    }

    private function _end_null()
    {
        $null = $this->_buffer;
        if ($null === 'null') {
            $this->_listener->value(null);
        } else {
            $this->throwParseError("Expected 'null'. Got: " . $null);
        }
        $this->_buffer = '';
        $this->_state = self::STATE_AFTER_VALUE;
    }

    private function _end_document()
    {
        $this->_listener->end_document();
        $this->_state = self::STATE_DONE;
    }

    /**
     * @param string $message
     * @throws ParsingError
     */
    private function throwParseError($message)
    {
        throw new ParsingError(
            $this->_line_number,
            $this->_char_number,
            $message
        );
    }
}