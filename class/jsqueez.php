<?php /*********************************************************************
 *
 *   Copyright : (C) 2006 Nicolas Grekas. All rights reserved.
 *   Email     : nicolas.grekas+patchwork@espci.org
 *   License   : http://www.gnu.org/licenses/gpl.txt GNU/GPL, see COPYING
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/


/*
*
* This class shrinks Javascript code
*
* Should work with most valid Javascript code,
* even when semi-colons are missing.
*
* Features:
* - Removes comments and white spaces.
* - Shortens every local vars, and specifically global vars, methods and
*   properties who begin with one or more "$" or with a single "_".
* - Shortens also local/global vars found in strings,
*   but only if they are prefixed as above.
* - Respects Microsoft's conditional comments.
*
* Notes:
* - Shortened names are choosen by considering closures,
*   variables frequency and single characters frequency.
* - If you use with/eval then be careful.
*
* Bonus:
* - Replaces new Array/Object by []/{}
* - Merges multiple consecutive "var" declarations with commas
* - Fix a bug in Safari's parser (http://forums.asp.net/thread/1585609.aspx)
* - Can replace optional semi-colons by line feeds,
*   thus facilitating output debugging.
* - Treats three semi-colons ;;; like single-line comments
*   (http://dean.edwards.name/packer/2/usage/#triple-semi-colon).
*
*/

class jsqueez
{
	/**
	 * Class constructor
	 *
	 * $specialVarRx defines the regular expression of special variables names
	 * for global vars, methods, properties and in string substitution.
	 *
	 * Example: $parser = new jsqueez;
	 */

	function jsqueez($specialVarRx = '(?:\$+[a-zA-Z_]|_[a-zA-Z0-9\$])[a-zA-Z0-9_\$]*')
	{
		$this->specialVarRx = $specialVarRx;
		$this->reserved = array_flip($this->reserved);
		$this->data = array();
		$this->charFreq = array_combine(range(0, 255), array_fill(0, 256, 0));
		$this->counter = 0;
	}


	/**
	 * Does the job.
	 *
	 * Set $singleLine to false if you want optional
	 * semi-colons to be replaced by line feeds.
	 *
	 * Example: $squeezed_js = $parser->squeeze($fat_js);
	 */

	function squeeze($code, $singleLine = true)
	{
		$code = trim($code);
		if ('' === $code) return '';

		if (false !== strpos($code, "\r")) $code = strtr(str_replace("\r\n", "\n", $code), "\r", "\n");

		list($code, $this->strings ) = $this->extractStrings( $code);
		list($code, $this->closures) = $this->extractClosures($code);

		$key = "//''\"\"#0'"; // This crap has a wonderful property: it can not happend in any valid javascript, even in strings
		$this->closures[$key] =& $code;

		$tree = array($key => array('parent' => false));
		$this->makeVars($code, $tree[$key]);
		$this->renameVars($tree[$key]);

		$code = substr($tree[$key]['code'], 1);
		$code = str_replace(array_keys($this->strings), array_values($this->strings), $code);

		if ($singleLine) $code = strtr($code, "\n", ';');

		return $code;
	}


	// Protected properties

	var $varRx = '(?:[a-zA-Z_\$])[a-zA-Z0-9_\$]*';
	var $reserved = array(
		'abstract','as','boolean','break','byte','case','catch','char','class',
		'const','continue','debugger','default','delete','do','double','else',
		'enum','export','extends','false','final','finally','float','for',
		'function','goto','if','implements','import','in','instanceof','int',
		'long','native','new','null','package','private','protected','public',
		'return','short','static','super','switch','synchronized','this',
		'throw','throws','transient','true','try','typeof','var','void',
		'while','with','yield','let',
	);


	// Protected methods

