Streaming JSONL parser for PHP
=============================

This is a fork of [Salsify's JSON parser](https://github.com/salsify/jsonstreamingparser) - to enable the parsing of [jsonl](http://jsonlines.org/) files (Line Delimited JSON) and also json files where there is no root array element.

See [Salsify's original repo](https://github.com/salsify/jsonstreamingparser) for more info and usage.

To make it work with no root element json files, i just started the parser stack at `0`: `$this->stack = [0]` instead of `$this->stack = []`

To make it work with jsonl I added an extra if statement to pick up `{`s, see: [the commit here](https://github.com/jamestowers/jsonstreamingparser/commit/c5fa56786aed9bc168d72a41b49f911f57c19818)