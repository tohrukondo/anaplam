<?php

namespace Anaplam\Tests;

use PHPUnit\Framework\TestCase;
require_once 'Config.php';
require_once 'AnaplamClient.php';

class BaseTestCase extends TestCase
{
    protected static $client;
    protected static $function;

    protected $version = 'v2';

    // PHPUnit Framework

    public static function setUpBeforeClass()
    {
        \Anaplam\Utility::syslog("setUpBeforeClass() called. on " . get_called_class());

        self::clearKubernetes();
        static::$client = new AnaplamClient();
    }
    public static function tearDownAfterClass()
    {
    }

    public function setUp()
    {
        self::clearDatabases();
        self::clearKubernetes();
    }
    public function tearDown()
    {
        self::assertDatabaseFlowEmpty();
        $endpoints = $this->getEndpointService();
        $this->assertCount(1, $endpoints);
        $this->assertEmpty($endpoints[0], "endpoint = " . json_encode($endpoints));
    }

    // static Utilities.

    public static function getClient()
    {
        return static::$client;
    }
    public static function getGuzzleClient()
    {
        return static::$client->getClient();
    }

    protected static function clearDatabases()
    {
        shell_exec("mysql -u" . Config::$user . " -p" . Config::$pass . " < /home/" . 
            Config::$account . "/anaplam-api/db/anaplamv2.sql 2>&1 > /dev/null");
        shell_exec("mysql -u" . Config::$user . " -p" . Config::$pass . " < /home/" . 
            Config::$account . "/anaplam-api/db/anaplamv2_slack.sql 2>&1 > /dev/null");
        shell_exec("mysql -u" . Config::$user . " -p" . Config::$pass . " < /home/" . 
            Config::$account . "/anaplam-api/db/anaplamv2_volume.sql 2>&1 > /dev/null");
    }
    public static function clearKubernetes()
    {
        //shell_exec("kubectl get svc -o name 2> /dev/null | xargs -L 1 -r kubectl delete 2> /dev/null");
        //shell_exec("kubectl get po -o name 2> /dev/null | xargs -L 1 -r kubectl delete 2> /dev/null");
    }
    public static function getKubeNames($type)
    {
        return shell_exec('kubectl get ' . $type . ' -o name 2> /dev/null');
    }
    public static function getEndpointService()
    {
        $cmd = "kubectl get svc -o jsonpath='{range .items[?(.spec.selector.type)]}{.metadata.name}{\"\\n\"}{end}' 2> /dev/null";
        return explode("\n", trim(shell_exec($cmd)));
    }
    public static function getPods($rules_flow, $flow, $module, $location = false)
    {
        $cmd = "kubectl get po";
        if ($rules_flow){
            $cmd .= " --selector=rules_flow=" . $rules_flow;
        }
        if ($flow){
            $cmd .= " --selector=flow=" . $flow;
        }
        if ($module){
            $cmd .= " --selector=module=" . $module;
        }
        if ($location){
            $cmd .= " -o=custom-column=LOCATION:.spec.nodeName";
        }
        $cmd .= ' -o name 2> /dev/null';
        return explode("\n", trim(shell_exec($cmd)));
    }

    // Utilities.
    public function getVersion()
    {
        return $this->version;
    }
    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function getUrl($name = null)
    {
        if (empty(static::$function)){
            throw new \LogicalException("`$function` must be specified by class derived from BaseTestCase.");
        }
        $url = '/anaplam/' . $this->version . '/' . static::$function;

        $names = is_array($name) ? $name : [$name];
        foreach ($names as $val){
            $url = $url . ($val ? '/' . $val : '');
        }
        return $url;
    }

    // delegate client

    public function get($name, $statusCode = 200)
    {
        return static::$client->get($this, $this->getUrl($name), $statusCode);
    }
    public function post($name, $params, $statusCode = 200)
    {
        return static::$client->post($this, $this->getUrl($name), $params, $statusCode);
    }
    public function delete($name, $statusCode = 200)
    {
        return static::$client->delete($this, $this->getUrl($name), $statusCode);
    }

    // Assertions

    protected function assertDatabaseFlowEmpty()
    {
        $tables = [
            'envs',
            'flow_labels',
            'flows',
            'module_instance_destinations',
            'module_instance_params',
            'module_instance_sources',
            'module_instances',
            'rule_flow_locations',
            'rules',
            'rules_flow_labels',
            'rules_flow_locations',
            'rules_flows',
        ];
        foreach ($tables as $table){
           $res = shell_exec("echo 'use anaplam_db; SELECT * FROM `" . $table . "`' | mysql -u" . Config::$user . ' -p' . Config::$pass . ' 2> /dev/null');
           $this->assertEmpty($res, $table . " is not empty.");
        }
    }
}