	function extractStrings($f)
	{
		if ($cc_on = false !== strpos($f, '@cc_on'))
		{
			// Protect conditional comments from being removed
			$f = str_replace('#', '##', $f);
			$f = preg_replace("'/\*@cc_on(?![\$\.a-zA-Z0-9_])'", '1#@cc_on', $f);
			$f = preg_replace( "'//@cc_on(?![\$\.a-zA-Z0-9_])([^\n]+)'", '2#@cc_on$1@#3', $f);
			$f = str_replace('@*/', '@#1', $f);
		}

		$code = array();
		$j = 0;

		$strings = array();
		$K = 0;

		$instr = false;

		// Extract strings, removes comments
		$len = strlen($f);
		for ($i = 0; $i < $len; ++$i)
		{
			if ($instr)
			{
				if ('//' == $instr)
				{
					if ("\n" == $f[$i])
					{
						$f[$i--] = ' ';
						$instr = false;
					}
				}
				else if ($f[$i] == $instr || ('/' == $f[$i] && "/'" == $instr))
				{
					if ('*' == $instr)
					{
						if ('/' == $f[$i+1])
						{
							++$i;
							$instr = false;
						}
					}
					else
					{
						if ("/'" == $instr) while (false !== strpos('gmi', $f[$i+1])) $s[] = $f[$i++];
						$instr = false;
						$s[] = $f[$i];
					}
				}
				else if ('*' == $instr) ;
				else if ('\\' == $f[$i])
				{
					if ("\n" == $f[$i+1]) ++$i;
					else
					{
						$s[] = $f[$i];
						++$i;
						$s[] = $f[$i];
					}
				}
				else $s[] = $f[$i];
			}
			else switch ($f[$i])
			{
			case ';':
				// Remove triple semi-colon
				if ($i>0 && ';' == $f[$i-1] && $i+1 < $len && ';' == $f[$i+1]) $f[$i] = $f[$i+1] = '/';
				else
				{
					$code[++$j] = $f[$i];
					break;
				}

			case '/':
				if ('*' == $f[$i+1])
				{
					++$i;
					$instr = '*';
				}
				else if ('/' == $f[$i+1])
				{
					++$i;
					$instr = '//';
				}
				else
				{
					$a = ' ' == $code[$j] ? $code[$j-1] : $code[$j];
					if (false !== strpos('-!%&;<=>~:^+|,(*?[{', $a)
						|| (false !== strpos('oenfd', $a)
						&& preg_match(
							"'(?<![\$\.a-zA-Z0-9_])(do|else|return|typeof|yield) ?$'",
							implode('', array_slice($code, -8))
						)))
					{
						$key = "//''\"\"" . $K++ . $instr = "/'";
						$code[++$j] = $key;
						isset($s) && ($s = implode('', $s)) && $cc_on && $this->restoreCc($s);
						$strings[$key] = array('/');
						$s =& $strings[$key];
					}
					else $code[++$j] = $f[$i];
				}

				break;

			case "'":
			case '"':
				if ($f[$i+1] == $f[$i]) $code[++$j] = $f[$i] . $f[++$i];
				else
				{
					$key = "//''\"\"" . $K++ . $instr = $f[$i];
					$code[++$j] = $key;
					isset($s) && ($s = implode('', $s)) && $cc_on && $this->restoreCc($s);
					$strings[$key] = array($instr);
					$s =& $strings[$key];
				}

				break;

			case "\n":
				if ($j > 5)
				{
					' ' == $code[$j] && --$j && array_pop($code);

					$code[++$j] =
						false !== strpos('kend', $code[$j-1])
							&& preg_match(
								"'(?<![\$\.a-zA-Z0-9_])(break|continue|return|yield)$'",
								implode('', array_slice($code, -9))
							)
						? ';' : ' ';

					break;
				}

			case "\t": $f[$i] = ' ';
			case ' ':
				if (!$j || ' ' == $code[$j]) break;

			default:
				$code[++$j] = $f[$i];
			}
		}

		isset($s) && ($s = implode('', $s)) && $cc_on && $this->restoreCc($s);
		unset($s);

		$code = implode('', $code);
		$cc_on && $this->restoreCc($code, false);

		// Remove unwanted spaces
		$code = str_replace('- -', '-#-', $code);
		$code = str_replace('+ +', '+#+', $code);
		$code = preg_replace("' ?([-!%&;<=>~:\\/\\^\\+\\|\\,\\(\\)\\*\\?\\[\\]\\{\\}]+) ?'", '$1', $code);
		$code = str_replace('-#-', '- -', $code);
		$code = str_replace('+#+', '+ +', $code);

		// Replace new Array/Object by []/{}
		false !== strpos($code, 'new Array' ) && $code = preg_replace( "'new Array(?:\(\)|([;\]\)\},:]))'", '[]$1', $code);
		false !== strpos($code, 'new Object') && $code = preg_replace("'new Object(?:\(\)|([;\]\)\},:]))'", '{}$1', $code);

		// Add missing semi-colons after curly braces
		$code = preg_replace("'\}(?![:,;\.\(\)\]\}]|(else|catch|finally|while)[^\$\.a-zA-Z0-9_])'", '};', $code);

		// Tag possible empty instruction for easy detection
		$code = preg_replace("'(?<![\$\.a-zA-Z0-9_])if\('"   , '1#(', $code);
		$code = preg_replace("'(?<![\$\.a-zA-Z0-9_])for\('"  , '2#(', $code);
		$code = preg_replace("'(?<![\$\.a-zA-Z0-9_])while\('", '3#(', $code);

		$forPool = array();
		$instrPool = array();
		$s = 0;

		$f = array();
		$j = -1;

		// Remove as much semi-colon as possible
		$len = strlen($code);
		for ($i = 0; $i < $len; ++$i)
		{
			switch ($code[$i])
			{
			case '(':
				if ($j>=0 && "\n" == $f[$j]) $f[$j] = ';';

				++$s;

				if ($i && '#' == $code[$i-1])
				{
					$instrPool[$s - 1] = 1;
					if ('2' == $code[$i-2]) $forPool[$s] = 1;
				}

				$f[++$j] = '(';
				break;

			case ']':
			case ')':
				if ($i+1 < $len && !isset($forPool[$s]) && !isset($instrPool[$s-1]) && preg_match("'[a-zA-Z0-9_\$]'", $code[$i+1]))
				{
					$f[$j] .= $code[$i];
					$f[++$j] = "\n";
				}
				else $f[++$j] = $code[$i];

				if (')' == $code[$i])
				{
					unset($forPool[$s]);
					--$s;
				}

				continue 2;

			case '}':
				if ("\n" == $f[$j]) $f[$j] = '}';
				else $f[++$j] = '}';
				break;

			case ';':
				if (isset($forPool[$s]) || isset($instrPool[$s])) $f[++$j] = ';';
				else if ($j>=0 && "\n" != $f[$j] && ';' != $f[$j]) $f[++$j] = "\n";

				break;

			case '#':
				switch ($f[$j])
				{
				case '1': $f[$j] = 'if';    break 2;
				case '2': $f[$j] = 'for';   break 2;
				case '3': $f[$j] = 'while'; break 2;
				}

			case '[';
				if ($j>=0 && "\n" == $f[$j]) $f[$j] = ';';

			default: $f[++$j] = $code[$i];
			}

			unset($instrPool[$s]);
		}

		$f = implode('', $f);
		$cc_on && $f = str_replace('@#3', "\n", $f);

		// Fix "else ;" empty instructions
		$f = preg_replace("'(?<![\$\.a-zA-Z0-9_])else\n'", 'else;', $f);

		if (false !== strpos($f, 'throw'))
		{
			// Fix for a bug in Safari's parser
			$f = preg_replace("'(?<![\$\.a-zA-Z0-9_])throw[^\$\.a-zA-Z0-9_][^;\}\n]*(?!;)'", '$0;', $f);
			$f = str_replace(";\n", ';', $f);
		}

		$r1 = array( // keywords with a direct object
			'case','delete','do','else','function','in','instanceof',
			'new','return','throw','typeof','var','void','yield','let',
		);

		$r2 = array( // keywords with a subject
			'in','instanceof',
		);

		// Fix missing semi-colons
		$f = preg_replace("'(?<!(?<![a-zA-Z0-9_\$])" . implode(')(?<!(?<![a-zA-Z0-9_\$])', $r1) . ") (?!(" . implode('|', $r2) . ")(?![a-zA-Z0-9_\$]))'", "\n", $f);
		$f = preg_replace("'(?<!(?<![a-zA-Z0-9_\$])do)(?<!(?<![a-zA-Z0-9_\$])else) if\('", "\nif(", $f);
		$f = preg_replace("'(?<=--|\+\+)(?<![a-zA-Z0-9_\$])(" . implode('|', $r1) . ")(?![a-zA-Z0-9_\$])'", "\n$1", $f);
		$f = preg_replace("'(?<![a-zA-Z0-9_\$])for\neach\('", 'for each(', $f);

		return array($f, $strings);
	}

