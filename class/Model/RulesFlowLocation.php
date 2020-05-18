<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use Anaplam\Model\PDODataModel;
use Anaplam\Utility as Util;

class RulesFlowLocation extends PDODataModel
{
    protected static $_schema = [
        'id'                 => parent::INTEGER,
        'rule_id'            => parent::INTEGER,
        'module_instance_id' => parent::INTEGER,
        'location'           => parent::STRING,
        'created_at'         => parent::DATETIME,
        'updated_at'         => parent::DATETIME,
    ];
    protected static $_table_name = 'rules_flow_locations';
    protected static $name = 'rules_flow_locations';

    public function __construct()
    {
        parent::__construct();
    }

    public function create($rule_id, $module_instance_id, $location)
    {
        $this->rule_id = $rule_id;
        $this->module_instance_id = $module_instance_id;
        $this->location = $location;
        return $this->_insert();
    }
    public function isValid()
    {
        return true;
    }
}

