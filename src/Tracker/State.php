<?php

namespace OwaSdk\Tracker;

use OwaSdk\sdk as sdk;

/**
 * Open Web Analytics - An Open Source Web Analytics Framework
 * Licensed under GPL v2.0 http://www.gnu.org/copyleft/gpl.html
 *
 */

/**
 * State Store Class
 *
 * @author      Peter Adams <peter@openwebanalytics.com>
 */
class State
{

    private $config;
    private array $stores = [];
    private array $stores_meta = [];
    private array $stores_with_cdh = [];
    private array $initial_state = [];
    private array $cookies = [];

    function __construct($config = [])
    {

        $this->config = [
            'cookie_domain' => ''
        ];

        // merge incoming config params
        $this->config = array_merge($this->config, $config);

        $this->initializeStores();
    }

    private function getSetting($name)
    {

        if (array_key_exists($name, $this->config)) {

            return $this->config[$name];
        }

        return null;

    }

    function registerStore($name, $expiration, $length = '', $format = 'json', $type = 'cookie', $cdh = null): void
    {

        $this->stores_meta[$name] = [
            'expiration' => $expiration,
            'length' => $length,
            'format' => $format,
            'type' => $type,
            'cdh_required' => $cdh
        ];

        if ($cdh) {
            $this->stores_with_cdh[] = $name;
        }
    }


    public function get($store, $name = '')
    {

        sdk::debug("Getting state - store: " . $store . ' key: ' . $name);
        //owa_coreAPI::debug("existing stores: ".print_r($this->stores, true));
        if (!isset($this->stores[$store])) {
            $this->loadState($store);
        }

        if (array_key_exists($store, $this->stores)) {

            if (!empty($name)) {
                // check to ensure this is an array, could be a string.
                if (is_array($this->stores[$store]) && array_key_exists($name, $this->stores[$store])) {

                    return $this->stores[$store][$name];
                } else {
                    return false;
                }
            } else {

                return $this->stores[$store];
            }
        } else {

            return false;
        }
    }

    function setState($store, $name, $value, $store_type = '', $is_permanent = false): void
    {

        sdk::debug(sprintf('populating state for store: %s, name: %s, value: %s, store type: %s, is_perm: %s', $store, $name, print_r($value, true), $store_type, $is_permanent));

        // set values
        if (empty($name)) {
            $this->stores[$store] = $value;
            //owa_coreAPI::debug('setstate: '.print_r($this->stores, true));
        } else {
            //just in case the store was set first as a string instead of as an array.
            if (array_key_exists($store, $this->stores)) {

                if (!is_array($this->stores[$store])) {
                    $new_store = [];
                    // check to see if we need ot ad a cdh
                    if ($this->isCdhRequired($store)) {
                        $new_store['cdh'] = $this->getCookieDomainHash();
                    }

                    $new_store[$name] = $value;
                    $this->stores[$store] = $new_store;

                } else {
                    $this->stores[$store][$name] = $value;
                }
                // if the store does not exist then    maybe add a cdh and the value
            } else {

                if ($this->isCdhRequired($store)) {
                    $this->stores[$store]['cdh'] = $this->getCookieDomainHash();
                }

                $this->stores[$store][$name] = $value;
            }

        }
    }

    function isCdhRequired($store_name)
    {

        if (isset($this->stores_meta[$store_name])) {
            return $this->stores_meta[$store_name]['cdh_required'];
        }

        return null;
    }

    function set($store, $name, $value, $store_type = '', $is_permanent = false): void
    {

        if (!isset($this->stores[$store])) {
            $this->loadState($store);
        }

        $this->setState($store, $name, $value, $store_type, $is_permanent);

        // persist immediately if the store type is cookie
        if ($this->stores_meta[$store]['type'] === 'cookie') {

            $this->persistState($store);
        }
    }

