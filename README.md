JSqueeze: Efficient JavaScript minification in PHP
==================================================

JSqueeze shrinks / compresses / minifies / mangles Javascript code.

It's a single PHP class licensed under Apache 2 and GPLv2 that is beeing
developed, maintained and thouroughly tested since 2003 on major JavaScript
frameworks (e.g. jQuery).

JSqueeze operates on any parse error free JavaScript code, even when semi-colons
are missing.

In term of compression ratio, it compares to YUI Compressor and UglifyJS.

Features
--------

* Removes comments and white spaces.
* Renames every local vars, typically to a single character.
* Keep Microsoft's conditional comments.
* In order to maximise later HTTP compression (deflate, gzip), new variables
  names are choosen by considering closures, variables' frequency and
  characters' frequency.
* Renames also global vars, methods and properties, but only if they are marked
  special by some naming convention. By default, special var names begin with
  one or more `$`, or with a single `_`.
* Renames also local/global vars found in strings, but only if they are marked
  special.
* If you use `with/eval` then be careful.

Bonus
-----

* Replaces `false/true` by `!1/!0`
* Replaces `new Array/Object` by `[]/{}`
* Merges consecutive `var` declarations with commas
* Merges consecutive concatened strings
* Fix [a bug in Safari's parser](http://forums.asp.net/thread/1585609.aspx)
* Can replace optional semi-colons by line feeds, thus facilitating output
  debugging.
* Keep important comments marked with `/*!...`
* Treats three semi-colons `;;;` [like single-line comments](http://dean.edwards.name/packer/2/usage/#triple-semi-colon).
* Fix special catch scope across browsers
* Work around buggy-handling of named function expressions in IE<=8

To do?
------

* foo['bar'] => foo.bar
* {'foo':'bar'} => {foo:'bar'}
* Dead code removal (never used function)
* Munge primitives: var WINDOW=window, etc.
