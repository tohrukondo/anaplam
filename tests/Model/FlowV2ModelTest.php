<?php

namespace Anaplam\Tests;

use PHPUnit\Framework\TestCase;
use Anaplam\Model\FlowV2Model;
use UnexpectedValueException;

class FlowV2ModelTestCase extends TestCase
{
    public function testTopologicalSort()
    {
        $this->assertSame([1, 2, 3], FlowV2Model::topologicalSort([], [ 2 => [3], 1 => [2, 3], 3 => [] ]));
        $this->assertSame(null, FlowV2Model::topologicalSort([], [ 2 => [2], 1 => [2, 3], 3 => [] ]));
        $this->assertSame(null, FlowV2Model::topologicalSort([], [ 1 => [2], 2 => [3], 3 => [1] ]));
    }
    /**
    * @test
    *@expectedException RuntimeException
    */
    public function testRuntimeException()
    {
        $this->assertSame([1, 2, 3], FlowV2Model::topologicalSort([], [ 1 => [], 2 => [], 3 => [] ]));
    }
}

