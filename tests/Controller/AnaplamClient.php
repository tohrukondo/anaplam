<?php

namespace Anaplam\Tests;

use Anaplam\Utility as Util;

require_once 'Config.php';

class AnaplamClient
{
    public function __construct()
    {
        Util::syslog("call AnaplamClient construct on " . get_called_class());

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => Config::$base_uri,
            'headers' => ['API-KEY' => Config::$api_key],
            'verify' => false,
            'curl' => [CURLOPT_SSL_VERIFYHOST => false],
            'curl' => [CURLOPT_SSL_VERIFYPEER => false],
        ]);
    }

    // Http client.

    public function getClient()
    {
        return $this->client;
    }

    public function get($testcase, $path, $statusCode = 200)
    {
        $response = $this->getClient()->get($path);
        $testcase->assertSame($statusCode, $response->getStatusCode());
        return yaml_parse((string)$response->getBody());
    }
    public function post($testcase, $path, $params, $statusCode = 200)
    {
        $requestOptions['body'] = yaml_emit($params);
        $response = $this->getClient()->post($path, $requestOptions);
        $testcase->assertSame($statusCode, $response->getStatusCode());
        $result = yaml_parse((string)$response->getBody());

        switch (true){
        case strpos($path, 'modules'):
            break;
        case strpos($path, 'flows'):
        case strpos($path, 'rules'):
            if (strcmp($testcase->getVersion(), 'v2') == 0 && !isset($result['rules']) && !isset($result['error'])){
                $testcase->assertFalse("post result => " . json_encode($result));
            }
            break;
        }
        return $result;
    }
    public function delete($testcase, $path, $statusCode = 200)
    {
        Util::syslog("delete(" . $path . ")");
        $response = $this->getClient()->delete($path);
        $testcase->assertSame($statusCode, $response->getStatusCode());
        return yaml_parse((string)$response->getBody());
    }
}

