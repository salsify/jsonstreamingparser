<?php
require_once 'ParsingError.php';
require_once 'Listener.php';

class JsonStreamingParser_Parser {
  private $_state;
  const STATE_START_DOCUMENT     = 0;
  const STATE_DONE               = -1;
  const STATE_IN_ARRAY           = 1;
  const STATE_IN_OBJECT          = 2;
  const STATE_END_KEY            = 3;
  const STATE_AFTER_KEY          = 4;  
  const STATE_IN_STRING          = 5;
  const STATE_START_ESCAPE       = 6;
  const STATE_UNICODE            = 7;
  const STATE_IN_NUMBER          = 8;
  const STATE_IN_TRUE            = 9;
  const STATE_IN_FALSE           = 10;
  const STATE_IN_NULL            = 11;
  const STATE_AFTER_VALUE        = 12;

  const STACK_OBJECT             = 0;
  const STACK_ARRAY              = 1;
  const STACK_KEY                = 2;
  const STACK_STRING             = 3;
  private $_stack;

  private $_stream;

  /**
   * @var JsonStreamingParser_Listener
   */
  private $_listener;

  private $_buffer;
  private $_buffer_size;
  private $_unicode_buffer;
  private $_unicode_high_codepoint;
  private $_line_ending;

  private $_line_number;
  private $_char_number;


  public function __construct($stream, $listener, $line_ending = "\n") {
    if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
      throw new InvalidArgumentException("Argument is not a stream");
    }
    if (!in_array("JsonStreamingParser_Listener", class_implements(get_class($listener)))) {
      throw new InvalidArgumentException("Listener must implement JsonStreamingParser_Listener");
    }

    $this->_stream = $stream;
    $this->_listener = $listener;

    $this->_state = self::STATE_START_DOCUMENT;
    $this->_stack = array();

