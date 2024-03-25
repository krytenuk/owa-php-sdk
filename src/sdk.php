<?php

namespace OwaSdk;

use Exception;
use OwaSdk\Sites\SitesClient;
use OwaSdk\Tracker\TrackerClient;

/**
 * Open Web Analytics PHP SDK
 */


/**
 * @method TrackerClient createTracker()
 * @method SitesClient createSites()
 */
class sdk
{

    const VERSION = '1.0';

    private array $config;

    public static bool $inDebug;

    public function __construct($config = [])
    {

        $this->config = $config;

        if ($this->getSetting('debug')) {

            ini_set('display_errors', 1);
            error_reporting(-1);

            self::$inDebug = true;

        }
    }

    public function getSetting($name)
    {

        if (array_key_exists($name, $this->config)) {

            return $this->config[$name];
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function __call($name, array $args)
    {

        $args = $args[0] ?? [];

        if (str_starts_with($name, 'create')) {
            return $this->createClient(substr($name, 6), $args);
        }

        throw new Exception("Unknown method: $name.");
    }


    public function createClient($name, array $config = [])
    {

        // Lookup service in the manifest
        $service = $this->lookupService($name);
        $namespace = $service['namespace'];

        // Create the client
        $client = "OwaSdk\\$namespace\\{$namespace}Client";

        return new $client($this->addToConfig($service, $config));

    }


    private function lookupService($name)
    {

        static $manifest;

        if (empty($manifest)) {

            $manifest = include __DIR__ . '/data/manifest.php';
        }

        $name = strtolower($name);

        if (isset($manifest[$name])) {

            return $manifest[$name];
        }

        return null;
    }

    private function addToConfig($service, $config)
    {

        if (isset($service['namespace'])) {

            $config['namespace'] = $service['namespace'];
        }

        return $config + $this->config;
    }

    static function debug($msg): void
    {

        if (self::$inDebug) {

            if (is_object($msg) || is_array($msg)) {

                $msg = print_r($msg, true);
            }

            error_log($msg . "\n", 3, "./errors.log");
        }
    }

}