    function persistState($store): void
    {

        //check to see that store exists.
        if (isset($this->stores[$store])) {
            sdk::debug('Persisting state store: ' . $store . ' with: ' . print_r($this->stores[$store], true));
            // transform state array into a string using proper format
            $cookie_value = null;
            if (is_array($this->stores[$store])) {
                switch ($this->stores_meta[$store]['type']) {

                    case 'cookie':

                        // check for old style assoc format
                        // @todo eliminate assoc style cookie format.
                        if ($this->stores_meta[$store]['format'] === 'assoc') {
                            $cookie_value = $this->implode_assoc('=>', '|||', $this->stores[$store]);
                        } else {
                            $cookie_value = json_encode($this->stores[$store]);
                        }

                        break;

                    default:

                }
            } else {
                $cookie_value = $this->stores[$store];
            }
            // get expiration time
            $time = $this->stores_meta[$store]['expiration'];
            //set cookie
            $this->createCookie($store, $cookie_value, $time, "/", $this->getSetting('cookie_domain'));

        } else {

            sdk::debug("Cannot persist state. No store registered with name $store");
        }
    }

    function setInitialState($store, $value): void
    {
        if ($value) {
            $this->initial_state[$store] = $value;
        }
    }

    function loadState($store, $name = '', $value = '', $store_type = 'cookie'): void
    {
        $possible_values = [];
        //get possible values
        if (!$value && isset($this->initial_state[$store])) {
            $possible_values = $this->initial_state[$store];
        }


        //count values
        $count = count($possible_values);
        // loop through values looking for a domain hash match or just using the last value.
        foreach ($possible_values as $k => $value) {
            // check format of value

            if (strpos($value, "|||")) {
                $value = $this->assocFromString($value);
            } elseif (strpos($value, ":")) {
                $value = json_decode($value);
                $value = (array)$value;
            }

            if (in_array($store, $this->stores_with_cdh)) {

                if (is_array($value) && isset($value['cdh'])) {

                    $runtime_cdh = $this->getCookieDomainHash();
                    $cdh_from_state = $value['cdh'];

                    // return as the cdh's do not match
                    if ($cdh_from_state === $runtime_cdh) {
                        sdk::debug("cdh match:  $cdh_from_state and $runtime_cdh");
                        $this->setState($store, $name, $value, $store_type);
                    } else {
                        // cookie domains do not match, so we need to delete the cookie in the offending domain
                        // which is always likely to be a sub.domain.com and thus HTTP_HOST.
                        // if cookie is not deleted then new cookies set on .domain.com will never be seen by PHP
                        // as only the subdomain cookies are available.
                        sdk::debug("Not loading state store: $store. Domain hashes do not match - runtime: $runtime_cdh, cookie: $cdh_from_state");
                        //owa_coreAPI::debug("deleting cookie: owa_$store");
                        //owa_coreAPI::deleteCookie($store,'/', $_SERVER['HTTP_HOST']);
                        //unset($this->initial_state[$store]);
                        //return;
                    }
                } else {

                    sdk::debug("Not loading state store: $store. No domain hash found.");
                }

            } else {
                // just set the state with the last value
                if ($k === $count - 1) {
                    sdk::debug("loading last value in initial state container for store: $store");
                    $this->setState($store, $name, $value, $store_type);
                }
            }
        }
    }

    function clear($store, $name = ''): void
    {

        if (!isset($this->stores[$store])) {
            $this->loadState($store);
        }

        if (array_key_exists($store, $this->stores)) {

            if (!$name) {

                unset($this->stores[$store]);

                if ($this->stores_meta[$store]['type'] === 'cookie') {

                    $this->deleteCookie($store);
                }

            } else {

                if (array_key_exists($name, $this->stores[$store])) {
                    unset($this->stores[$store][$name]);

                    if ($this->stores_meta[$store]['type'] === 'cookie') {

                        $this->persistState($store);
                    }
                }
            }
        }
    }

    public function getPermExpiration(): float|int
    {
        return time() + 3600 * 24 * 365 * 15;
    }

    public function addStores($array): void
    {
        $this->stores = array_merge($this->stores, $array);
    }

    function getCookieDomainHash($domain = ''): string
    {
        if (!$domain) {
            $domain = $this->getSetting('cookie_domain');
        }

        return $this->crc32AsHex($domain);
    }

