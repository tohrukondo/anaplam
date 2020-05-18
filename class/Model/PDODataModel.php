<?php

namespace Anaplam\Model;

use PDO;
use ArrayAccess;
use DateTime;
use InvalidArgumentException;
use \Anaplam\Model;
use \Anaplam\Utility as Util;
use Anaplam\InvalidParamException;

abstract class PDODataModel extends Model implements ArrayAccess
{
    const BOOLEAN  = 'boolean';
    const INTEGER  = 'integer';
    const DOUBLE   = 'double';
    const FLOAT    = 'double';
    const STRING   = 'string';
    const DATETIME = 'DateTime';
    const ARRAY    = 'array';

    protected $_data = [];
    protected static $_schema = [];
    protected static $_table_name;
    protected static $_id_key = "id";
    protected static $_created_at_key = "created_at";
    protected static $_updated_at_key = "updated_at";

    protected static $name = "pdo model";

    public function offsetSet($offset, $value) {
        $this->$offset = $value;
    }
    public function offsetExists($offset) {
        return isset($this->$offset);
    }
    public function offsetUnset($offset) {
        unset($this->$offset);
    }
    public function offsetGet($offset) {
        return $this->$offset;
    }
    protected function throwPropertyNotSupported($prop)
    {
        throw new InvalidArgumentException('property "' . $prop . '" is not supported by "' . get_class($this) . '"');
    }
    function __get($prop) {
        $schema = static::$_schema[$prop] ?? null;
        if (!$schema){
            $this->throwPropertyNotSupported($prop);
        }
        $v = $this->_data[$prop] ?? null;
        if ($v != null && $schema === self::ARRAY){
            assert(is_string($v));
            $v = json_decode($v, true);
        }
        return $v;
    }
    function __isset($prop) {
        return isset($this->_data[$prop]);
    }
    function __set($prop, $val) {
        $schema = static::$_schema[$prop] ?? null;
        if (!$schema) {
            $this->throwPropertyNotSupported($prop);
        }
        switch ($schema) {
        case self::DATETIME:
            return $this->_data[$prop] = ($val instanceof DateTime) ?  $val : DateTime::createFromFormat('Y-m-d H:i:s', $val);
        case self::ARRAY:
            if (is_array($val)){
                return $this->_data[$prop] = json_encode($val);
            } else if (is_string($val)){
                return $this->_data[$prop] = $val;
            }
        default:
            if ($schema === gettype($val)){
                return $this->_data[$prop] = $val;
            } else if (is_array($val)){
                throw new InvalidParamException(static::$name, $prop, json_encode($val));
            }
            switch ($schema){
            case self::BOOLEAN:
                return $this->_data[$prop] = (bool)$val;
            case self::INTEGER:
                return $this->_data[$prop] = (int)$val;
            case self::DOUBLE:
                return $this->_data[$prop] = (double)$val;
            case self::STRING:
            default:
                return $this->_data[$prop] = (string)$val;
            }
        }
    }
    function toArray() {
        return $this->_data;
    }
    function fromArray(array $arr) {
        foreach ($arr as $key => $val) {
            $this->__set($key, $val);
        }
    }
    protected function get_insert_keys()
    {
        return array_filter(array_keys(static::$_schema), function($v){ return $v != static::$_id_key; });
    }
    protected function get_update_keys()
    {
        return array_filter(array_keys(static::$_schema), function($v){ return $v != static::$_id_key && $v != static::$_created_at_key; });
    }
    public function get_restore_sql()
    {
        $sql = "SELECT * FROM `" . static::$_table_name . "` WHERE `" . static::$_id_key . "`=?";
        return $sql;
    }
    public function get_insert_sql()
    {
        $keys = $this->get_insert_keys();
        $sql = "INSERT INTO `" . static::$_table_name . "` (" .
            join(',', array_map(function($k){ return '`'.$k.'`';}, $keys)) .
            ")" .  " VALUES (". join(',', array_fill(0, count($keys), "?")) .")";
        return $sql;
    }
    public function get_update_sql()
    {
        $keys = $this->get_update_keys();
        $sql = "UPDATE `" . static::$_table_name . "` SET " .
            join(',', array_map(function($k){ return '`'.$k.'`=?';}, $keys)) .
            " WHERE `" . static::$_id_key . "`=?";
        return $sql;
    }
    public function get_delete_sql()
    {
        $sql = "DELETE FROM `" . static::$_table_name . "` WHERE `" . static::$_id_key . "`=?";
        return $sql;
    }

