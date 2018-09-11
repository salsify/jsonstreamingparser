<?php
namespace JsonStreamingParser\Listener;

use JsonStreamingParser\Listener;

/**
 * This listener is for parsing very simple JSON files that contain an array of objects.
 * For example [{"id":"1", "name":"foo"}, {"id","2","name":"bar"}]
 * This can be useful for seeding databases or importing massive amounts of data.
 * Please do note that is intended for single level simple objects. 
 * To support nested objects you need to modify the code as suited to your purposes.
 */
class SimpleObjectQueueListneer implements Listener
{
    const TYPE_ARRAY = 1;
    const TYPE_OBJECT = 2;
    
    /** 
     * @var $currentObject array will hold the current object being parsed as an associative array.
     */
    protected $currentObject = [];
    
    /**
     * @var $currentKey string will hold the current key used to feed $currentObject
     */ 
    protected $currentKey = null;

    /**
     * @var Callable this will be called to perform an action on the compiled object.
     */
    protected $callback;
    
    /**
     * @var integer which return type to provide.
     */
    protected $return_type;
    
    /**
     * Initiate the listener for very simple objects that do not contain nested elements.
     * For example [{"id":"1", "name":"foo"}, {"id","2","name":"bar"}]
     * @param Callable $callback
     * @param return type to callback. Defaults to to associative array.<BR/>
     *     SimpleObjectQueueListneer::TYPE_ARRAY will provide an associative array to the callback<BR/>
     *     SimpleObjectQueueListneer::TYPE_OBJECT will privde an object to the callback
     */
    public function __construct($callback = null, $return_type = 1)
    {
        $this->callback = $callback;
    }

    public function startDocument()
    {
        $this->reset();
    }
    
    public function endDocument()
    {
        $this->reset();
    }

    public function startObject()
    {
        $this->reset();
    }

    public function endObject()
    {
        /**
         * Return the currently compiled object to the callback.
         */
        if($this->return_type === self::TYPE_ARRAY) {
            call_user_func($this->callback, $this->currentObject);   
        }
        elseif($this->return_type === self::TYPE_OBJECT) {
            call_user_func($this->callback, (object)$this->currentObject);   
        }
        else {
            throw new Exception("Unsupported callback data type requested.");
        }
    }

    public function startArray()
    {
        /** we support an array of objects, not nested arrays. leave this alone **/
    }

    public function endArray()
    {
        /** no need to support arrays **/
    }

    /**
     * @param string $key
     */
    public function key($key)
    {
        $this->currentKey = $key;
    }

    /**
     * Value may be a string, integer, boolean, null
     * @param mixed $value
     */
    public function value($value)
    {
        $this->currentObject[$this->currentKey] = $value;
    }

    public function whitespace($whitespace)
    {
        // do nothing
    }
    
    /**
     * Reset all the values to default
     */
    protected function reset() 
    {
        $this->currentObject = [];
        $this->currentKey = null;
    }
}
