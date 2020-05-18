<?php

namespace Anaplam\Tests;

use PHPUnit\Framework\TestCase;
use Anaplam\Model\Rule;

class RuleTestCase extends TestCase
{
    public function testInsertSqlStatement(){
        $rule = new Rule();
        $rule->name = 'rule-name';
        $this->assertSame($rule['name'], $rule->name);
        $rule['rules_flow_id'] = 1;
        $this->assertSame($rule['rules_flow_id'], $rule->rules_flow_id);
        $rule->flow_id = 2;
        $rule->seq = 3;
        $conditions = ['\'hoge\'==\'hoe\''];
        $rule->conditions = $conditions;
        $conditions = [];
        $rule->conditions = $conditions;
        $this->assertSame($rule['conditions'], $conditions);
        $expect = "INSERT INTO `rules` (`name`,`rules_flow_id`,`flow_id`,`seq`,`conditions`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?)";
        $this->assertSame($expect, $rule->get_insert_sql());
    }
    public function testUpdateSqlStatement(){
        $rule = new Rule();
        $expect = "UPDATE `rules` SET `name`=?,`rules_flow_id`=?,`flow_id`=?,`seq`=?,`conditions`=?,`updated_at`=? WHERE `id`=?";
        $this->assertSame($expect, $rule->get_update_sql());
    }
    public function testDeleteSqlStatement(){
        $rule = new Rule();
        $expect = "DELETE FROM `rules` WHERE `id`=?";
        $this->assertSame($expect, $rule->get_delete_sql());
    }
    public function testRestoreSqlStatement(){
        $rule = new Rule();
        $expect = "SELECT * FROM `rules` WHERE `id`=?";
        $this->assertSame($expect, $rule->get_restore_sql());
    }
    public function testFindByParamSqlStatement(){
        $params = ['name' => 'rule-name', 'rules_flow_id' => 1];
        $sql = Rule::get_find_by_params_sql($params);
        $expect = "SELECT * FROM `rules` WHERE `name`=? AND `rules_flow_id`=?";
        $this->assertSame($expect, $sql);
    }
}

