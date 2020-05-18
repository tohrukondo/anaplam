<?php

namespace Anaplam\Model;

use PDO;
use RuntimeException;
use Anaplam\Model;
use Anaplam\Utility as Util;
use Anaplam\ResourceNotFoundException;
use Anaplam\ResourceDuplicateException;

use Maclof\Kubernetes\Models\Pod;
use Maclof\Kubernetes\Models\Service;

class RulesModel extends Model
{
    protected static $name = 'rule';

    function __construct()
    {
        parent::__construct();
    }
    /*
    *   create from array data.
    */
    public function create($id, $datas)
    {
        // 'name' duplication check.
        $dups = array_filter(
            array_count_values(
                array_map(function($v){ return $v['name']; }, $datas)
            ),
            function($c){ return $c > 1; }
        );
        if ($dups){
            reset($dups);
            throw new ResourceDuplicateException(static::$name, 'name', key($dups));
        }
        $seq = 1;
        $default_rule = null;
        foreach ($datas as $data){
            switch ($data['name']){
            case 'default':
                $default_rule = $data;
                break;
            case 'current':
                throw new RuntimeException("'current' is read only property.");
            default:
                (new Rule())->create($id, $data, $seq++);
            }
        }
        if ($default_rule){
            (new Rule())->create($id, $default_rule, $seq);
        }
        return true;
    }
    public function get($id, $current_rule_id = null)
    {
        $datas = [];
        $current_rule_name = null;
        $sql = "SELECT `i`.`name`, `l`.`location` FROM " .
                    "(`rules_flow_locations` `l` LEFT JOIN `rules` `r` ON `l`.`rule_id` = `r`.`id`) " .
                    "LEFT JOIN `module_instances` `i` ON `l`.`module_instance_id` = `i`.`id` " .
                    "WHERE `r`.`rules_flow_id` = ? AND `r`.`name` = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $id);
        $stmt->bindParam(2, $rule_name);

        $rules = Rule::find_by_params(['rules_flow_id' => $id]);
        foreach ($rules as $rule){
            $data['name'] = $rule_name = $rule['name'];
            $flow = (new FlowModel())->get($rule['flow_id']);
            if (!$flow){
                throw new ResourceNotFoundException('flow', 'id = ' . $rule['flow_id']);
            }
            if (empty($rule['conditions'])){
                unset($data['conditions']);
            } else {
                $data['conditions'] = $rule['conditions'];
            }
            $stmt->execute();
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($locations)){
                $data['flow'] = $flow['name'];
            } else {
                $locs = ['name' => $flow['name']];
                foreach ($locations as $location){
                    $locs['locations'][$location['name']] = $location['location'];
                }
                $data['flow'] = $locs;
            }
            $datas[] = $data;

            if ($current_rule_id == $rule['id']){
                $current_rule_name = $rule['name'];
            }
        }
        if ($current_rule_name){
            $current['name'] = 'current';
            $current['rule'] = $current_rule_name;
            $datas[] = $current;
        }
        return $datas;
    }
    public function delete($id)
    {
        $sql = "DELETE `l` FROM `rules_flow_locations` `l` LEFT JOIN `rules` `r` ON `l`.`rule_id` = `r`.`id` WHERE `r`.`rules_flow_id` = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $id);
        $stmt->execute();

        Rule::delete_by_params(['rules_flow_id' => $id]);
    }

    public function getIdByName($name)
    {
        $rules = Rule::find_by_params(['name' => $name]);
        assert(count($rules) <= 1);
        return $rules[0]['id'];
    }

    public function evaluateCondition($id, $env)
    {
        $rules = Rule::find_by_params(['rules_flow_id' => $id]);
        foreach ($rules as $rule){
            if ($rule->evaluateCondition($env))
                return $rule;
        }
        return null;
    }
}

