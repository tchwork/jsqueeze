<?php

/*
* This class obfuscates javascript code
*
* Every var whose name/method begins with a "$" will be replaced by a shorter one, typicaly a single letter.
* Comments will be removed
* White chars will be stripped
*
* Works with any valid JavaScript code as long as all semi-colons are there, except for code like (i++ + 2) which will become (i+++2)
*/

class jsquiz
{
	function __construct() // rename to "jsquiz" for PHP4 compatibility
	{
		$this->known = array();
		$this->data = array();
		$this->counter = 0;
		$this->varRx = '(?<![a-zA-Z0-9_])\\$[a-zA-Z_][a-zA-Z0-9_]*';
	}

	function addJs($code) {$this->data[] =& $code;}

	function get()
	{
		$code = str_replace(
			array("\r\n", "\r"),
			array("\n"  , "\n"),
			implode("\n", $this->data)
		);

		list($code, $this->strings) = $this->extractStrings($code);

		list($code, $this->closures) = $this->extractClosures($code);

		$key = "//''\"\"f0'";
		$this->closures[$key] =& $code;

		$tree = array($key => array('parent' => false));
		$this->makeVars($code, $tree[$key]);

		$this->renameVars($tree[$key]);

		return substr($tree[$key]['code'], 1);
	}

	function extractStrings($f)
	{
		$code = '';
		$strings = array();
		$K = 0;

		$instr = false;

		$len = strlen($f);
		for ($i=0; $i<$len; $i++)
		{
			if ($instr)
			{
				if ($instr=='//')
				{
					if ($f[$i]=="\n") $instr = false;
				}
				else if ($f[$i]==$instr)
				{
					if ($instr=='*')
					{
						if ($f[$i+1]=='/')
						{
							$i++;
							$instr = false;
						}
					}
					else
					{
						if ($instr == '/') while (strpos('gmi', $f[$i+1])!==false) $s .= $f[$i++];
						$instr = false;
						$s .= $f[$i];
					}
				}
				else if ($instr=='*') ;
				else if ($f[$i]=='\\')
				{
					if ($f[$i+1]=="\n") $i++;
					else
					{
						$s .= $f[$i];
						$i++;
						$s .= $f[$i];
					}
				}
				else $s .= $f[$i];
			}
			else switch ($f[$i])
			{
				case '/':
					if ($f[$i+1]=='*')
					{
						$i++;
						$instr = '*';
					}
					else if ($f[$i+1]=='/')
					{
						$i++;
						$instr = '//';
					}
					else
					{
						$a = substr(trim($code), -1);
						if (strpos('-!%&;<=>~:^+|,(*?[{n', $a)!==false)
						{
							$instr = $f[$i];
							$key = "//''\"\"" . $K . $instr;
							$strings[$key] = $instr;
							$code .= $key;
							$s =& $strings[$key];
							$K++;
						}
						else $code .= $f[$i];
					}
					break;
				case "'":
				case '"':
					$instr = $f[$i];
					$key = "//''\"\"" . $K . $instr;
					$strings[$key] = $instr;
					$code .= $key;
					$s =& $strings[$key];
					$K++;
					break;
				default:
					$code .= $f[$i];
			}
		}

		$code = str_replace("\n", '', $code);
		$code = preg_replace("'\s+'u", ' ', $code);
		$code = preg_replace("' ?([-!%&;<=>~:\\/\\^\\+\\|\\,\\(\\)\\*\\?\\[\\]\\{\\}]+) ?'u", '$1', $code);
		$code = preg_replace("'\}([^:,;\]\}\)]|$)'u", '};$1', $code);
		$code = preg_replace("'\};(else|catch|finally|while)'", '}$1', $code);
		$code = preg_replace("';{2,}'u", ';', $code);
		$code = str_replace(';}', '}', $code);
		$code = str_replace(';', ";\n", $code); // This prevents IE from bugging, and is VERY usefull for debugging !

		return array($code, $strings);
	}

	function extractClosures($code)
	{
		$code = ';' . $code;

		$this->known = preg_match_all("'\.([a-z_][a-z0-9_\\$]*)'iu", $code, $i) ? $i[1] : array();

		$f = preg_split("'([^\\$\.a-zA-Z0-9_]function[ \(].*?\{)'u", $code, -1, PREG_SPLIT_DELIM_CAPTURE);
		$i = count($f)-1;
		$closures = array();

		while ($i)
		{
			$c = 1;
			$j = 0;
			$l = strlen($f[$i]);
			$fK = "//''\"\"f$i'";
			$fS = $f[$i-1];
			while ($c && $j<$l)
			{
				$fS .= $s = $f[$i][$j];
				$c += $s=='{' ? 1 : ($s=='}' ? -1 : 0);
				$j++;
			}

			$closures[$fK] = $fS;

			$f[$i-2] .= $fK . substr($f[$i], $j);
			$i -= 2;
		}

		if (preg_match_all("'[^a-z0-9_\\$\"]([a-z_][a-z0-9_\\$]*)'iu", $f[0], $i)) $this->known = array_merge($this->known, $i[1]);

		$this->known = array_flip($this->known);

		return array($f[0], $closures);
	}

