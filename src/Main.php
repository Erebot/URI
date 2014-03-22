<?php
/*
    This file is part of Erebot, a modular IRC bot written in PHP.

    Copyright © 2010 François Poirotte

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

namespace Erebot\URI;

/**
 * \brief
 *      Simple parser/generator for Uniform Resource Identifiers
 *      as defined in RFC 3986.
 *
 * This class can be used as both a parser and a generator for
 * Uniform Resource Identifiers (URI) as defined in RFC 3986.
 * It is primarly meant to deal with with absolute URIs but also
 * offers methods to deal with relative URIs.
 * It is mostly compatible with parse_url(), but tends to be
 * stricter when validating data.
 *
 * This implementation doesn't assume that the "userinfo" part
 * is made up of a "username:password" pair (in contrast to
 * parse_url() which is based on RFC 1738), and provides a single
 * field named "userinfo" instead. Such pairs will be merged upon
 * encounter.
 *
 * All components are normalized by default when retrieved using
 * any of the getters except asParsedURL(). You may override this
 * behaviour by passing $raw=true to said getters.
 * Normalization is done using the rules defined in RFC 3986.
 */
class Main implements \Erebot\URI\URIInterface
{
    /// Scheme component (sometimes also erroneously called a "protocol").
    protected $scheme;
    /// User information component (such as a "username:password" pair).
    protected $userinfo;
    /// Host component ("authority", even though "authority" is more general).
    protected $host;
    /// Port component.
    protected $port;
    /// Path component.
    protected $path;
    /// Query component.
    protected $query;
    /// Fragment component.
    protected $fragment;

    /**
     * Constructs an URI.
     *
     * \param mixed $uri
     *      Either a string representing the URI or an array
     *      as returned by PHP's parse_url() function.
     *
     * \throw ::InvalidArgumentException
     *      The given URI is invalid.
     */
    public function __construct($uri)
    {
        if (is_string($uri)) {
            $uri = $this->parseURI($uri, false);
        }

        if (!is_array($uri)) {
            throw new \InvalidArgumentException('Invalid URI');
        }

        if (!isset($uri['userinfo']) && isset($uri['user'])) {
            $uri['userinfo'] = $uri['user'];
            if (isset($uri['pass'])) {
                $uri['userinfo'] .= ':'.$uri['pass'];
            }
        }

        $components = array(
            'Scheme',
            'Host',
            'Port',
            'Path',
            'Query',
            'Fragment',
            'UserInfo',
        );

        foreach ($components as $component) {
            $tmp    = strtolower($component);
            $setter = 'set'.$component;
            if (isset($uri[$tmp])) {
                $this->$setter($uri[$tmp]);
            } else {
                $this->$setter(null);
            }
        }
    }

    /**
     * Parses an URI using the grammar defined in RFC 3986.
     *
     * \param string $uri
     *      URI to parse.
     *
     * \param bool $relative
     *      Whether $uri must be considered as an absolute URI (\b false)
     *      or a relative reference (\b true).
     *
     * \retval array
     *      An associative array containing the different components
     *      that could be parsed out of this URI.
     *      It uses the same format as parse_url(), except that the
     *      "user" and "pass" components are merged into a single
     *      "userinfo" component and only string keys are defined.
     *
     * \throw ::InvalidArgumentException
     *      The given $uri is not valid.
     */
    protected function parseURI($uri, $relative)
    {
        $result = array();

        if (!$relative) {
            // Parse scheme.
            $pos = strpos($uri, ':');
            if (!$pos) {
                // An URI starting with ":" is also invalid.
                throw new \InvalidArgumentException('No scheme found');
            }

            $result['scheme'] = substr($uri, 0, $pos);
            $uri = (string) substr($uri, $pos + 1);
        }

        // Parse fragment.
        $pos = strpos($uri, '#');
        if ($pos !== false) {
            $result['fragment'] = (string) substr($uri, $pos + 1);
            $uri = (string) substr($uri, 0, $pos);
        }

        // Parse query string.
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $result['query'] = (string) substr($uri, $pos + 1);
            $uri = (string) substr($uri, 0, $pos);
        }

