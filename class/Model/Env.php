<?php

namespace Anaplam\Model;

use PDO;
use Anaplam\Model\PDODataModel as PDODataModel;
use Anaplam\Utility as Util;

class Env extends PDODataModel
{
    protected static $_schema = [
        'id'             => parent::INTEGER,
        'rules_flow_id'  => parent::INTEGER,
        'key'            => parent::STRING,
        'value'          => parent::STRING,
        'created_at'     => parent::DATETIME,
        'updated_at'     => parent::DATETIME,
    ];
    protected static $_table_name = 'envs';
    protected static $name = 'env';

    public function create($rules_flow_id, $key, $value)
    {
        $this->rules_flow_id = $rules_flow_id;
        $this->key = $key;
        $this->value = $value;
        return $this->_insert();
    }
    public function isValid()
    {
        return true;
    }
}

