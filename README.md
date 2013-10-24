[![Build Status](https://travis-ci.org/Magomogo/jsonstreamingparser.png?branch=master)](https://travis-ci.org/Magomogo/jsonstreamingparser)

php-streaming-json-parser
=========================

This is a simple, streaming parser for processing large JSON documents. Use it for parsing very large JSON documents to avoid loading the entire thing into memory, which is how just about every other JSON parser for PHP works.

For more details, I've written up a longer explanation of the [JSON streaming parser](http://www.salsify.com/blog/json-streaming-parser-for-php/1056) that talks about pros and cons vs. the standard PHP JSON parser.

If you've ever used a [SAX parser](http://en.wikipedia.org/wiki/Simple_API_for_XML) for XML (or even JSON) in another language, that's what this is. Except for JSON in PHP.


Usage
-----

To use the `JsonStreamingParser` you just have to implement the `\JsonStreamingParser\Listener` interface. You then pass your `Listener` into the parser. For example:

```php
$stream = fopen('doc.json', 'r');
$listener = new YourListener();
try {
  $parser = new JsonStreamingParser_Parser($stream, $listener);
  $parser->parse();
} catch (Exception $e) {
  fclose($stream);
  throw $e;
}
```

That's it! Your `Listener` will receive events from the streaming parser as it works.

There is a complete example of this in `example/example.php`.


License
-------

[MIT License](http://mit-license.org/) (c) Salsify, Inc.
