[![Build Status](https://travis-ci.org/Im0rtality/jsonstreamingparser.svg?branch=master)](https://travis-ci.org/Im0rtality/jsonstreamingparser)

Not invented here
=================

Code forked from https://github.com/salsify/jsonstreamingparser and some fixed added:

 - Removed file_position callback from listener (we did not need it and it gave significant performance boost)
 - PSR2

Features
--------
 - Stream based - low memory footprint does not grow with file size
 - Similar to [SAX parser](http://en.wikipedia.org/wiki/Simple_API_for_XML)

Known drawbacks
---------------
 - Performance is not as good as it should be (throughput - ~1MB per 9 secs, VirtualBox, Debian 7 on 4.3GHz CPU, single core)

Usage
-----

To use the `JsonStreamingParser` you just have to implement the `JsonStreamingParser\Listener` interface. You then pass your `Listener` into the parser. For example:

```php
$stream = fopen('doc.json', 'r');
$listener = new YourListener();
try {
  $parser = new Parser($stream, $listener);
  $parser->parse();
} catch (Exception $e) {
  fclose($stream);
  throw $e;
}
```

That's it! Your `Listener` will receive events from the streaming parser as it works.

License
-------

MIT License