	function makeVars($closure, &$tree)
	{
		$tree['code'] = $closure;

		# Get all local vars (arguments and "var" prefixed)
		$tree['local'] = $this->getVars($closure);

		# Get all used vars, local and non-local
		$tree['used'] = array();
		$a = preg_replace("'^.function {$this->varRx}\('u", '', $closure);
		$a = $this->replace_keys_by_values($this->strings, $a);
		if (preg_match_all("#\.?{$this->varRx}#u", $a, $w))
		{
			foreach ($w[0] as $k) @$tree['used'][$k]++;
		}

		if (preg_match_all("#//''\"\"f\d+'#u", $closure, $w))
		{
			foreach ($w[0] as $a)
			{
				if (preg_match("'^.function ({$this->varRx})\('u", $this->closures[$a], $w))
				{
					$tree['local'][$w[1]] = 0;
					@$tree['used'][$w[1]]++;
				}
			}
		}

		# Propagate the usage number to parents
		foreach ($tree['used'] as $w => $a)
		{
			$k =& $tree;
			$chain = array();
			do
			{
				$chain[] =& $k;
				if (isset($k['local'][$w]))
				{
					unset($k['used'][$w]);
					@$k['local'][$w] += $a;
					$a = false;
					break;
				}
			} while ($k['parent'] && $k =& $k['parent']);

			if ($a && !$k['parent']) @$k['local'][$w] += $a;

			if (isset($tree['used'][$w]) && isset($k['local'][$w])) foreach ($chain as $a => $b)
			{
				if (!isset($chain[$a]['local'][$w])) $chain[$a]['used'][$w] =& $k['local'][$w];
			}
		}

		# Analyse childs
		$tree['childs'] = array();
		if (preg_match_all("#//''\"\"f\d+'#u", $closure, $w))
		{
			foreach ($w[0] as $a)
			{
				$tree['childs'][$a] = array('parent' => &$tree);
				$this->makeVars($this->closures[$a], $tree['childs'][$a]);
			}
		}
	}

	function renameVars(&$tree, $home = true)
	{
		$this->_getNextName(true);

		if ($home)
		{
			foreach (array_keys($tree['local']) as $var)
			{
				if ('.' != substr($var, 0, 1) && isset($tree['local'][".{$var}"])) $tree['local'][$var] += $tree['local'][".{$var}"];
			}

			foreach (array_keys($tree['local']) as $var)
			{
				if ('.' == substr($var, 0, 1) && isset($tree['local'][substr($var, 1)])) $tree['local'][$var] = $tree['local'][substr($var, 1)];
			}

			arsort($tree['local']);

			foreach (array_keys($tree['local']) as $var)
			{
				switch (substr($var, 0, 1))
				{
					case '.':
						if (!isset($tree['local'][substr($var, 1)]))
						{
							$tree['local'][$var] = '#' . $this->_getNextName(array_flip($tree['used']));
						}
						break;

					case '#': break;

					default:
						$home = $this->_getNextName(array_flip($tree['used']));
						$tree['local'][$var] = $home;
						if (isset($tree['local'][".{$var}"])) $tree['local'][".{$var}"] = '#' . $home;
				}
			}

			foreach (array_keys($tree['local']) as $var) $tree['local'][$var] = preg_replace("'^#'u", '.', $tree['local'][$var]);
		}
		else
		{
			arsort($tree['local']);

			foreach (array_keys($tree['local']) as $var)
			{
				$tree['local'][$var] = $this->_getNextName(array_flip($tree['used']));
			}
		}

		foreach (array_keys($tree['childs']) as $var)
		{
			$this->renameVars($tree['childs'][$var], false);
			$tree['code'] = str_replace($var, $tree['childs'][$var]['code'], $tree['code']);
		}

		$tree['code'] = $this->replace_keys_by_values($this->strings, $tree['code']);
		$tree['code'] = preg_replace("#\.?{$this->varRx}#eu", 'isset($tree["local"][\'$0\']) ? $tree["local"][\'$0\'] : \'$0\'', $tree['code']);
	}

	function getVars($closure)
	{
		$vars = array();

		if (preg_match("'\((.*?)\)'iu", $closure, $v))
		{
			$v = explode(',', $v[1]);
			foreach ($v as $w) if (preg_match("'^{$this->varRx}$'u", $w)) $vars[$w] = 0;
		}

		if (preg_match_all("'[^\\$\.a-zA-Z0-9_]var ([^;]+)'iu", $closure, $v))
		{
			$v = implode(',', $v[1]);

			$v = preg_replace("'\(.*?\)'u", '', $v);
			$v = preg_replace("'\{.*?\}'u", '', $v);
			$v = preg_replace("'\[.*?\]'u", '', $v);
			$v = explode(',', $v);
			foreach ($v AS $w) if (preg_match("'^{$this->varRx}'u", $w, $v)) $vars[$v[0]] = 0;
		}

		return $vars;
	}

	function _getNextName($exclude = array())
	{
		if ($exclude===true) return $this->counter = -1;
		else $this->counter++;

		$str0 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ_';
		$len0 = strlen($str0);

		$str1 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz0123456789';
		$len1 = strlen($str1);

		$name = $str0[$this->counter % $len0];

		$i = intval($this->counter / $len0) - 1;
		while ($i>=0)
		{
			$name .= $str1[ $i % $len1 ];
			$i = intval($i / $len1) - 1;
		}

		return !(isset($this->known[$name]) || isset($exclude[$name])) ? $name : $this->_getNextName($exclude);
	}

	function replace_keys_by_values(&$array, $str)
	{
		return str_replace(array_keys($array), array_values($array), $str);
	}
}
