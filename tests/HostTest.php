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

class   HostTest
extends PHPUnit_Framework_TestCase
{
    public function validProvider()
    {
        $values = array();
        $data   = array(
            '[2a01:e35:2e30:c120::28]',                 // irc.secours.iiens.net
            '[2a01:e34:ee8f:6730:201:2ff:fe01:e964]',   // erebot.net
            '[::1]',                                    // ip6-loopback
            "localhost",
            "127.0.0.1",
        );
        foreach ($data as $host)
            $values[] = array($host);
        return $values;
    }

    /**
     * @dataProvider    validProvider
     * @covers          \Erebot\URI
     */
    public function testNoPort($host)
    {
        try {
            $uri = new \Erebot\URI('http://'.$host.'/');
        }
        catch (Erebot_InvalidValueException $e) {
            $this->fail("'".$host."' and no port: ".$e->getMessage());
        }
    }

    /**
     * @dataProvider    validProvider
     * @covers          \Erebot\URI
     */
    public function testWithPort($host)
    {
        try {
            $uri = new \Erebot\URI('http://'.$host.':42/');
        }
        catch (Erebot_InvalidValueException $e) {
            $this->fail("'".$host."' and a port: ".$e->getMessage());
        }
    }
}