    /**
     * Convert Associative Array to String
     *
     * @param string $inner_glue
     * @param string $outer_glue
     * @param array $array
     * @return string
     */
    public static function implode_assoc(string $inner_glue, string $outer_glue, array $array): string
    {
        $output = [];
        foreach ($array as $key => $item) {
            $output[] = $key . $inner_glue . $item;
        }

        return implode($outer_glue, $output);
    }

    private function createCookie($cookie_name, $cookie_value, $expires = 0, $path = '/', $domain = ''): void
    {

        $sameSite = 'lax';

        if (!$domain) {

            $domain = $this->getSetting('cookie_domain');
        }

        if (is_array($cookie_value)) {

            $cookie_value = $this->implode_assoc('=>', '|||', $cookie_value);
        }

        // add namespace
        $cookie_name = sprintf('%s%s', $this->getSetting('ns'), $cookie_name);

        // debug
        sdk::debug(sprintf('Setting cookie %s with values: %s under domain: %s', $cookie_name, $cookie_value, $domain));

        // makes cookie to session cookie only
        if (!$this->getSetting('cookie_persistence')) {
            $expires = 0;
        }

        // check for php version to set same-site attribute.
        //php 7.2
        if (PHP_VERSION_ID < 70300) {

            $path .= '; SameSite=' . $sameSite;
            setcookie($cookie_name, $cookie_value, $expires, $path, $domain);

        } else {
            //php 7.3+
            setcookie($cookie_name, $cookie_value, [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain,
                'samesite' => $sameSite,
                'secure' => false,
                'httponly' => false,
            ]);
        }
    }

    private function deleteCookie($cookie_name): void
    {
        $this->createCookie($cookie_name, false, time() - 3600 * 25);
    }

    private function crc32AsHex($string): string
    {

        $crc = crc32((string) $string);
        //$crc += 0x100000000;
        if ($crc < 0) {
            $crc = 0xFFFFFFFF + $crc + 1;
        }
        return dechex($crc);
    }

    private function assocFromString($string_state)
    {
        $state = null;
        if (!empty($string_state)):

            if (!str_contains($string_state, '|||')):

                return $string_state;

            else:

                $array = explode('|||', $string_state);

                $state = [];

                foreach ($array as $value) {

                    [$realKey, $realValue] = explode('=>', $value);
                    $state[$realKey] = $realValue;

                }

            endif;

        endif;

        return $state;


    }

    private function initializeStores(): void
    {

        // look for access to the raw HTTP cookie string. This is needed because OWA can set settings cookies
        // with the same name under different subdomains. Multiple cookies with the same name are not
        // available under $_COOKIE. Therefor OWA's cookie container must be an array of arrays.
        if (isset($_SERVER['HTTP_COOKIE']) && strpos($_SERVER['HTTP_COOKIE'], ';')) {

            $raw_cookie_array = explode(';', $_SERVER['HTTP_COOKIE']);

            foreach ($raw_cookie_array as $raw_cookie) {

                $nvp = explode('=', trim($raw_cookie));
                $this->cookies[$nvp[0]][] = urldecode($nvp[1]);
            }

        } else {
            // just use the normal cookie global
            if ($_COOKIE && is_array($_COOKIE)) {

                foreach ($_COOKIE as $n => $v) {
                    // hack against other frameworks sanitizing cookie data and blowing away our '>' delimiter
                    // this should be removed once all cookies are using json format.
                    if (strpos($v, '&gt;')) {
                        $v = str_replace("&gt;", ">", $v);
                    }

                    $this->cookies[$n][] = $v;
                }
            }
        }

        // populate owa_cookie container with just the cookies that have the owa namespace.
        $this->cookies = $this->stripParams($this->cookies, $this->getSetting('ns'));

        // merges cookies
        foreach ($this->cookies as $k => $owa_cookie) {

            $this->setInitialState($k, $owa_cookie);
        }
    }

    private function stripParams($params, $ns = '')
    {

        $striped_params = [];

        if (!empty($ns)) {

            $len = strlen($ns);

            foreach ($params as $n => $v) {

                // if namespace is present in param
                if (strstr($n, $ns)) {
                    // strip the namespace value
                    $striped_n = substr($n, $len);
                    //add to striped array
                    $striped_params[$striped_n] = $v;

                }

            }

            return $striped_params;

        } else {

            return $params;
        }

    }


}
