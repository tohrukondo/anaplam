<?php

namespace Anaplam\Controller;

use Exception;
use InvalidArgumentException;
use Anaplam\Utility as Util;

class BaseController
{
    /**
     * protected model
     *
     */
    protected $model;
    protected $situation;
    protected $request;
    protected $response;

    protected function init($situation)
    {
        $this->situation = $situation;
        $this->syslog("begin");
    }
    protected function terminate()
    {
        $this->syslog("end");
    }
    protected function yaml_parse($yaml)
    {
        $info = yaml_parse($yaml);
        if (!$info){
            $info = json_decode($yaml);
            if (!$info){
                throw new InvalidArgumentException("yaml parse error.");
            }
        }
        return $info;
    }
    protected function getMsg($msg)
    {
        return $this->situation . " " . $msg;
    }
    protected function syslog($msg)
    {
        Util::syslog($this->getMsg($msg));
    }
    protected function outputException($e)
    {
        echo yaml_emit([$this->situation => 'failed', 'error' => $e->getMessage()], YAML_UTF8_ENCODING);
        $msg = $this->getMsg('error "' . $e->getMessage() . '"');
        Util::syslog($msg);
    }
    public function __call($name, $args)
    {
        $result = null;
        $this->request = $args[0];
        $this->response = $args[1];
        try {
            $method = '_' . $name;
            $result = $this->$method($this->request, $this->response, $args[2]);
        } catch (Exception $e){
            $this->outputException($e);
        }
        $this->terminate();
        return $result;
    }
}

