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

class   MainTest
extends PHPUnit_Framework_TestCase
{
    /**
     * @covers \Erebot\URI
     */
    public function testParsing()
    {
        $base   = new \Erebot\URI("http://u:p@a:8080/b/c/d;p?q#r");
        $this->assertEquals("http",     $base->getScheme());
        $this->assertEquals("u:p",      $base->getUserInfo());
        $this->assertEquals("a",        $base->getHost());
        $this->assertEquals(8080,       $base->getPort());
        $this->assertEquals("/b/c/d;p", $base->getPath());
        $this->assertEquals("q",        $base->getQuery());
        $this->assertEquals("r",        $base->getFragment());
    }

    /**
     * @covers \Erebot\URI::__toString
     */
    public function testToString()
    {
        $original   = "http://u:p@a:8080/b/c/d;p?q#r";
        $base       = new \Erebot\URI($original);
        $this->assertEquals($original, (string) $base);
    }

    /**
     * @covers \Erebot\URI
     */
    public function testCaseNormalization()
    {
        // The scheme and host components must be lowercased.
        // The dot segments must be handled correctly.
        $original   = "bAr://LOCALHOST/../a/b/./c/../d";
        $normed     = "bar://localhost/a/b/d";
        $uri        = new \Erebot\URI($original);
        $this->assertEquals($original, $uri->toURI(TRUE));
        $this->assertEquals($normed, $uri->toURI(FALSE));
        $this->assertEquals($normed, $uri->toURI());
        $this->assertEquals($normed, (string) $uri);
    }

    /**
     * @covers \Erebot\URI
     */
    public function testPercentEncodingNormalisation()
    {
        // The hexadecimal digits used for percent-encoded characters
        // must be UPPERCASED.
        // Percent-encoded characters belonging to the "unreserved" set
        // must be replaced by their actual representation.
        $uri = new \Erebot\URI(
            "http://%41%20%3a%62:%63%40%64@".
            "loc%61l%2dhost.example%2ecom/".
            "%7e%2e%2f/foobar"
        );
        $this->assertEquals(
            "http://A%20%3Ab:c%40d@local-host.example.com/~.%2F/foobar",
            (string) $uri
        );
    }

    public function validPathProvider()
    {
        return array(
            array("/a/b/c"),
            array(""),
            array("/"),
            array("@a/b")
        );
    }

    /**
     * @dataProvider    validPathProvider
     * @covers          \Erebot\URI::setPath
     */
    public function testSetPathWithValidPath($path)
    {
        $uri = new \Erebot\URI("http://localhost");
        $uri->setPath($path);

        $this->assertEquals($uri->getPath(), $path);
    }

    public function invalidPathProvider()
    {
        return array(
            array("?/"),
            array("#/"),
            array(FALSE)
        );
    }

    /**
     * @dataProvider    invalidPathProvider
     * @covers          \Erebot\URI::setPath
     */
    public function testSetPathWithInvalidPath($path)
    {
        $this->setExpectedException('\InvalidArgumentException');

        $uri = new \Erebot\URI("http://localhost");
        $uri->setPath($path);
    }

    public function invalidHostProvider()
    {
        return array(
            array("http://@:"),
            array("http://:"),
        );
    }

    /**
     * @dataProvider    invalidHostProvider
     * @covers          \Erebot\URI::setHost
     */
    public function testSetHostWithInvalidHost($url)
    {
        $this->setExpectedException('\InvalidArgumentException');
        $uri = new \Erebot\URI($url);
    }

}
