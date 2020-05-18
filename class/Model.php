<?php

namespace Anaplam;

use Anaplam\Kubernetes;
use Anaplam\Utility as Util;

class Model
{
    /**
     * database object
     *
     * @var PDO
     */
    protected static $_db;
    protected $db;

    /**
     * protected k8s
     *
     */
    protected static $_k8s;
    protected $k8s;

    /**
     * protected errors
     *
     */
    protected $errors;


    public static function static_initialize()
    {
        $host = Util::getEnv('DB_HOST');
        $user = Util::getEnv('DB_USER');
        $password = Util::getEnv('DB_PASS');
        $dbname = Util::getEnv('DB_NAME');
        static::$_db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8;", $user, $password);
        static::$_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        static::$_k8s = new Kubernetes();
    }

    /**
     * construct
     *
     */
    public function __construct()
    {
        $this->db = &static::$_db;
        $this->k8s = &static::$_k8s;
        $this->errors = [];
    }

    /**
     * get errors
     *
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * set errors
     *
     * @param $msg
     *
     */
    public function setErrors($msg)
    {
        $this->errors[] = $msg;
        return;
    }
}

Model::static_initialize();