    public function _restore($id = null)
    {
        $id_key = static::$_id_key;
        $id = $id ?? $this->$id_key;
        if (!$id){
            return null;
        }
        $stmt = $this->db->prepare($this->get_restore_sql());
        $stmt->bindValue(1, $id, PDO::PARAM_INT);

        if (!$stmt->execute()){
            throw new RuntimeException("PDO search error!"); // TODO: 
        }
        $stmt->setFetchMode(PDO::FETCH_INTO, $this);
        return $stmt->fetch();
    }
    public function _insert()
    {
        $stmt = $this->db->prepare($this->get_insert_sql());

        $index = 0;
        $keys = $this->get_insert_keys();
        foreach ($keys as $key){
            $this->bindValue($stmt, ++$index, $key, true);
        }
        if (!$stmt->execute()){
            throw new RuntimeException("PDO insertion error!"); // TODO: 
        }
        $id_key = static::$_id_key;
        $this->$id_key = $this->db->lastInsertId();
        return $this;
    }
    public function _update()
    {
        $stmt = $this->db->prepare($this->get_update_sql());

        $index = 0;
        $keys = $this->get_update_keys();
        foreach ($keys as $key){
            $this->bindValue($stmt, ++$index, $key, false);
        }
        $id_key = static::$_id_key;
        $stmt->bindValue(++$index, $this->$id_key);
        if (!$stmt->execute())
            return null;
        return $this;
    }
    public function _delete($id = null)
    {
        $id_key = static::$_id_key;
        $id = $id ?? $this->$id_key;
        if (!$id){
            return false;
        }
        $stmt = $this->db->prepare($this->get_delete_sql());
        $stmt->bindValue(1, $id);
        return $stmt->execute();
    }
    protected function bindValue($stmt, $index, $key, $new)
    {
        if (($new && $key == static::$_created_at_key) || $key == static::$_updated_at_key){
            $this->_data[$key] = Util::getTimeStamp();
        } else if ($new && $key == 'uuid'){
            $this->_data[$key] = Util::createUUID();
        }
        if ((static::$_schema[$key] ?? null) === self::INTEGER){
            $stmt->bindValue($index, $this->_data[$key] ?? null, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($index, $this->_data[$key] ?? null);
        }
    }

    public static function get_find_by_params_sql($params)
    {
        $sql = "SELECT * FROM `" . static::$_table_name . "` WHERE " .
            join(" AND ", array_map(function($k){ return "`" . $k . "`=?"; }, array_keys($params)));
        #Util::syslog($sql);
        return $sql;
    }
    public static function find_by_params($params)
    {
        $class = get_called_class();
        $stmt = (new $class())->db->prepare(static::get_find_by_params_sql($params));
        $index = 0;
        foreach ($params as $key => $value){
            $stmt->bindValue(++$index, $value);
        }
        if (!$stmt->execute()){
            throw new RuntimeArgumentException("PDO search error!"); // TODO: 
        }
        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $class);
        return $stmt->fetchAll();
    }
    public static function get_delete_by_params_sql($params)
    {
        $sql = "DELETE FROM `" . static::$_table_name . "` WHERE " .
            join(" AND ", array_map(function($k){ return "`" . $k . "`=?"; }, array_keys($params)));
        return $sql;
    }
    public static function delete_by_params($params)
    {
        $class = get_called_class();
        $stmt = (new $class())->db->prepare(static::get_delete_by_params_sql($params));
        $index = 0;
        foreach ($params as $key => $value){
            $stmt->bindValue(++$index, $value);
        }
        return $stmt->execute();
    }

    function isValid()
    {
    }
}

