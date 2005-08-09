<?php

/*
* This class obfuscate javascript code
* 
* Every var whose name/method begins with a "$" will be replaced by a shorter one, typicaly a single letter.
* Comments will be removed
* White chars will be stripped
*
* Works with all JavaScript code, except for code like (i++ + 2) which will become (i+++2)
*/

class jsquiz
{
	protected $known = array();
	protected $data = array();
	protected $counter;

	public function addFile($filename)
	{
		$this->data[] = file_get_contents($filename, true);
	}
	public function addJs($code) {$this->data[] =& $code;}

	public function get()
	{
		$code = str_replace(
			array("\r\n", "\r"),
			array("\n"  , "\n"),
			implode("\n", $this->data)
		);
		if (DEBUG > 1) return $code;

		list($code, $this->strings) = $this->extractStrings($code);

		list($code, $this->closures) = $this->extractClosures($code);

		$key = "//''\"\"f0'";
		$this->closures[$key] =& $code;

		$tree = array();
		$this->makeVars($code, $tree[$key] = array('parent' => false));

		$this->renameVars($tree[$key]);

		return substr($tree[$key]['code'], 1);
	}

	protected function extractStrings($f)
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
						if (strpos('-!%&;<=>~:^+|,(*?[{', $a)!==false)
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
		$code = str_replace('};else', '}else', $code);
		$code = preg_replace("'return([\[\{])'u", 'return $1', $code);
		$code = preg_replace("';{2,}'u", ';', $code);
		$code = str_replace(';}', '}', $code);

		return array($code, $strings);
	}

	protected function extractClosures($code)
	{
		$code = ';' . $code;

		preg_match_all("'[^a-z0-9_\\$]([a-z_][a-z0-9_\\$]*)'iu", $code, $this->known);
		$this->known = array_flip($this->known[1]);

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

		return array($f[0], $closures);
	}

	protected function makeVars($closure, &$tree)
	{
		$tree['code'] = $closure;

		# Get all local vars (arguments and "var" prefixed)
		$tree['local'] = $this->getVars($closure);

		# Get all used vars, local and non-local
		$tree['used'] = array();
		$a = preg_replace("'^.function \\$[a-zA-Z0-9_]+\('u", '', $closure);
		$a = $this->replace_keys_by_values($this->strings, $a);
		if (preg_match_all("#\.?\\$[a-zA-Z0-9_]+#u", $a, $w))
		{
			foreach ($w[0] as $k) @$tree['used'][$k]++;
		}

		if (preg_match_all("#//''\"\"f\d+'#u", $closure, $w))
		{
			foreach ($w[0] as $a)
			{
				if (preg_match("'^.function (\\$[a-zA-Z0-9_]+)\('u", $this->closures[$a], $w))
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
				$this->makeVars($this->closures[$a], $tree['childs'][$a] = array('parent' => &$tree));
			}
		}
	}

	protected function renameVars(&$tree)
	{
		arsort($tree['local']);
		$this->_getNextName(true);

		foreach (array_keys($tree['local']) as $var)
		{
			$tree['local'][$var] = (substr($var, 0, 1)=='.' ? '.' : '') . $this->_getNextName(array_flip($tree['used']));
		}

		foreach (array_keys($tree['childs']) as $var)
		{
			$this->renameVars($tree['childs'][$var]);
			$tree['code'] = str_replace($var, $tree['childs'][$var]['code'], $tree['code']);
		}

		$tree['code'] = $this->replace_keys_by_values($this->strings, $tree['code']);
		$tree['code'] = preg_replace("#\.?\\$[a-zA-Z0-9_]+#eu", 'isset($tree["local"][\'$0\']) ? $tree["local"][\'$0\'] : \'$0\'', $tree['code']);
	}

	protected function getVars($closure)
	{
		$vars = array();

		if (preg_match("'\((.*?)\)'iu", $closure, $v))
		{
			$v = explode(',', $v[1]);
			foreach ($v as $w) if (preg_match("'^\\$[a-zA-Z0-9_]+$'u", $w)) $vars[$w] = 0;
		}

		if (preg_match_all("'[^\\$\.a-zA-Z0-9_]var ([^;]+)'iu", $closure, $v))
		{
			$v = implode(',', $v[1]);

			$v = preg_replace("'\(.*?\)'u", '', $v);
			$v = preg_replace("'\{.*?\}'u", '', $v);
			$v = preg_replace("'\[.*?\]'u", '', $v);
			$v = explode(',', $v);
			foreach ($v AS $w) if (preg_match("'^\\$[a-zA-Z0-9_]+'u", $w, $v)) $vars[$v[0]] = 0;
		}

		return $vars;
	}

	protected function _getNextName($exclude = array())
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
	
	protected function replace_keys_by_values(&$array, $str)
	{
		return str_replace(array_keys($array), array_values($array), $str);
	}
}