	function extractClosures($code)
	{
		$code = ';' . $code;

		$f = preg_split("'(?<![a-zA-Z0-9_\$])(function[ \(].*?\{)'", $code, -1, PREG_SPLIT_DELIM_CAPTURE);
		$i = count($f) - 1;
		$closures = array();

		while ($i)
		{
			$c = 1;
			$j = 0;
			$l = strlen($f[$i]);

			while ($c && $j<$l)
			{
				$s = $f[$i][$j++];
				$c += '{' == $s ? 1 : ('}' == $s ? -1 : 0);
			}

			$key = "//''\"\"#$i'";
			$bracket = strpos($f[$i-1], '(', 8);
			$closures[$key] = substr($f[$i-1], $bracket) . substr($f[$i], 0, $j);
			$f[$i-2] .= substr($f[$i-1], 0, $bracket) . $key . substr($f[$i], $j);
			$i -= 2;
		}

		return array($f[0], $closures);
	}

	function makeVars($closure, &$tree)
	{
		$tree['code'] =& $closure;


		// Replace multiple "var" declarations by a single one
		$closure = preg_replace_callback("'(?<=[\n\{\}])var [^\n]+(?:\nvar [^\n]+)+'", array(&$this, 'mergeVarDeclarations'), $closure);


		// Get all local vars (functions, arguments and "var" prefixed)

		$tree['local'] = array();
		$vars =& $tree['local'];

		if (preg_match("'\((.+?)\)'", $closure, $v))
		{
			$v = explode(',', $v[1]);
			foreach ($v as $w) $vars[$w] = 0;
		}

		$v = preg_split("'(?<![\$\.a-zA-Z0-9_])var '", $closure);
		if ($i = count($v) - 1)
		{
			$w = array();

			while ($i)
			{
				$j = $c = 0;
				$l = strlen($v[$i]);

				while ($j < $l)
				{
					switch ($v[$i][$j])
					{
					case '(': case '[': case '{':
						++$c;
						break;

					case ')': case ']': case '}':
						if ($c-- <= 0) break 2;
						break;

					case ';': case "\n": case ' ':
						if (!$c) break 2;

					default:
						$c || $w[] = $v[$i][$j];
					}

					++$j;
				}

				$w[] = ',';
				--$i;
			}

			$v = explode(',', implode('', $w));
			foreach ($v as $w) if (preg_match("'^{$this->varRx}'", $w, $v)) $vars[$v[0]] = 0;
		}

		if (preg_match_all("@function ({$this->varRx})//''\"\"#@", $closure, $v))
		{
			foreach ($v[1] as $w) $vars[$w] = 0;
		}


		// Get all used vars, local and non-local

		$tree['used'] = array();
		$vars =& $tree['used'];

		if (preg_match_all("#\.?(?<![a-zA-Z0-9_\$]){$this->varRx}#", $closure, $w))
		{
			foreach ($w[0] as $k) isset($vars[$k]) ? ++$vars[$k] : $vars[$k] = 1;
		}

		if (preg_match_all("#//''\"\"[0-9]+['\"]#", $closure, $w)) foreach ($w[0] as $a)
		{
			$v = preg_split("#(\.?(?<![a-zA-Z0-9_\$]){$this->specialVarRx})#", $this->strings[$a], -1, PREG_SPLIT_DELIM_CAPTURE);
			$a = count($v);
			for ($i = 0; $i < $a; ++$i)
			{
				$k = $v[$i];

				if ($i%2)
				{
					$w =& $tree;

					while (isset($w['parent']) && !(isset($w['used'][$k]) || isset($w['local'][$k]))) $w =& $w['parent'];

					(isset($w['used'][$k]) || isset($w['local'][$k])) && (isset($vars[$k]) ? ++$vars[$k] : $vars[$k] = 1);

					unset($w);
				}

				if (0 == $i%2 || !isset($vars[$k])) foreach (count_chars($v[$i], 1) as $k => $w) $this->charFreq[$k] += $w;
			}
		}


		// Propagate the usage number to parents

		foreach ($vars as $w => $a)
		{
			$k =& $tree;
			$chain = array();
			do
			{
				$vars =& $k['local'];
				$chain[] =& $k;
				if (isset($vars[$w]))
				{
					unset($k['used'][$w]);
					if (isset($vars[$w])) $vars[$w] += $a;
					else $vars[$w] = $a;
					$a = false;
					break;
				}
			}
			while ($k['parent'] && $k =& $k['parent']);

			if ($a && !$k['parent'])
			{
				if (isset($vars[$w])) $vars[$w] += $a;
				else $vars[$w] = $a;
			}

			if (isset($tree['used'][$w]) && isset($vars[$w])) foreach ($chain as &$b)
			{
				isset($b['local'][$w]) || $b['used'][$w] =& $vars[$w];
			}
		}


		// Analyse childs

		$tree['childs'] = array();
		$vars =& $tree['childs'];

		if (preg_match_all("@//''\"\"#[0-9]+'@", $closure, $w))
		{
			foreach ($w[0] as $a)
			{
				$vars[$a] = array('parent' => &$tree);
				$this->makeVars($this->closures[$a], $vars[$a]);
			}
		}
	}

