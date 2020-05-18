<?php

namespace Anaplam;

use Ramsey\Uuid\Uuid;
use Dotenv\Dotenv;

class Utility
{
    /**
     * public static apikey
     */
    public static $apikey;
    public static $user;

    /**
     * public create uuid 
     *
     * @return uuid
     */
    public static function createUUID()
    {
        return Uuid::uuid4();
    }

    /**
     * public get env
     *
     * @param name
     *
     * @return env value
    */
    public static function getEnv($name)
    {
        static $env;
        if (empty($env)) {
            $env = new Dotenv(__DIR__);
            $env->load();
        }
        return getenv($name);
    }

    /**
     * public get time stamp
     *
     */
    public static function getTimeStamp()
    {
        return date('Y-m-d H:i:s');       
    }

    /**
     * syslog
     *
     * @param msg
     *
     */
    public static function syslog($msg)
    {
        $timestamp = date("Y-m-d H:i:s") . "," . substr(explode(".", (microtime(true) . ""))[1], 0, 3);
        if (empty($msg)) {
            return;
        }
        openlog("anaplam", LOG_ODELAY|LOG_PID, LOG_LOCAL0);
        if (empty(self::getApiKey())) {
            $msg = 'IP ' . ($_SERVER['REMOTE_ADDR'] ?? "localhost") . ' ' . $msg;
        } else if (!empty(self::getUser())) {
            $msg = '(USER:' . self::getUser() . ') IP ' . ($_SERVER['REMOTE_ADDR'] ?? "localhost") . ' ' . $msg;
        } else {
            $msg = '(KEY:' . self::getApiKey() . ') IP ' . ($_SERVER['REMOTE_ADDR'] ?? "localhost") . ' ' . $msg;
        }
        $fp = fopen('/var/log/anaplam/controller_syslog.log','a+');
        if($fp){
          fwrite($fp, "$timestamp:INFO:$msg\n");
          fclose($fp);
        }
        syslog(LOG_INFO, $msg);
    }

    public static function getUser()
    {
        return self::$user;
    }
    public static function setUser($user)
    {
        self::$user = $user;
    }

    /**
     * set api key
     *
     * @param $apikey
     *
     */
    public static function setApiKey($apikey)
    {
        self::$apikey = $apikey;
    }

    /**
     * get api key
     *
     */
    public static function getApiKey()
    {
        return self::$apikey;
    }

    public static function kubernetesNameCheck($resource, $name, $value)
    {
        if (empty($value)) {
            throw new RequiredParamException($resource, $name);
        }
        static $maxlength = 253;
        if (strlen($value) > $maxlength){
            throw new LengthExceedException($resource, $name, $value, $maxlength);
        }
        static $regex = '/^[a-z0-9]+([-\.]+[a-z0-9]+)*$/';
        if (!preg_match($regex, $value, $matches)){
            throw new InvalidParamException($resource, $name, $value);
        }
    }
    public static function kubernetesLabelCheck($resource, $name, $value)
    {
        if (empty($value)) {
            throw new RequiredParamException($resource, $name);
        }
        static $maxlength = 63;
        if (strlen($value) > $maxlength){
            throw new LengthExceedException($resource, $name, $value, $maxlength);
        }
        static $regex = '/^[a-zA-Z0-9]+([-\.]+[a-zA-Z0-9]+)*$/';
        if (!preg_match($regex, $value, $matches)){
            throw new InvalidParamException($resource, $name, $matches[0]);
        }
    }
}

