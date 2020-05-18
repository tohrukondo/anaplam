<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;
use Anaplam\RequiredParamException;

class Rule extends PDODataModel
{
    protected static $_schema = [
        'id'             => parent::INTEGER,
        'name'           => parent::STRING,
        'rules_flow_id'  => parent::INTEGER,
        'flow_id'        => parent::INTEGER,
        'seq'            => parent::INTEGER,
        'conditions'     => parent::ARRAY,
        'created_at'     => parent::DATETIME,
        'updated_at'     => parent::DATETIME,
    ];
    protected static $_table_name = 'rules';
    protected static $name = 'rule';

    public function __construct()
    {
        parent::__construct();
    }

    public function create($rules_flow_id, $rule_data, $seq)
    {
        $requires = ['name', 'flow'];
        foreach ($requires as $key){
            if (!isset($rule_data[$key])){
                throw new RequiredParamException(static::$name, $key);
            }
        }
        $flow = $rule_data['flow'];
        $short = !is_array($flow);
        $flow_id = (new FlowV2Model($rules_flow_id))->getIdByName($short ? $flow : $flow['name']);
        if (!$flow_id){
            throw new ResourceNotFoundException("flow", $rule_data['flow']);
        }
        $this->name = $rule_data['name'];
        $this->rules_flow_id = $rules_flow_id;
        $this->flow_id = $flow_id;
        $this->seq = $seq;
        $this->conditions = $rule_data['conditions'] ?? [];

        // 'conditions' is required except 'default'.
        if (empty($this->conditions) && strcmp($this->name, 'default') != 0){
            throw new RequiredParamException(static::$name, 'conditions');
        }
        // If condition is invalid, throw exception.
        foreach ($this->conditions as $condition){
            (new ConditionParser([]))->validate($condition);
        }
        if ($this->_insert()){
            if (!$short){
                foreach ($flow['locations'] as $module => $location){
                    $module_instance_id = (new ModuleInstanceV2Model())->getIdByFlowIdAndName($flow_id, $module);
                    (new RulesFlowLocation())->create($this->id, $module_instance_id, $location);
                }
            }
            return $this;
        }
        return null;
    }
    public function isValid()
    {
        return true;
    }
    public function evaluateCondition($env)
    {
        foreach((array)$this->conditions as $c){
            #Util::syslog("conditions = " . json_encode($c));
            if (!(new ConditionParser($env))->evaluate($c)){
                return false;
            }
        }
        return true;
    }
}

