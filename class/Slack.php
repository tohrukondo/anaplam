<?php

namespace Anaplam;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Anaplam\Model\ApiKeyModel;
use Anaplam\Utility as Util;

class Slack
{
    const VERSION = "v0";

    public function get_api_key(Request $request, Response $response, $args)
    {
        # Signing secretチェック
        $response = $this->checkSigningSecret($request, $response, false, $args);
        if ($response->getStatusCode() == 200){
            $params = $request->getParsedBody();
            if ($params['user_id']){
                $apiKeyModel = new ApiKeyModel();
                $api_key = $apiKeyModel->user_key($params['user_id']);
                $body = $response->getBody();
                $body->write($api_key['api_key']);
            } else {
                $response->withStatus(400, 'Bad request');
            }
        }
        return $response;
    }
    public function update_api_key(Request $request, Response $response, $args)
    {
        # Signing secretチェック
        $response = $this->checkSigningSecret($request, $response, true, $args);
        if ($response->getStatusCode() == 200){
            $params = $request->getParsedBody();
            if ($params['user_id']){
                $apiKeyModel = new ApiKeyModel();
                $api_key = $apiKeyModel->user_key($params['user_id']);
                $body = $response->getBody();
                $body->write($api_key['api_key']);
            } else {
                $response->withStatus(400, 'Bad request');
            }
        }
        return $response;
    }
    public function create_join_command(Request $request, Response $response, $args)
    {
        # Signing secretチェック
        $response = $this->checkSigningSecret($request, $response, false, $args);
        if ($response->getStatusCode() == 200){
            $body = $response->getBody();
            $token = $this->makeRandStr(6) . "." . $this->makeRandStr(16);
            $cmd = "/usr/bin/kubeadm token create {$token} --print-join-command";
            $result = shell_exec($cmd);
            $body->write($result);
        }
        return $response;
    }

    private function makeRandStr($length)
    {
        $str = array_merge(range('a', 'z'), range('0', '9'));
        $r_str = null;
        for ($i = 0; $i < $length; $i++) {
            $r_str .= $str[rand(0, count($str) - 1)];
        }
        return $r_str;
    }

    private function checkSigningSecret(Request $request, Response $response, $api_key_force_update, $args)
    {
        $version = self::VERSION;
        $secret = Util::getEnv('SLACK_SIGNING_SECRET');

        $timestamp = $request->getHeader('X-Slack-Request-Timestamp')[0] ?? "";
        if (abs((int)$timestamp - (int)time()) < 300){
            $requestBody = $request->getBody();

            $sigBase = "{$version}:{$timestamp}:{$requestBody}";
            $hash = hash_hmac('sha256', $sigBase, $secret);
            $localSig = "{$version}={$hash}";

            $signature = $request->getHeader('X-Slack-Signature')[0] ?? "";
            if (hash_equals($signature, $localSig)){
                $apiKeyModel = new ApiKeyModel();
                # user_idからapi-keyを取得してみる
                $params = $request->getParsedBody();
                $apiKey = $apiKeyModel->user_key($params["user_id"]);
                # api-keyの強制更新
                if ($api_key_force_update && $apiKey['api_key']){
                    $apiKeyModel->delete($apiKey['api_key']);
                    unset($apiKey['api_key']);
                }
                if ($apiKey['api_key']){
                    $msg = "api-key for user_id({$params['user_id']}) already exist.";
                } else {
                    # API-KEYを自動作成
                    $key = NULL;
                    do {
                        $key = md5(uniqid(mt_rand(), true));
                    } while ($apiKeyModel->exist($key));
                    # データベースにAPI-KEYとuser, user_idを登録
                    if ($apiKeyModel->create($key, $params["user_name"], $params["user_id"])){
                        $msg = "create new api-key for user_id({$params['user_id']}).";
                    } else {
                        $msg = "fail to create api-key for user_id({$params['user_id']}).";
                        $response = $response->withStatus(400, 'Bad request');
                        Util::syslog($msg);
                        return $response;
                    }
                }
                $response = $response->withStatus(200, 'OK');
                Util::syslog($msg);
                return $response;
            }
            $msg = 'Unauthorized Slack Secret';
        } else {
            $msg = 'X-Slack-Request-Timestamp error.';
        }
        $response = $response->withStatus(401, 'Unauthorized');
        echo yaml_emit(array('errors' => array($msg)));
        Util::syslog($msg);
        return $response;
    }

    /**
     * api key exist
     *
     * @param apiKey
     * @return bool
     */
    private function __apiKeyExist($apiKey)
    {
        $apiKeyModel = new ApiKeyModel();
        $ret = $apiKeyModel->exist($apiKey);
        if (!$ret) {
            return false;
        }
        return true;
    }
}

