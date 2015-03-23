<?php
/*
 * Copyright (C) 2015 Nicolas Grekas - p@tchwork.com
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the (at your option):
 * Apache License v2.0 (see provided LICENCE.ASL20 file), or
 * GNU General Public License v2.0 (see provided LICENCE.GPLv2 file).
 */

namespace JSqueeze\Tests;

use JSqueeze;

class JSqueezeTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider provideUglifyJs */
    function testUglifyJs($file)
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

    function provideUglifyJs()
    {
        $tests = array();

        if ($h = opendir(__DIR__ . '/uglifyjs/test/')) {
            while ($file = readdir($h)) {
                $tests[] = array($file);
            }
        }

        return $tests;
    }
}
