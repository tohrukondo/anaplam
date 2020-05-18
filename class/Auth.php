<?php

namespace Anaplam;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Anaplam\Model\ApiKeyModel;
use Anaplam\Utility as Util;

class Auth
{
    private $routes;

    function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function __invoke(Request $request, Response $response, callable $next)
    {
        foreach ($this->routes as $route){
            $route = preg_quote($route, '/');
            $pattern = "/^{$route}/";
            if (preg_match($pattern, $request->getUri()->getPath())){
                $apiKey = $request->getHeaderLine('HTTP_API_KEY');
                Util::setApiKey($apiKey);
                if (!isset($apiKey) || !$this->__apiKeyExist($apiKey)) {
                    $response = $response->withStatus(401, 'Unauthorized');
                    $msg = 'Unauthorized API-KEY:' . $apiKey;
                    echo yaml_emit(array('errors' => array($msg)));
                    Util::syslog($msg);
                    return $response;
                }
                break;
            }
        }
        $response = $next($request, $response);
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
        # set Slack user name if exist.
        Util::setUser($ret['user'] ?? "");
        return true;
    }
}

