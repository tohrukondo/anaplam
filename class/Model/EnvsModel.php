<?php

namespace Anaplam\Model;

use Anaplam\Model;

class EnvsModel extends Model
{
    public function create($id, $datas)
    {
        foreach ($datas as $key => $value){
            (new Env())->create($id, $key, $value);
        }
        return true;
    }
    public function update($id, $datas)
    {
        $this->delete($id);
        $this->create($id, $datas);
    }
    public function get($id)
    {
        $datas = [];
        $index = 0;
        $envs = Env::find_by_params(['rules_flow_id' => $id]);
        foreach ($envs as $env){
            $datas[$env['key']] = $env['value'];
        }
        return $datas;
    }
    public function delete($id)
    {
        return Env::delete_by_params(['rules_flow_id' => $id]);
    }
}

