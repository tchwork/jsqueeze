<?php

namespace JSqueeze\Tests;

use JSqueeze;

class JSqueezeTest extends \PHPUnit_Framework_TestCase
{
    function testUglifyJs()
    {
        if ($h = opendir(__DIR__ . '/uglifyjs/test/'))
        {
            while ($file = readdir($h))
            {
                $xfail = '.xfail' === substr($file, -6) ? '.xfail' : '';
                if ($xfail) $file = substr($file, 0, -6);

                if ('.js' === substr($file, -3) && file_exists(__DIR__ . '/uglifyjs/expected/' . $file))
                {
                    $test = file_get_contents(__DIR__ . '/uglifyjs/test/' . $file . $xfail);
                    $expe = file_get_contents(__DIR__ . '/uglifyjs/expected/' . $file);

                    $jz = new JSqueeze;
                    $test = $jz->squeeze($test) . "\n";

                    $xfail
                        ? $this->assertFalse($expe === $test, "Xfail {$file}")
                        : $this->assertSame($expe, $test, "Testing {$file}");
                }
            }
        }
    }
}
