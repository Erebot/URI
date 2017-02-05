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

class   ParseURLCompatibilityTest
extends PHPUnit_Framework_TestCase
{
    public function urlProvider()
    {
        return array(
            // Valid URLs.
            // - Explicit values for all components
            array("http://a:b@example.com:80/path?query=something#fragment"),
            // - URI with IPv6 address and zone ID (see RFC 6874)
            array("http://[fe80::226:b9ff:fe7e:7ca9%25eth0]/"),

            // These cause PHP to reject the URL entirely.
            array("http:///example.com"),
            array("http://:80"),
            array("http://user@:80"),
            array("http://@"),

            // These are invalid based on the RFCs,
            // but PHP will still parse them... somewhat.
            array("http://a@example.com/"),
            array("http://a:b:c@example.com/"),
        );
    }

    /**
     * @dataProvider    urlProvider
     * @covers          \Erebot\URI::asParsedURL
     */
    public function testParseURLCompatibilityQuirks($url)
    {
        $uri = new \Erebot\URI($url);

        // Check array-based retrieval.
        $this->assertSame(parse_url($url), $uri->asParsedURL());

        // Check with specific components first.
        $this->assertSame(parse_url($url, PHP_URL_SCHEME), $uri->asParsedURL(PHP_URL_SCHEME));
        $this->assertSame(parse_url($url, PHP_URL_USER), $uri->asParsedURL(PHP_URL_USER));
        $this->assertSame(parse_url($url, PHP_URL_PASS), $uri->asParsedURL(PHP_URL_PASS));
        $this->assertSame(parse_url($url, PHP_URL_HOST), $uri->asParsedURL(PHP_URL_HOST));
        $this->assertSame(parse_url($url, PHP_URL_PORT), $uri->asParsedURL(PHP_URL_PORT));
        $this->assertSame(parse_url($url, PHP_URL_PATH), $uri->asParsedURL(PHP_URL_PATH));
        $this->assertSame(parse_url($url, PHP_URL_QUERY), $uri->asParsedURL(PHP_URL_QUERY));
        $this->assertSame(parse_url($url, PHP_URL_FRAGMENT), $uri->asParsedURL(PHP_URL_FRAGMENT));
    }

    /**
     * @covers          \Erebot\URI::asParsedURL
     */
    public function testParseURLCompatibilityQuirks2()
    {
        $url = "http://a:@example.com/";
        $uri = new \Erebot\URI($url);

        // Check with specific components first.
        $this->assertSame('a', $uri->asParsedURL(PHP_URL_USER));
        $this->assertSame('', $uri->asParsedURL(PHP_URL_PASS));

        // Now check array-based retrieval.
        $received = $uri->asParsedURL();
        $expected = parse_url($url);
        $expected['user'] = 'a'; // Older versions of PHP return
        $expected['pass'] = '';  // null instead; patch the result
        ksort($expected);
        ksort($received);
        $this->assertSame($expected, $received);
    }

    /**
     * @covers          \Erebot\URI::asParsedURL
     */
    public function testParseURLCompatibilityQuirks3()
    {
        $url = "http://:b@example.com/";
        $uri = new \Erebot\URI($url);

        // Check with specific components first.
        $this->assertSame('', $uri->asParsedURL(PHP_URL_USER));
        $this->assertSame('b', $uri->asParsedURL(PHP_URL_PASS));

        // Now check array-based retrieval.
        $received = $uri->asParsedURL();
        $expected = parse_url($url);
        $expected['user'] = '';  // Older versions of PHP return
        $expected['pass'] = 'b'; // null instead; patch the result
        ksort($expected);
        ksort($received);
        $this->assertSame($expected, $received);
    }

    public function noSchemeProvider()
    {
        return array(
            // These are not absolute URLs.
            // - Same scheme host change
            array("//example.com/"),
            // - Absolute path
            array("/foo/bar"),
            array("foo/bar"),
            // - Relative path
        );
    }

    /**
     * @dataProvider    noSchemeProvider
     * @covers          \Erebot\URI::asParsedURL
     */
    public function testParseURLWithoutSchemes($url)
    {
        $this->setExpectedException('\InvalidArgumentException');
        $uri = new \Erebot\URI($url);
        $uri->asParsedURL();
    }

}
