<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('\\PHPUnit\\Framework\\TestCase', 'PHPUnit_Framework_TestCase');
}

class   RelativeURITest
extends PHPUnit_Framework_TestCase
{
    public function normalResults()
    {
        return array(
            # 5.4.1.  Normal Examples
            array("g:h",        "g:h"),
            array("g",          "http://a/b/c/g"),
            array("./g",        "http://a/b/c/g"),
            array("g/",         "http://a/b/c/g/"),
            array("/g",         "http://a/g"),
            array("//g",        "http://g"),
            array("?y",         "http://a/b/c/d;p?y"),
            array("g?y",        "http://a/b/c/g?y"),
            array("#s",         "http://a/b/c/d;p?q#s"),
            array("g#s",        "http://a/b/c/g#s"),
            array("g?y#s",      "http://a/b/c/g?y#s"),
            array(";x",         "http://a/b/c/;x"),
            array("g;x",        "http://a/b/c/g;x"),
            array("g;x?y#s",    "http://a/b/c/g;x?y#s"),
            array("",           "http://a/b/c/d;p?q"),
            array(".",          "http://a/b/c/"),
            array("./",         "http://a/b/c/"),
            array("..",         "http://a/b/"),
            array("../",        "http://a/b/"),
            array("../g",       "http://a/b/g"),
            array("../..",      "http://a/"),
            array("../../",     "http://a/"),
            array("../../g",    "http://a/g"),

            # 5.4.2.  Abnormal Examples
            array("../../../g",     "http://a/g"),
            array("../../../../g",  "http://a/g"),
            array("/./g",           "http://a/g"),
            array("/../g",          "http://a/g"),
            array("g.",             "http://a/b/c/g."),
            array(".g",             "http://a/b/c/.g"),
            array("g..",            "http://a/b/c/g.."),
            array("..g",            "http://a/b/c/..g"),
            array("./../g",         "http://a/b/g"),
            array("./g/.",          "http://a/b/c/g/"),
            array("g/./h",          "http://a/b/c/g/h"),
            array("g/../h",         "http://a/b/c/h"),
            array("g;x=1/./y",      "http://a/b/c/g;x=1/y"),
            array("g;x=1/../y",     "http://a/b/c/y"),
            array("g?y/./x",        "http://a/b/c/g?y/./x"),
            array("g?y/../x",       "http://a/b/c/g?y/../x"),
            array("g#s/./x",        "http://a/b/c/g#s/./x"),
            array("g#s/../x",       "http://a/b/c/g#s/../x"),
            array("http:g",         "http:g"),
        );
    }

    /**
     * @dataProvider    normalResults
     * @covers          \Erebot\URI::relative
     */
    public function testNormalResolution($reference, $targetURI)
    {
        $base   = new \Erebot\URI("http://a/b/c/d;p?q");
        $target = $base->relative($reference);
        $this->assertEquals($targetURI, (string) $target, $reference);
    }
}