	function mergeVarDeclarations($m)
	{
		return str_replace("\nvar ", ',', $m[0]);
	}

	function renameVars(&$tree, $base = true)
	{
		$this->counter = -1;

		if ($base)
		{
			$tree['local'] += $tree['used'];
			$tree['used'] = array();

			foreach ($tree['local'] as $k => $v)
			{
				if ('.' == $k[0]) $k = substr($k, 1);

				if (!preg_match("#^{$this->specialVarRx}$#", $k))
				{
					foreach (count_chars($k, 1) as $k => $w) $this->charFreq[$k] += $w * $v;
				}
				else if (2 == strlen($k)) $tree['used'][] = $k[1];
			}

			arsort($this->charFreq);

			$this->str0 = '';
			$this->str1 = '';

			foreach ($this->charFreq as $k => $v)
			{
				if (!$v) break;

				$v = chr($k);

				if ((64 < $k && $k < 91) || (96 < $k && $k < 123))
				{
					$this->str0 .= $v;
					$this->str1 .= $v;
				}
				else if (47 < $k && $k < 58)
				{
					$this->str1 .= $v;
				}
			}

			foreach (array_keys($tree['local']) as $var)
			{
				if ('.' != substr($var, 0, 1) && isset($tree['local'][".{$var}"])) $tree['local'][$var] += $tree['local'][".{$var}"];
			}

			foreach (array_keys($tree['local']) as $var)
			{
				if ('.' == substr($var, 0, 1) && isset($tree['local'][substr($var, 1)])) $tree['local'][$var] = $tree['local'][substr($var, 1)];
			}

			arsort($tree['local']);

			foreach (array_keys($tree['local']) as $var) switch (substr($var, 0, 1))
			{
			case '.':
				if (!isset($tree['local'][substr($var, 1)]))
				{
					$tree['local'][$var] = '#' . (3 < strlen($var) && preg_match("'^\.{$this->specialVarRx}$'", $var) ? '$' . $this->getNextName($tree['used']) : substr($var, 1));
				}
				break;

			case '#': break;

			default:
				$base = 2 < strlen($var) && preg_match("'^{$this->specialVarRx}$'", $var) ? '$' . $this->getNextName($tree['used']) : $var;
				$tree['local'][$var] = $base;
				if (isset($tree['local'][".{$var}"])) $tree['local'][".{$var}"] = '#' . $base;
			}

			foreach (array_keys($tree['local']) as $var) $tree['local'][$var] = preg_replace("'^#'", '.', $tree['local'][$var]);
		}
		else
		{
			arsort($tree['local']);

			foreach (array_keys($tree['local']) as $var)
			{
				$tree['local'][$var] = $this->getNextName($tree['used']);
			}
		}

		$this->local_tree =& $tree['local'];
		$this->used_tree  =& $tree['used'];

		$tree['code'] = preg_replace_callback("#\.?(?<![a-zA-Z0-9_\$]){$this->varRx}#", array(&$this, 'getNewName'), $tree['code']);
		$tree['code'] = preg_replace_callback("#//''\"\"[0-9]+['\"]#", array(&$this, 'renameInString'), $tree['code']);

		foreach ($tree['childs'] as $a => &$b)
		{
			$this->renameVars($b, false);
			$tree['code'] = str_replace($a, $b['code'], $tree['code']);
			unset($tree['childs'][$a]);
		}
	}

