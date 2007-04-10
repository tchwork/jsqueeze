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
* This class obfuscates javascript code
*
* Removes comments and white chars,
* Shorten every local vars, and global vars/methods/properties
* who begins with one or more "$" or a single "_".
* Shorten also local/global vars found in strings,
* but only if they are prefixed as above.
* If you use eval() then be careful.
*
* Works with most valid Javascript code.
* Tolerates some missing semi-colons.
* Respects Microsoft's conditional comments.
* Three semi-colons (;;;) are treated like single-line comments.
*/

class jsqueez
{
	function jsqueez()
	{
		$this->reserved = array(
			'abstract','boolean','break','byte',
			'case','catch','char','class',
			'const','continue','default','delete',
			'do','double','else','export',
			'extends','false','final','finally',
			'float','for','function','goto',
			'if','implements','in','instanceof',
			'int','long','native','new',
			'null','package','private','protected',
			'public','return','short','static',
			'super','switch','synchronized','this',
			'throw','throws','transient','true',
			'try','typeof','var','void',
			'while','with',
		);

		$this->reserved = array_flip($this->reserved);
		$this->data = array();
		$this->counter = 0;
		$this->varRx = '(?<![a-zA-Z0-9_\$])(?:[a-zA-Z_\$])[a-zA-Z0-9_\$]*';
		$this->specialVarRx = '(?<![a-zA-Z0-9_\$])(?:\$+[a-zA-Z_]|_[a-zA-Z0-9\$])[a-zA-Z0-9_\$]*';
	}

	function addJs($code) {$this->data[] =& $code;}

	function get()
	{
		$code = implode(";\n", $this->data);

		if (false !== strpos($code, "\r")) $code = strtr(str_replace("\r\n", "\n", $code), "\r", "\n");

		list($code, $this->strings ) = $this->extractStrings( $code);
		list($code, $this->closures) = $this->extractClosures($code);

		$key = "//''\"\"#0'";
		$this->closures[$key] =& $code;

		$tree = array($key => array('parent' => false));
		$this->makeVars($code, $tree[$key]);
		$this->renameVars($tree[$key]);

		$code = substr($tree[$key]['code'], 1);
		$code = str_replace(array_keys($this->strings), array_values($this->strings), $code);

		return $code;
	}

	function extractStrings($f)
	{
		$f = trim($f);

		if ('' === $f) return array('', array());

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
				else if ($f[$i] == $instr)
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
						if ('/' == $instr) while (false !== strpos('gmi', $f[$i+1])) $s[] = $f[$i++];
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
				// Remove triple semi-colon (see http://dean.edwards.name/packer/2/usage/#triple-semi-colon)
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
					if (false !== strpos('-!%&;<=>~:^+|,(*?[{n', $a))
					{
						$key = "//''\"\"" . $K++ . $instr = $f[$i];
						$code[++$j] = $key;
						isset($s) && ($s = implode('', $s)) && $cc_on && $this->restoreCc($s);
						$strings[$key] = array($instr);
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

			case ')':
				if ($i+1 < $len && !isset($forPool[$s]) && !isset($instrPool[$s-1]) && preg_match("'[a-zA-Z0-9_\$]'", $code[$i+1]))
				{
					$f[$j] .= ')';
					$f[++$j] = "\n";
				}
				else $f[++$j] = ')';

				unset($forPool[$s]);
				--$s;

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

		// Optimize "i++" to "++i" in "for" loops
		$f = preg_replace("';([^\;\)\n]+)(\+\+|--)\)'", ';$2$1)', $f);

		if (false !== strpos($f, 'throw'))
		{
			// Fix for a bug in Safari's parser (see http://forums.asp.net/thread/1585609.aspx)
			$f = preg_replace("'(?<![\$\.a-zA-Z0-9_])throw[^\$\.a-zA-Z0-9_][^;\}\n]*(?!;)'", '$0;', $f);
			$f = str_replace(";\n", ';', $f);
		}

		// Fix some missing semi-colon
		$rx = '(?<!(?<![a-zA-Z0-9_\$])' . implode(')(?<!(?<![a-zA-Z0-9_\$])', array_keys($this->reserved)) . ')';
		$f = preg_replace("'{$rx} (?!(" . implode('|', array_keys($this->reserved)) . ") )'", "\n", $f);

		// Replace multiple "var" declarations by a single one
		$f = preg_replace_callback("'(?:\nvar [^\n]+){2,}'", array(&$this, 'mergeVarDeclarations'), $f);

		return array($f, $strings);
	}

	function mergeVarDeclarations($m)
	{
		return "\nvar " . str_replace("\nvar ", ',', substr($m[0], 5));
	}

	function extractClosures($code)
	{
		$code = ';' . $code;

		$f = preg_split("'(?<![\$\.a-zA-Z0-9_])(function[ \(].*?\{)'", $code, -1, PREG_SPLIT_DELIM_CAPTURE);
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


		// Get all local vars (functions, arguments and "var" prefixed)

		$tree['local'] = array();
		$vars =& $tree['local'];

		if (preg_match("'\((.*?)\)'", $closure, $v))
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
						if (!$c--) break 2;
						break;

					case ';': case "\n":
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

		if (preg_match_all("#\.?{$this->varRx}#", $closure, $w))
		{
			foreach ($w[0] as $k) if (!isset($this->reserved[$k])) isset($vars[$k]) ? ++$vars[$k] : $vars[$k] = 1;
		}

		if (preg_match_all("#//''\"\"[0-9]+['\"]#", $closure, $w)) foreach ($w[0] as $a)
		{
			if (preg_match_all("#\.?{$this->specialVarRx}#", $this->strings[$a], $w))
			{
				foreach ($w[0] as $k)
				{
					$w =& $tree;

					while (isset($w['parent']) && !(isset($w['used'][$k]) || isset($w['local'][$k]))) $w =& $w['parent'];

					(isset($w['used'][$k]) || isset($w['local'][$k])) && (isset($vars[$k]) ? ++$vars[$k] : $vars[$k] = 1);
				}

				unset($w);
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

	function renameVars(&$tree, $base = true)
	{
		$this->counter = -1;

		if ($base)
		{
			$tree['local'] += $tree['used'];
			$tree['used'] = array();

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
					$tree['local'][$var] = '#' . (preg_match("'^\.{$this->specialVarRx}$'", $var) ? '$' . $this->getNextName() : substr($var, 1));
				}
				break;

			case '#': break;

			default:
				$base = preg_match("'^{$this->specialVarRx}$'", $var) ? '$' . $this->getNextName() : $var;
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

		$tree['code'] = preg_replace_callback("#\.?{$this->varRx}#", array(&$this, 'getNewName'), $tree['code']);
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
			"#\.?{$this->specialVarRx}#",
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

		$str0 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$len0 = strlen($str0);

		$str1 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$len1 = strlen($str1);

		$name = $str0[$this->counter % $len0];

		$i = intval($this->counter / $len0) - 1;
		while ($i>=0)
		{
			$name .= $str1[ $i % $len1 ];
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