        // Handle "path-empty".
        if ($uri == '') {
            $result['path'] = '';
            return $result;
        }

        // Handle "hier-part".
        if (substr($uri, 0, 2) == '//') {
            // Remove leftovers from the scheme field.
            $uri = (string) substr($uri, 2);

            // Parse path.
            $result['path'] = '';
            $pos = strpos($uri, '/');
            if ($pos !== false) {
                $result['path'] = substr($uri, $pos);
                $uri = (string) substr($uri, 0, $pos);
            }

            // Parse userinfo.
            $pos = strpos($uri, '@');
            if ($pos !== false) {
                $result['userinfo'] = (string) substr($uri, 0, $pos);
                $uri = (string) substr($uri, $pos + 1);
            }

            // Parse port.
            $rpos   = strcspn(strrev($uri), ':]');
            $len    = strlen($uri);
            if ($rpos != 0 && $rpos < $len && $uri[$len - $rpos - 1] != "]") {
                $result['port'] = (string) substr($uri, -1 * $rpos);
                $uri = (string) substr($uri, 0, -1 * $rpos - 1);
            }

            $result['host'] = $uri;
            return $result;
        }

        // Handle "path-absolute" & "path-rootless".
        $result['path'] = $uri;
        return $result;
    }

    /**
     * Performs normalization of percent-encoded characters.
     *
     * \param string $data
     *      Some text containing percent-encoded characters
     *      that need to be normalized.
     *
     * \retval string
     *      The same text, after percent-encoding normalization.
     *
     * \note
     *      This method is just a thin wrapper around
     *      Erebot_URI::normalizePercentReal.
     */
    protected static function normalizePercent($data)
    {
        return preg_replace_callback(
            '/%([[:xdigit:]]{2})/',
            array('self', 'normalizePercentReal'),
            $data
        );
    }

    /**
     * Performs normalization of a percent-encoded character.
     *
     * \param string $hexchr
     *      One percent-encoded character that needs to be normalized.
     *
     * \retval string
     *      The same text, after percent-encoding normalization.
     */
    public static function normalizePercentReal($hexchr)
    {
        // 6.2.2.1.  Case Normalization
        // Percent-encoded characters must use uppercase letters.
        // 6.2.2.2.  Percent-Encoding Normalization
        $unreserved =   'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.
                        'abcdefghijklmnopqrstuvwxyz'.
                        '-._~';

        $chr = chr(hexdec($hexchr[1]));
        if (strpos($unreserved, $chr) !== false) {
            return $chr;
        }
        return '%' . strtoupper($hexchr[1]);
    }

    public function toURI($raw = false, $credentials = true)
    {
        // 5.3.  Component Recomposition
        $result = "";

        // In our case, the scheme will always be set
        // because we only deal with absolute URIs here.
        // The condition is checked anyway to keep the code
        // in line with the algorithm described in RFC 3986.
        if ($this->scheme !== null) {
            $result .= $this->getScheme($raw).':';
        }

        if ($this->host !== null) {
            $result .= '//';
            if ($this->userinfo !== null && $credentials) {
                $result .= $this->getUserInfo($raw)."@";
            }

            $result    .= $this->getHost($raw);
            $port       = $this->getPort($raw);
            if ($port !== null) {
                $result .= ':'.$port;
            }
        }

        $result .= $this->getPath($raw);

        if ($this->query !== null) {
            $result .= '?'.$this->getQuery($raw);
        }

        if ($this->fragment !== null) {
            $result .= '#'.$this->getFragment($raw);
        }

        return $result;
    }

    public function __toString()
    {
        return $this->toURI();
    }

    public function getScheme($raw = false)
    {
        // 6.2.2.1.  Case Normalization
        // Characters must be normalized to use lowercase letters.
        if ($raw) {
            return $this->scheme;
        }
        return strtolower($this->scheme);
    }

    public function setScheme($scheme)
    {
        // scheme        = ALPHA *( ALPHA / DIGIT / "+" / "-" / "." )
        if (!preg_match('/^[-[:alpha:][:alnum:]\\+\\.]*$/Di', $scheme)) {
            throw new \InvalidArgumentException('Invalid scheme');
        }
        $this->scheme = $scheme;
    }

    public function getUserInfo($raw = false)
    {
        if ($raw) {
            return $this->userinfo;
        }
        return  ($this->userinfo === null)
                ? null
                : $this->normalizePercent($this->userinfo);
    }

    public function setUserInfo($userinfo)
    {
        /*
        userinfo      = *( unreserved / pct-encoded / sub-delims / ":" )
        pct-encoded   = "%" HEXDIG HEXDIG
        unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
        sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
                      / "*" / "+" / "," / ";" / "="
        */
        $pattern =  '(?:'.
                        '[-[:alnum:]\\._~!\\$&\'\\(\\)\\*\\+,;=:]|'.
                        '%[[:xdigit:]]{2}'.
                    ')*';
        if ($userinfo !== null && !preg_match('/^'.$pattern.'$/Di', $userinfo)) {
            throw new \InvalidArgumentException('Invalid user information');
        }
        $this->userinfo = $userinfo;
    }

    public function getHost($raw = false)
    {
        // 6.2.2.1.  Case Normalization
        // Characters must be normalized to use lowercase letters.
        if ($raw) {
            return $this->host;
        }
        return  ($this->host !== null)
                ? strtolower($this->normalizePercent($this->host))
                : null;
    }

    public function setHost($host)
    {
        /*
        host          = IP-literal / IPv4address / reg-name
        IP-literal    = "[" ( IPv6address / IPvFuture  ) "]"
        IPvFuture     = "v" 1*HEXDIG "." 1*( unreserved / sub-delims / ":" )
        IPv6address   =                            6( h16 ":" ) ls32
                      /                       "::" 5( h16 ":" ) ls32
                      / [               h16 ] "::" 4( h16 ":" ) ls32
                      / [ *1( h16 ":" ) h16 ] "::" 3( h16 ":" ) ls32
                      / [ *2( h16 ":" ) h16 ] "::" 2( h16 ":" ) ls32
                      / [ *3( h16 ":" ) h16 ] "::"    h16 ":"   ls32
                      / [ *4( h16 ":" ) h16 ] "::"              ls32
                      / [ *5( h16 ":" ) h16 ] "::"              h16
                      / [ *6( h16 ":" ) h16 ] "::"
        h16           = 1*4HEXDIG
        ls32          = ( h16 ":" h16 ) / IPv4address
        IPv4address   = dec-octet "." dec-octet "." dec-octet "." dec-octet
        dec-octet     = DIGIT                 ; 0-9
                      / %x31-39 DIGIT         ; 10-99
                      / "1" 2DIGIT            ; 100-199
                      / "2" %x30-34 DIGIT     ; 200-249
                      / "25" %x30-35          ; 250-255
        reg-name      = *( unreserved / pct-encoded / sub-delims )
        pct-encoded   = "%" HEXDIG HEXDIG
        unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
        sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
                      / "*" / "+" / "," / ";" / "="
        */
        $decOctet       = '(?:\\d|[1-9]\\d|1\\d{2}|2[0-4]\\d|25[0-5])';
        $dotAddress     = $decOctet.'(?:\\.'.$decOctet.'){3}';
        $half           = '[[:xdigit:]]{1,4}';
        $long           = '(?:'.$half.':'.$half.'|'.$dotAddress.')';
        $colonAddress   =
            '(?:'.
            '(?:'.$half.':){6}'.$long.'|'.
            '::(?:'.$half.':){5}'.$long.'|'.
            '(?:'.$half.')?::(?:'.$half.':){4}'.$long.'|'.
            '(?:(?:'.$half.':)?'.$half.')?::(?:'.$half.':){3}'.$long.'|'.
            '(?:(?:'.$half.':){0,2}'.$half.')?::(?:'.$half.':){2}'.$long.'|'.
            '(?:(?:'.$half.':){0,3}'.$half.')?::'.$half.':'.$long.'|'.
            '(?:(?:'.$half.':){0,4}'.$half.')?::'.$long.'|'.
            '(?:(?:'.$half.':){0,5}'.$half.')?::'.$half.'|'.
            '(?:(?:'.$half.':){0,6}'.$half.')?::'.
            ')';
        $ipFuture       =   'v[[:xdigit:]]+\\.'.
                            '[-[:alnum:]\\._~!\\$&\'\\(\\)*\\+,;=]+';
        $ipLiteral      =   '\\[(?:'.$colonAddress.'|'.$ipFuture.')\\]';
        $regName        =   '(?:[-[:alnum:]\\._~!\\$&\'\\(\\)*\\+,;=]|'.
                            '%[[:xdigit:]]{2})*';
        $pattern        =   '(?:'.$ipLiteral.'|'.$dotAddress.'|'.$regName.')';
        if ($host !== null && !preg_match('/^'.$pattern.'$/Di', $host)) {
            throw new \InvalidArgumentException('Invalid host');
        }
        $this->host = $host;
    }

    public function getPort($raw = false)
    {
        // 6.2.3.  Scheme-Based Normalization
        if ($raw) {
            return $this->port;
        }

        if ($this->port == '') {
            return null;
        }

        $port = (int) $this->port;

        // Try to canonicalize the port.
        $tcp = getservbyname($this->scheme, 'tcp');
        $udp = getservbyname($this->scheme, 'udp');

        if ($tcp == $port && ($udp === false || $udp == $tcp)) {
            return null;
        }

        if ($udp == $port && ($tcp === false || $udp == $tcp)) {
            return null;
        }

        return $port;
    }

    public function setPort($port)
    {
        // port          = *DIGIT
        if (is_int($port)) {
            $port = (string) $port;
        }
        if ($port !== null && strspn($port, '0123456789') != strlen($port)) {
            throw new \InvalidArgumentException('Invalid port');
        }
        $this->port = $port;
    }

    /**
     * Removes "dot segments" ("." and "..") from a path.
     *
     * \param string $path
     *      Path on which to operate.
     *
     * \retval string
     *      The same $path, with all its dot segments
     *      substituted.
     */
    protected function removeDotSegments($path)
    {
        if ($path === null) {
            throw new \InvalidArgumentException('Path not set');
        }

        // §5.2.4.  Remove Dot Segments
        $input  = $path;
        $output = '';

        while ($input != '') {
            if (substr($input, 0, 3) == '../') {
                $input = (string) substr($input, 3);
            } elseif (substr($input, 0, 2) == './') {
                $input = (string) substr($input, 2);
            } elseif (substr($input, 0, 3) == '/./') {
                $input = substr($input, 2);
            } elseif ($input == '/.') {
                $input = '/';
            } elseif (substr($input, 0, 4) == '/../') {
                $input  = (string) substr($input, 3);
                $pos    = strrpos($output, '/');
                if ($pos === false) {
                    $output = '';
                } else {
                    $output = substr($output, 0, $pos);
                }
            } elseif ($input == '/..') {
                $input  = '/';
                $pos    = strrpos($output, '/');
                if ($pos === false) {
                    $output = '';
                } else {
                    $output = substr($output, 0, $pos);
                }
            } elseif ($input == '.' || $input == '..') {
                $input = '';
            } else {
                $pos = strpos($input, '/', 1);
                if ($pos === false) {
                    $output    .= $input;
                    $input      = '';
                } else {
                    $output    .= substr($input, 0, $pos);
                    $input      = substr($input, $pos);
                }
            }
        }

        return $output;
    }

    /**
     * Merges the given path with the current URI's path.
     *
     * \param string $path
     *      Path to merge into the current path.
     *
     * \retval string
     *      Result of that merge.
     *
     * \note
     *      Despite its name, this method does not modify
     *      the given $path nor the current object.
     */
    protected function merge($path)
    {
        // 5.2.3.  Merge Paths
        if ($this->host !== null && $this->path == '') {
            return '/'.$path;
        }

        $pos = strrpos($this->path, '/');
        if ($pos === false) {
            return $path;
        }
        return substr($this->path, 0, $pos + 1).$path;
    }

    public function getPath($raw = false)
    {
        // 6.2.2.3.  Path Segment Normalization
        if ($raw) {
            return $this->path;
        }

        return $this->removeDotSegments(
            $this->normalizePercent($this->path)
        );
    }

    /**
     * Validates the given path.
     *
     * \param string $path
     *      Path to validate.
     *
     * \param bool $relative
     *      Whether the given $path is relative (\b true)
     *      or not (\b false).
     *
     * \retval bool
     *      \b true if the given $path is valid,
     *      \b false otherwise.
     */
    protected function validatePath($path, $relative)
    {
        /*
        path          = path-abempty    ; begins with "/" or is empty
                      / path-absolute   ; begins with "/" but not "//"
                      / path-noscheme   ; begins with a non-colon segment
                      / path-rootless   ; begins with a segment
                      / path-empty      ; zero characters
        path-abempty  = *( "/" segment )
        path-absolute = "/" [ segment-nz *( "/" segment ) ]
        path-noscheme = segment-nz-nc *( "/" segment )
                      ; only used for a relative URI
        path-rootless = segment-nz *( "/" segment )
                      ; only used for an absolute URI
        path-empty    = 0<pchar>
        segment       = *pchar
        segment-nz    = 1*pchar
        segment-nz-nc = 1*( unreserved / pct-encoded / sub-delims / "@" )
                      ; non-zero-length segment without any colon ":"
        pchar         = unreserved / pct-encoded / sub-delims / ":" / "@"
        unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
        sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
                      / "*" / "+" / "," / ";" / "="
        pct-encoded   = "%" HEXDIG HEXDIG
        */
        $pchar          =   '(?:'.
                                '[-[:alnum:]\\._~!\\$&\'\\(\\)\\*\\+,;=:@]|'.
                                '%[[:xdigit:]]'.
                            ')';
        $segment        = '(?:'.$pchar.'*)';
        $segmentNz      = '(?:'.$pchar.'+)';
        $segmentNzNc    =   '(?:'.
                                '[-[:alnum:]\\._~!\\$&\'\\(\\)\\*\\+,;=@]|'.
                                '%[[:xdigit:]]'.
                            ')+';
        $pathAbempty    = '(?:/'.$segment.')*';
        $pathAbsolute   = '/(?:'.$segmentNz.'(?:/'.$segment.')*)?';
        $pathNoscheme   = $segmentNzNc.'(?:/'.$segment.')*';
        $pathRootless   = $segmentNz.'(?:/'.$segment.')*';
        $pathEmpty      = '(?!'.$pchar.')';

        $pattern =  $pathAbempty.'|'.$pathAbsolute;
        if ($relative) {
            $pattern .= '|'.$pathNoscheme;
        } else {
            $pattern .= '|'.$pathRootless;
        }
        $pattern .= '|'.$pathEmpty;

        return (bool) preg_match('#^'.$pattern.'$#Di', $path);
    }

    /**
     * Sets the current URI's path.
     *
     * \param string $path
     *      New path for this URI.
     *
     * \param bool $relative
     *      Whether the given $path is relative (\b true)
     *      or not (\b false).
     *
     * \throw ::InvalidArgumentException
     *      The given $path is not valid.
     */
    protected function realSetPath($path, $relative)
    {
        if (!is_string($path) || !$this->validatePath($path, $relative)) {
            throw new InvalidArgumentException(
                'Invalid path; use relative() for relative paths'
            );
        }
        $this->path = $path;
    }

    public function setPath($path)
    {
        $this->realSetPath($path, false);
    }

    public function getQuery($raw = false)
    {
        if ($raw) {
            return $this->query;
        }
        return $this->normalizePercent($this->query);
    }

    public function setQuery($query)
    {
        /*
        pchar         = unreserved / pct-encoded / sub-delims / ":" / "@"
        query         = *( pchar / "/" / "?" )
        pct-encoded   = "%" HEXDIG HEXDIG
        unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
        sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
                      / "*" / "+" / "," / ";" / "="
        */
        $pattern    =   '(?:'.
                            '[-[:alnum:]\\._~!\\$&\'\\(\\)\\*\\+,;=/\\?]|'.
                            '%[[:xdigit:]]{2}'.
                        ')*';
        if ($query !== null && !preg_match('#^'.$pattern.'$#Di', $query)) {
            throw new \InvalidArgumentException('Invalid query');
        }
        $this->query = $query;
    }

    public function getFragment($raw = false)
    {
        if ($raw) {
            return $this->fragment;
        }
        return $this->normalizePercent($this->fragment);
    }

    public function setFragment($fragment)
    {
        /*
        pchar         = unreserved / pct-encoded / sub-delims / ":" / "@"
        fragment      = *( pchar / "/" / "?" )
        pct-encoded   = "%" HEXDIG HEXDIG
        unreserved    = ALPHA / DIGIT / "-" / "." / "_" / "~"
        sub-delims    = "!" / "$" / "&" / "'" / "(" / ")"
                      / "*" / "+" / "," / ";" / "="
        */
        $pattern    =   '(?:'.
                            '[-[:alnum:]\\._~!\\$&\'\\(\\)\\*\\+,;=/\\?]|'.
                            '%[[:xdigit:]]{2}'.
                        ')*';
        if ($fragment !== null && !preg_match('#^'.$pattern.'$#Di', $fragment)) {
            throw new \InvalidArgumentException('Invalid fragment');
        }
        $this->fragment = $fragment;
    }

    public function asParsedURL($component = -1)
    {
        if ($component == -1) {
            $result = array();
            $fields = array(
                'scheme'    => PHP_URL_SCHEME,
                'host'      => PHP_URL_HOST,
                'port'      => PHP_URL_PORT,
                'path'      => PHP_URL_PATH,
                'query'     => PHP_URL_QUERY,
                'fragment'  => PHP_URL_FRAGMENT,
            );

            foreach ($fields as $field => $alias) {
                if ($this->$field !== null) {
                    $result[$field] = $this->$field;
                    $result[$alias] = $result[$field];
                }
            }

            // Cleanup "port" component.
            if (isset($result['port'])) {
                if (strspn($result['port'], '0123456789') != strlen($result['port'])) {
                    unset($result['port']);
                    unset($result[PHP_URL_PORT]);
                } else {
                    $result['port']         =
                    $result[PHP_URL_PORT]   = (int) $result['port'];
                }
            }

            if ($this->userinfo !== null) {
                $limit = strcspn($this->userinfo, ':');
                if ($limit > 0) {
                    $user = substr($this->userinfo, 0, $limit);
                    $result['user']         = $user;
                    $result[PHP_URL_USER]   = $user;
                }
                $pass = substr($this->userinfo, $limit + 1);
                if ($pass !== false) {
                    $result['pass']         = $pass;
                    $result[PHP_URL_PASS]   = $pass;
                }
            }

            return $result;
        }

        switch ($component) {
            case PHP_URL_SCHEME:
                return $this->scheme;

            case PHP_URL_HOST:
                return $this->host;

            case PHP_URL_PORT:
                return  ($this->port === null || strspn($this->port, '0123456789') != strlen($this->port))
                        ? null
                        : (int) $this->port;

            case PHP_URL_PATH:
                return $this->path;

            case PHP_URL_QUERY:
                return $this->query;

            case PHP_URL_FRAGMENT:
                return $this->fragment;

            case PHP_URL_USER:
                $user = substr($this->userinfo, 0, strcspn($this->userinfo, ':'));
                return ($user == "" ? null : $user);

            case PHP_URL_PASS:
                $pass = substr(
                    $this->userinfo,
                    strcspn($this->userinfo, ':') + 1
                );
                return ($pass === false ? null : $pass);

            default:
                return null;
        }
    }

    public function relative($reference)
    {
        try {
            $cls    = __CLASS__;
            $result = new $cls($reference);
            return $result;
        } catch (\InvalidArgumentException $e) {
            // Nothing to do. We will try to interpret
            // the reference as a relative URI instead.
        }

        // Use the current (absolute) URI as the base.
        $result = clone $this;

        // 5.2.2.  Transform References
        // Our parser is strict.
        $parsed = $this->parseURI($reference, true);

        // No need to test the case where the scheme is defined.
        // This would be an absolute URI and has already been
        // captured by the previous try..catch block.

        // Always copy the new fragment.
        $result->setFragment(
            isset($parsed['fragment'])
            ? $parsed['fragment']
            : null
        );

        // "host" == "authority" here, see the grammar
        // for reasons why this always holds true.
        if (isset($parsed['host'])) {
            $result->setHost(isset($parsed['host']) ? $parsed['host'] : null);
            $result->setPort(isset($parsed['port']) ? $parsed['port'] : null);
            $result->setUserInfo(
                isset($parsed['userinfo'])
                ? $parsed['userinfo']
                : null
            );
            $result->realSetPath($parsed['path'], true);
            $result->setQuery(
                isset($parsed['query'])
                ? $parsed['query']
                : null
            );
            return $result;
        }

        // No need to copy path/authority because
        // $result is already a copy of the base.

        if ($parsed['path'] == '') {
            if (isset($parsed['query'])) {
                $result->setQuery($parsed['query']);
            }
            return $result;
        }

        if (substr($parsed['path'], 0, 1) == '/') {
            $result->realSetPath(
                $result->removeDotSegments($parsed['path']),
                true
            );
        } else {
            $result->realSetPath(
                $result->removeDotSegments($result->merge($parsed['path'])),
                true
            );
        }
        $result->setQuery(isset($parsed['query']) ? $parsed['query'] : null);
        return $result;
    }

    public static function fromAbsPath($abspath, $strict = true)
    {
        if (!strncasecmp(PHP_OS, "Win", 3)) {
            $isUnc = (substr($abspath, 0, 2) == '\\\\');
            if ($isUnc) {
                $abspath = ltrim($abspath, '\\');
            }
            $parts = explode('\\', $abspath);

            if ($isUnc && $parts[0] == '?') {
                // This is actually UNCW -- "Long UNC".
                array_shift($parts);
                if (strpos($parts[0], ':') !== false) {
                    $host = 'localhost';
                    $path = implode('\\', $parts);
                } elseif ($parts[0] != 'UNC') {
                    throw new \InvalidArgumentException('Invalid UNC path');
                } else {
                    array_shift($parts[0]);         // shift the "UNC" token.
                    $host = array_shift($parts[0]); // shift ServerName.
                    $path = implode('\\', $parts);
                }
            } elseif ($isUnc) {
                // Regular UNC path.
                $host = array_shift($parts[0]); // shift ServerName.
                $path = implode('\\', $parts);
            } else {
                // Regular local path.
                $host = 'localhost';
                $path = implode('\\', $parts);
            }

            if (!$strict) {
                $path = str_replace('/', '\\', $path);
            }
            $path = str_replace('/', '%2F', $path);
            $path = str_replace('\\', '/', $path);
            $path = ltrim($path, '/');
        } else {
            $host = 'localhost';

            if (DIRECTORY_SEPARATOR != '/') {
                if (!$strict) {
                    $abspath = str_replace('/', DIRECTORY_SEPARATOR, $abspath);
                }
                $path = str_replace('/', '%2F', $path);
                $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            }
            $path = ltrim($abspath, '/');
        }

        $host = strtolower(self::normalizePercent($host));
        $cls = __CLASS__;
        $url = 'file://' . ($host == 'localhost' ? '' : $host) . '/' . $path;
        return new $cls($url);
    }
}