	function renameInString($a)
	{
		$b =& $this->strings[$a[0]];
		unset($this->strings[$a[0]]);

		return preg_replace_callback(
			"#\.?(?<![a-zA-Z0-9_\$]){$this->specialVarRx}#",
			array(&$this, 'getNewName'),
			$b
		);
	}

	function getNewName($m)
	{
		$m = $m[0];

		return isset($this->reserved[$m])
			? $m
			: (
				  isset($this->local_tree[$m])
				? $this->local_tree[$m]
				: (
					  isset($this->used_tree[$m])
					? $this->used_tree[$m]
					: $m
				)
			);
	}

	function getNextName(&$exclude = array(), $recursive = false)
	{
		++$this->counter;

		if (!$recursive)
		{
			$recursive = $exclude;
			$exclude =& $recursive;
			$exclude = array_flip($exclude);
		}

		$len0 = strlen($this->str0);
		$len1 = strlen($this->str0);

		$name = $this->str0[$this->counter % $len0];

		$i = intval($this->counter / $len0) - 1;
		while ($i>=0)
		{
			$name .= $this->str1[ $i % $len1 ];
			$i = intval($i / $len1) - 1;
		}

		return !(isset($this->reserved[$name]) || isset($exclude[$name])) ? $name : $this->getNextName($exclude, true);
	}

	function restoreCc(&$s, $lf = true)
	{
		$lf && $s = str_replace('@#3', '', $s);

		$s = str_replace('@#1', '@*/', $s);
		$s = str_replace('2#@cc_on', '//@cc_on', $s);
		$s = str_replace('1#@cc_on', '/*@cc_on', $s);
		$s = str_replace('##', '#', $s);
	}
}
