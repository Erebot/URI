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

class   ParseURLCompatibilityTest
extends PHPUnit_Framework_TestCase
{
    public function userinfoProvider()
    {
        return array(
            array("a", "a", NULL),
            array("a:", "a", NULL),     // Theorically, pass should be "" here.
            array("a:b:c", "a", "b:c"), // Invalid based on the RFC.
            array(":b", NULL, "b"),     // Also invalid.
        );
    }

    /**
     * @dataProvider    userinfoProvider
     * @covers          \Erebot\URI\URI::asParsedURL
     */
    public function testParseURLCompatibilityQuirks($userinfo, $user, $pass)
    {
        $uri = new \Erebot\URI\URI("http://".$userinfo."@localhost/");

        // Try requesting those specific components first.
        if ($user !== NULL)
            $this->assertEquals($user, $uri->asParsedURL(PHP_URL_USER));
        else
            $this->assertNull($uri->asParsedURL(PHP_URL_USER));

        if ($pass !== NULL)
            $this->assertEquals($pass, $uri->asParsedURL(PHP_URL_PASS));
        else
            $this->assertNull($uri->asParsedURL(PHP_URL_PASS));


        // Now try with a global retrieval.
        $components = $uri->asParsedURL();
        if ($user !== NULL) {
            $this->assertEquals($user, $components['user']);
            $this->assertEquals($user, $components[PHP_URL_USER]);
        }
        else {
            $this->assertFalse(isset($components['user']));
            $this->assertFalse(isset($components[PHP_URL_USER]));
        }

        if ($pass !== NULL) {
            $this->assertEquals($pass, $components['pass']);
            $this->assertEquals($pass, $components[PHP_URL_PASS]);
        }
        else {
            $this->assertFalse(isset($components['pass']));
            $this->assertFalse(isset($components[PHP_URL_PASS]));
        }
    }
}
