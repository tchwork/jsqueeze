<?php

namespace JSqueeze\Tests;

use JSqueeze;

class JSqueezeTest extends \PHPUnit_Framework_TestCase
{
    function testUglifyJs()
    {
        if ($h = opendir(__DIR__ . '/uglifyjs/test/'))
        {
            while ($file = readdir($h)) if ('.js' === substr($file, -3) && file_exists(__DIR__ . '/uglifyjs/expected/' . $file))
            {
                $test = file_get_contents(__DIR__ . '/uglifyjs/test/' . $file);
                $expe = file_get_contents(__DIR__ . '/uglifyjs/expected/' . $file);

                $jz = new JSqueeze;
                $test = $jz->squeeze($test);

                $this->assertSame($expe, $test, "Testing {$file}");
            }
        }
    }
}