    $this->_buffer = '';
    $this->_buffer_size = 8192;
    $this->_unicode_buffer = array();
    $this->_unicode_high_codepoint = -1;
    $this->_line_ending = $line_ending;
  }


  public function parse() {
    $this->_line_number = 1;
    $this->_char_number = 1;

    while (!feof($this->_stream)) {
      $pos = ftell($this->_stream);
      $line = stream_get_line($this->_stream, $this->_buffer_size, $this->_line_ending);
      $ended = (bool)(ftell($this->_stream) - strlen($line) - $pos);

      $byteLen = strlen($line);
      for ($i = 0; $i < $byteLen; $i++) {
        $this->_listener->file_position($this->_line_number, $this->_char_number);
        $this->_consume_char($line[$i]);
        $this->_char_number++;
      }

      if ($ended) {
        $this->_line_number++;
        $this->_char_number = 1;
      }

    }
  }

  private function _consume_char($c) {
    // valid whitespace characters in JSON (from RFC4627 for JSON) include:
    // space, horizontal tab, line feed or new line, and carriage return.
    // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
    if (($c === " " || $c === "\t" || $c === "\n" || $c === "\r") &&
        !($this->_state === self::STATE_IN_STRING ||
          $this->_state === self::STATE_UNICODE ||
          $this->_state === self::STATE_START_ESCAPE ||
          $this->_state === self::STATE_IN_NUMBER ||
          $this->_state === self::STATE_START_DOCUMENT)) {
      return;
    }

    switch ($this->_state) {

      case self::STATE_START_DOCUMENT:
        $this->_listener->start_document();
        if ($c === '[') {
          $this->_start_array();
        } elseif ($c === '{') {
          $this->_start_object();
        } else {
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Document must start with object or array.");
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
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Start of string expected for object key. Instead got: ".$c);
        }
        break;

      case self::STATE_END_KEY:
        if ($c !== ':') {
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Expected ':' after key.");
        }
        $this->_state = self::STATE_AFTER_KEY;
        break;

      case self::STATE_AFTER_KEY:
        $this->_start_value($c);
        break;

      case self::STATE_IN_STRING:
        if ($c === '"') {
          $this->_end_string();
        } elseif ($c === '\\') {
          $this->_state = self::STATE_START_ESCAPE;
        } elseif (($c < "\x1f") || ($c === "\x7f")) {
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Unescaped control character encountered: ".$c);
        } else {
          $this->_buffer .= $c;
        }
        break;

      case self::STATE_START_ESCAPE:
        $this->_process_escape_character($c);
        break;

      case self::STATE_UNICODE:
        $this->_process_unicode_character($c);
        break;

      case self::STATE_AFTER_VALUE:
        $within = end($this->_stack);
        if ($within === self::STACK_OBJECT) {
          if ($c === '}') {
            $this->_end_object();
          } elseif ($c === ',') {
            $this->_state = self::STATE_IN_OBJECT;
          } else {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Expected ',' or '}' while parsing object. Got: ".$c);
          }
        } elseif ($within === self::STACK_ARRAY) {
          if ($c === ']') {
            $this->_end_array();
          } elseif ($c === ',') {
            $this->_state = self::STATE_IN_ARRAY;
          } else {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Expected ',' or ']' while parsing array. Got: ".$c);
          }
        } else {
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Finished a literal, but unclear what state to move to. Last state: ".$within);
        }
        break;

      case self::STATE_IN_NUMBER:
        if (preg_match('/\d/', $c)) {
          $this->_buffer .= $c;
        } elseif ($c === '.') {
          if (strpos($this->_buffer, '.') !== false) {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Cannot have multiple decimal points in a number.");
          } elseif (stripos($this->_buffer, 'e') !== false) {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Cannot have a decimal point in an exponent.");
          }
          $this->_buffer .= $c;
        } elseif ($c === 'e' || $c === 'E') {
          if (stripos($this->_buffer, 'e') !== false) {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Cannot have multiple exponents in a number.");
          }
          $this->_buffer .= $c;
        } elseif ($c === '+' || $c === '-') {
          $last = mb_substr($this->_buffer, -1);
          if (!($last === 'e' || $last === 'E')) {
            throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
              "Can only have '+' or '-' after the 'e' or 'E' in a number.");
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

      case self::STATE_DONE:
        throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
          "Expected end of document.");

      default:
        throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
          "Internal error. Reached an unknown state: ".$this->_state);
    }
  }

  private function _is_hex_character($c) {
    return preg_match('/[0-9a-fA-F]/u', $c);
  }

  // Thanks: http://stackoverflow.com/questions/1805802/php-convert-unicode-codepoint-to-utf-8
  private function _convert_codepoint_to_character($num) {
    if($num<=0x7F)       return chr($num);
    if($num<=0x7FF)      return chr(($num>>6)+192).chr(($num&63)+128);
    if($num<=0xFFFF)     return chr(($num>>12)+224).chr((($num>>6)&63)+128).chr(($num&63)+128);
    if($num<=0x1FFFFF)   return chr(($num>>18)+240).chr((($num>>12)&63)+128).chr((($num>>6)&63)+128).chr(($num&63)+128);
    return '';
  }

  private function _is_digit($c) {
    // Only concerned with the first character in a number.
    return preg_match('/[0-9]|-/u',$c);
  }


  private function _start_value($c) {
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
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Unexpected character for value: ".$c);
    }
  }


  private function _start_array() {
    $this->_listener->start_array();
    $this->_state = self::STATE_IN_ARRAY;
    array_push($this->_stack, self::STACK_ARRAY);
  }

  private function _end_array() {
    $popped = array_pop($this->_stack);
    if ($popped !== self::STACK_ARRAY) {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of array encountered.");
    }
    $this->_listener->end_array();
    $this->_state = self::STATE_AFTER_VALUE;

    if (empty($this->_stack)) {
      $this->_end_document();
    }
  }


  private function _start_object() {
    $this->_listener->start_object();
    $this->_state = self::STATE_IN_OBJECT;
    array_push($this->_stack, self::STACK_OBJECT);
  }

  private function _end_object() {
    $popped = array_pop($this->_stack);
    if ($popped !== self::STACK_OBJECT) {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of object encountered.");
    }
    $this->_listener->end_object();
    $this->_state = self::STATE_AFTER_VALUE;

    if (empty($this->_stack)) {
      $this->_end_document();
    }
  }

  private function _start_string() {
    array_push($this->_stack, self::STACK_STRING);
    $this->_state = self::STATE_IN_STRING;
  }

  private function _start_key() {
    array_push($this->_stack, self::STACK_KEY);
    $this->_state = self::STATE_IN_STRING;
  }

  private function _end_string() {
    $popped = array_pop($this->_stack);
    if ($popped === self::STACK_KEY) {
      $this->_listener->key($this->_buffer);
      $this->_state = self::STATE_END_KEY;
    } elseif ($popped === self::STACK_STRING) {
      $this->_listener->value($this->_buffer);
      $this->_state = self::STATE_AFTER_VALUE;
    } else {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Unexpected end of string.");
    }
    $this->_buffer = '';
  }

  private function _process_escape_character($c) {
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
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Expected escaped character after backslash. Got: ".$c);
    }

    if ($this->_state !== self::STATE_UNICODE) {
      $this->_state = self::STATE_IN_STRING;
    }
  }

  private function _process_unicode_character($c) {
    if (!$this->_is_hex_character($c)) {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Expected hex character for escaped unicode character. Unicode parsed: " . implode($this->_unicode_buffer) . " and got: ".$c);
    }
    array_push($this->_unicode_buffer, $c);
    if (count($this->_unicode_buffer) === 4) {
      $codepoint = hexdec(implode($this->_unicode_buffer));

      if ($codepoint >= 0xD800 && $codepoint < 0xDC00) {
        $this->_unicode_high_codepoint = $codepoint;
        $this->_unicode_buffer = array();
      } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
        if ($this->_unicode_high_codepoint === -1) {
          throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
            "Missing high codepoint for unicode low codepoint.");
        }
        $combined_codepoint = (($this->_unicode_high_codepoint - 0xD800) * 0x400) + ($codepoint - 0xDC00) + 0x10000;

        $this->_end_unicode_character($combined_codepoint);
      } else {
        $this->_end_unicode_character($codepoint);
      }
    }
  }

  private function _end_unicode_character($codepoint) {
    $this->_buffer .= $this->_convert_codepoint_to_character($codepoint);
    $this->_unicode_buffer = array();
    $this->_unicode_high_codepoint = -1;
    $this->_state = self::STATE_IN_STRING;
  }


  private function _start_number($c) {
    $this->_state = self::STATE_IN_NUMBER;
    $this->_buffer .= $c;
  }

  private function _end_number() {
    $num = $this->_buffer;
    if (preg_match('/\./', $num)) {
      $num = (float)($num);
    } else {
      $num = (int)($num);
    }
    $this->_listener->value($num);

    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }


  private function _end_true() {
    $true = $this->_buffer;
    if ($true === 'true') {
      $this->_listener->value(true);
    } else {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Expected 'true'. Got: ".$true);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }

  private function _end_false() {
    $false = $this->_buffer;
    if ($false === 'false') {
      $this->_listener->value(false);
    } else {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Expected 'false'. Got: ".$false);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }

  private function _end_null() {
    $null = $this->_buffer;
    if ($null === 'null') {
      $this->_listener->value(null);
    } else {
      throw new JsonStreamingParser_ParsingError($this->_line_number, $this->_char_number,
        "Expected 'null'. Got: ".$null);
    }
    $this->_buffer = '';
    $this->_state = self::STATE_AFTER_VALUE;
  }


  private function _end_document() {
    $this->_listener->end_document();
    $this->_state = self::STATE_DONE;
  }

}