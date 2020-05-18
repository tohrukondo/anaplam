<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use InvalidArgumentException;
use DateTime;
use Anaplam\Model\PDODataModel as PDODataModel;
use Anaplam\Model\FlowV2Model as FlowV2Model;
use Anaplam\Utility as Util;
use Anaplam\ResourceAlreadyExistException;
use Anaplam\ResourceNotFoundException;
use Anaplam\RequiredParamException;

class RulesFlow extends PDODataModel
{
    protected static $_schema = [
        'id'              => parent::INTEGER,
        'name'            => parent::STRING,
        'description'     => parent::STRING,
        'uuid'            => parent::STRING,
        'current_rule_id' => parent::INTEGER,
        'node_port'       => parent::INTEGER,
        'target_port'     => parent::INTEGER,
        'protocol'        => parent::STRING,
        'created_at'      => parent::DATETIME,
        'updated_at'      => parent::DATETIME,
    ];
    protected static $_table_name = 'rules_flows';
    protected static $name = 'rule flow';

    protected function getMappedName($key)
    {
        static $maps = [
            "node_port"   => "nodePort",
            "target_port" => "targetPort",
            "created_at"  => "creationTimestamp",
            "updated_at"  => "lastModifiedTimestamp"
        ];
        return $maps[$key] ?? $key;
    }

    public function __construct()
    {
        parent::__construct();
    }
    public function create($info)
    {
        $this->parse($info);

        static $usedchecks = ['name', 'node_port'];
        foreach ($usedchecks as $key){
            if (!empty(RulesFlow::find_by_params([$key => $this->$key]))){
                throw new ResourceAlreadyExistException(static::$name, $this->getMappedName($key), $this->$key);
            }
        }
        $this->current_rule_id = null;
        return $this->_insert() ?? null;
    }
    public function update($info)
    {
        $this->parse($info);

        $rules_flows = RulesFlow::find_by_params(['name' => $this->name]);
        if (empty($rules_flows)){
            throw new ResourceNotFoundException(static::$name, $this->name);
        }
        // node_port duplicate check.
        $sql = "SELECT * FROM `rules_flows` WHERE `id` != ? AND `node_port` = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $rules_flows['id']);
        $stmt->bindValue(1, $this->node_port);
        if ($stmt->execute() && $stmt->fetch()){
            throw new ResourceAlreadyExistException(static::$name, 'nodePort', $this->node_port);
        }
        static $inherits = ['id', 'uuid'];
        $rules_flow = $rules_flows[0];
        foreach (static::$_schema as $k => $v){
            if (isset($rules_flow->$k) && (!isset($this->$k) || in_array($k, $inherits, true))){
                $this->$k = $rules_flow->$k;
            }
        }
        return $this->_update();
    }
    protected function parse($info)
    {
        static $requires = ['name', 'node_port'];
        static $keys = ['name', 'node_port', 'description'];
        foreach ($keys as $key){
            $mappedName = $this->getMappedName($key);
            $this->$key = $info['metadata'][$mappedName] ?? null;
            if (empty($this->$key) && in_array($key, $requires, true)){
                throw new RequiredParamException(static::$name, $mappedName);
            }
        }
    }
    public function get($id)
    {
        if (!$this->_restore($id)){
            return null;
        }
        static $ignores = [ "id", "current_rule_id" ];
        foreach (static::$_schema as $key => $type){
            if (array_search($key, $ignores) === false){
                $map_name = $this->getMappedName($key);
                if ($type == self::INTEGER){
                    $info['metadata'][$map_name] = (int)$this->$key;
                } else {
                    $info['metadata'][$map_name] = $this->$key;
                }
            }
        }
        return $info;
    }
    public function isValid()
    {

    }
}
