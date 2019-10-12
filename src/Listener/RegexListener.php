<?php

declare(strict_types=1);

namespace JsonStreamingParser\Listener;

/**
 * Allow to select elements from a JSON being loaded via a regex expression.
 * As the JSON is loaded you can use expressions such as /name to select
 * a name attribute from the root of the document.
 * For arrays, /1/name which is the name element from the second element
 * of an array. Using things like /\d* will match any array element.
 *
 * Basic usage (with file tests/data/example.json):
 * $listener = new RegexListener(["/1/name" => function ($data ) {
 *     echo "/1/name=".$data.PHP_EOL;
 * }]);
 * $parser = new Parser(fopen($filename, 'rb'), $listener);
 * $parser->parse();
 *
 * Capture groups can also be used to provide the element path to the data
 * located, so (/\d*) will pass the array element number along with the
 * element value.
 *
 * Example using capture groups:
 * $listener = new RegexListener(["(/\d*)" => function ($data, $path ) {
 *     echo $path."=".$data['name'].PHP_EOL;
 * }]);
 * $parser = new Parser(fopen($filename, 'rb'), $listener);
 * $parser->parse();
 *
 * It's also possible to stop the load if you only need specific data.
 *
 * Example to stop read (with file tests/data/ratherBig.json):
 * $listener = new RegexListener();
 * $parser = new Parser(fopen($filename, 'rb'), $listener);
 * $listener->setMatch(["/total_rows" => function ($data ) use ($parser) {
 *     echo "/total_rows=".$data.PHP_EOL;
 *     $parser->stop();
 * }]);
 *
 * @author NigelRen <nigelrel3@yahoo.co.uk>
 */
class RegexListener implements ListenerInterface
{
    /**
     * Constant to flag that it is an object level.
     *
     * @var int
     */
    const OBJECT = -1;
    /**
     * Constant to flag that it is an array level.
     *
     * @var int
     */
    const ARRAY = 0;

    /**
     * Various matching criteria along with the callback to execute.
     *
     * @var callable[] - [regex => callable, ...]
     */
    private $dataMatch;
    /**
     * Path to the current data item, used to match with $dataMatch for extract.
     * Path is made up of keys for each level of structure.
     *
     * @var array string
     */
    private $path = [];

    /**
     * For arrays, this will have the numerical index, for objects this
     * will be -1.
     *
     * @var array
     */
    private $pathIndex = [];

    /**
     * The last index of the path arrays.
     *
     * @var int
     */
    private $pathLength = 0;

    /**
     * Stack of the data structure so far encountered.
     * Note that this will only contain data of interest.
     *
     * @var array
     */
    private $stack = [];

    /**
     * The 'current' label.
     *
     * @var string|null
     */
    private $label;

    /**
     * The start level at which data is needed for matching.  When an expression
     * matches the current path this is set to pathLength.  When reducing the
     * level of the data it will be reset then pathLength < levelInterest.
     *
     * @var int
     */
    private $levelInterest = PHP_INT_MAX;

    /**
     * Flag set when endDocument is called.
     *
     * @var bool
     */
    private $complete = false;

    /**
     * Flag to indicate if an object has been encountered.
     * Only really used when subsequent values are encountered in an array to
     * ensure the array index is incremented.
     *
     * @var bool
     */
    private $objectEncountered = false;

    /**
     * @param array $dataMatch - [regex => closure()] format
     */
    public function __construct(array $dataMatch = [])
    {
        $this->setMatch($dataMatch);
    }

    /**
     * Sets the combination of regex and closures to be processed
     * against the JSON input.
     * Sent as [regex => closure()] format.
     */
    public function setMatch(array $dataMatch): void
    {
        $this->dataMatch = $dataMatch;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonStreamingParser\Listener\ListenerInterface::startObject()
     *
     * @param int $index - set to 0 for arrays (used for index number)
     */
    public function startObject($index = self::OBJECT): void
    {
        // Does the hierarchy need updating?
        if ($index !== self::OBJECT
            || $this->pathLength !== 0
            || !empty($this->label)
        ) {
            $this->path[] = $this->label ?? '';
            $this->pathIndex[] = $index;
            $this->pathLength++;
        }

        $this->stack[] = [];
        $this->label = null;
        // If not already interested in the data, check if it is now.
        if ($this->levelInterest === PHP_INT_MAX) {
            $path = $this->getPath();
            foreach (array_keys($this->dataMatch) as $pathReg) {
                // Check if matches regex, if this is an array, then check
                // if matches with regex/0 when looking at first element.
                if (preg_match('#^'.$pathReg.'$#', $path)
                    || (preg_match('#^'.$pathReg.'/0$#', $path)
                        && $this->pathIndex[$this->pathLength - 1] === 0
                    )
                ) {
                    $this->levelInterest = $this->pathLength;
                }
            }
        }

        // Set object flag (Only when processing objects and not arrays).
        $this->objectEncountered = ($index === self::OBJECT);
    }

    public function endObject(): void
    {
        $obj = array_pop($this->stack);
        if ($this->pathLength > 0) {
            $this->label = array_pop($this->path);
            $this->value($obj);
            array_pop($this->pathIndex);
            $this->pathLength--;
        } else {
            $this->value($obj);
        }
        // Update array index if neccessary.
        if ($this->pathLength > 0
            && $this->pathIndex[$this->pathLength - 1] !== self::OBJECT
        ) {
            $this->pathIndex[$this->pathLength - 1]++;
        }
    }

    public function startArray(): void
    {
        $this->startObject(self::ARRAY);
    }

    public function endArray(): void
    {
        $this->endObject();
    }

    public function whitespace(string $whitespace): void
    {
    }

    public function startDocument(): void
    {
        $this->complete = false;
    }

    public function endDocument(): void
    {
        $this->complete = true;
    }

    public function value($value): void
    {
        $obj = array_pop($this->stack);
        $path = $this->getPath();

        // Check the path against the required matches
        foreach ($this->dataMatch as $pathReg => $closure) {
            $matches = [];
            if (preg_match('#^'.$pathReg.'$#', $path, $matches)) {
                $closure($value, $matches[1] ?? []);
            }
        }

        if ($this->label !== null) {
            $obj[$this->label] = $value;
            $this->label = null;
        } else {
            $obj[] = $value;
        }

        // Check if need to store the data
        if ($this->levelInterest <= $this->pathLength) {
            $this->stack[] = $obj;
        } else {
            $this->levelInterest = PHP_INT_MAX;
        }

        // Update array index if neccessary.
        if ($this->objectEncountered === false
            && $this->pathLength > 0
            && $this->pathIndex[$this->pathLength - 1] !== self::OBJECT
        ) {
            $this->pathIndex[$this->pathLength - 1]++;
        }
        $this->objectEncountered = false;
    }

    public function key(string $key): void
    {
        $this->label = $key;
    }

    /**
     * Returns the path at the current element.  This is made up
     * of both the path (including array indicies) to this point
     *  as well as the current label(if set).
     */
    private function getPath(): string
    {
        $path = '';
        foreach ($this->path as $key => $pathElement) {
            if (!empty($pathElement)) {
                $path .= '/'.$pathElement;
            }
            // Add in array index if present
            if ($this->pathIndex[$key] !== self::OBJECT) {
                $path .= '/'.$this->pathIndex[$key];
            }
        }
        // Add in current label if present
        if ($this->label) {
            $path .= '/'.$this->label;
        }

        return $path;
    }
}
