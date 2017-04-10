function g(x) {
  var y;
  x(y--
  );
	for (var i = 0; i < parseInt("10 )"); i++
  ) {
	  ++x;
  }
	for (var j = 0; j--
    ; j++
  ) {
	  x++
  }
	for (var j = 1; --j
    ; ++j
  ) {
	  ++x
  }
  (x++
    ) ? (x++
    ) : (x++
  );
  while (x++
  ) {
    x--;
  }
  for (x = (function() { return 10; })(); x--;
    x++
  ) {
    x--
      && y++
      , y++
    y = y++
      + 2
  }
  for (x = (function() { return 10; })(); --x;
    ++x
  ) {
    --x &&
      ++y,
      ++y
    y = 2 +
      ++y
  }
  if (x++
  ) {
    --x;
  } else if (x++
) {
    --x;
  }
  if (++x
  ) {
    x--;
  } else if (++x
) {
    x--;
  }
  return x;
}
g(function(x) {
  x++
  x--
  ++x
  ++x
  ++
    x

  while (1) {
    break
    continue
    xcontinue
      = 4
    return
  }
  var o = {
    w: x++
  }
  var p = {
    w:
      x++
  }
  var q = {
    w: ++x
  }
  var r = {
    w: ++
      x
  }
  var s = {
    w:
      ++
        x
  }
  var a = [
    x++
  ]
  var b = [
    ++
      x
  ]
});
var w
++w
var x
x = 2
x++
x++
  (y = 2)
x && ++
  (y)
++
  (y)
var v
v++
var z
z = 2
++z
try {
  ++z
  ++z
    (y = 2)
  z &&
    (y)++
  } catch (e) {} // z is not a function
z &&
  (y)++
(y)++
x++
{
  x++
}
++x; // TODO without `;`
{
  ++x
}
f(f(w++
));
f(f(++w
));
f(f(++
  w
));

// Same again, but with a space between the prefix/postfix and its operand
function h(x) {
  var y;
  x(y --
  );
	for (var i = 0; i < parseInt("10 )"); i ++
  ) {
	  ++ x;
  }
	for (var j = 0; j --
    ; j ++
  ) {
	  x ++
  }
	for (var j = 1; -- j
    ; ++ j
  ) {
	  ++ x
  }
  (x ++
    ) ? (x ++
    ) : (x ++
  );
  while (x ++
  ) {
    x --;
  }
  for (x = (function() { return 10; })(); x --;
    x ++
  ) {
    x --
      && y ++
      , y ++
    y = y ++
      + 2
  }
  for (x = (function() { return 10; })(); -- x;
    ++ x
  ) {
    -- x &&
      ++ y,
      ++ y
    y = 2 +
      ++ y
  }
  if (x ++
  ) {
    -- x;
  } else if (x ++
) {
    -- x;
  }
  if (++ x
  ) {
    x --;
  } else if (++ x
) {
    x --;
  }
  return x;
}
g(function(x) {
  x ++
  x --
  ++ x
  ++ x
  var o = {
    w: x ++
  }
  var p = {
    w:
      x ++
  }
  var q = {
    w: ++ x
  }
  var a = [
    x ++
  ]
});
var w
++ w
var x
x = 2
x ++
x ++
  (y = 2)
var v
v ++
var z
z = 2
++ z
try {
  ++ z
  ++ z
    (y = 2)
  z &&
    (y) ++
} catch (e) {} // z is not a function
z &&
  (y) ++
(y) ++
x ++
{
  x ++
}
++ x; // TODO without `;` - also `y = x` fails before `{`
{
  ++ x
}
f(f(w ++
));
f(f(++ w
));

/*! Issue #47 */
function issue47(found, context, i) {
    var elems, elem;
    if ( !found ) {
        for ( found = [], elems = context.childNodes || context;
            ( elem = elems[ i ] ) != null;
            i++
        ) {
            // ...
        }
    }
}

/*! Issue #49 */
function issue49(out, text, n) {
    out.push(text)
    ++n
}

/*! Pull #50 comment */
function pull50(x) {
  while(x)--x
  while(x)x--
  while(x)--
    x
  while(x)
    --x
  while(x)
    --
      x
  while(x)
    x--
  // with semicolon
  while(x)--x;
  while(x)x--;
  while(x)--
    x;
  while(x)
    --x;
  while(x)
    --
      x;
  while(x)
    x--;
  // with space
  while(x)-- x
  while(x)x --
  while(x)
    -- x
  while(x)
    x --
}

/*! do-while */
function doWhile(x) {
  do {
    x--
  } while (x--)
  x--
  do
    x--
  while (x--)
  x--
  do {
    --x
  } while (--x)
  --x
  do --
    x
  while (--x)
  --x
  // nested
  do
    while (
      x --
    )
      x --
  while (
    x --
  )
  x --
  do {
    while (
      x --
    )
      x --
    do
      x --
    while (
      x --
    )
    x --
  } while (x --)
  x --
  do
    while (
      --
        x
    )
      --
        x
  while (
    --
      x
  )
  --
    x
  do {
    while (
      --
        x
    )
      --
        x
    do
      --
        x
    while (
      --
        x
    )
    --
      x
  } while (
    --
      x
  )
  --
    x
}

/*! other keywords */
function keywords(x) {
  if (x)
    --
      x
  else
    ++
      x
  if (x) --
    x
  else ++
    x
  if (typeof ++
    x
      === 'string' ||
    typeof
      x ++
      === 'string'
  )
    throw ++
      x
  throw
    ++ x
  return ++
    x
  return
    ++
      x
}
