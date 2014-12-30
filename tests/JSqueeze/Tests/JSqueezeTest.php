<?php
/*
 * Copyright (C) 2014 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (http://apache.org/licenses/LICENSE-2.0.txt), or
 * GNU General Public License v2.0 (http://gnu.org/licenses/gpl-2.0.txt).
 * If you received a full copy of JSqueeze, copies of both licenses should
 * be present in the top level directory.
 */

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
