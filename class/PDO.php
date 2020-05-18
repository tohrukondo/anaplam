<?php

namespace Anaplam;

class PDO extends \PDO
{
    private $count = 0;

    public function beginTransaction()
    {
        if ($this->count++ == 0){
            try {
                return parent::beginTransaction();
            } catch (Exception $e){
                ++$this->count;
                throw $e;
            }
        }
        return $this->count >= 0;
    }
    public function commit()
    {
        if (--$this->count == 0){
            try {
                return parent::commit();
            } catch (Exception $e){
                ++$this->count;
                throw $e;
            }
        }
        return $this->count >= 0;
    }
    public function rollBack()
    {
        if ($this->count > 0){
            try {
                $this->count = 0;
                return parent::rollBack();
            } catch (Exception $e){
                throw $e;
            }
        }
        $this->count = 0;
        return false;
    }
}

