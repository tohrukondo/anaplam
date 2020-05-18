<?php

namespace Anaplam\Tests;

use PHPUnit\Framework\TestCase;
use Anaplam\Model\ConditionParser;
use Parle\ParserException;
use UnexpectedValueException;

class CondtionParserTestCase extends TestCase
{
    private $parser;

    function setUp()
    {
        $this->parser = new ConditionParser(['mode' => 'low-latency', 'rtt' => 51]);
    }

    function forTestEvaluate(): array {
        return [
            ["!0", True],
            ["'51'", 'UnexpectedValueException'],
            ["52", 'UnexpectedValueException'],
            ["124 == 124", True],
            ["125 != 125", False],
            ["-64 < 12", True],
            ["\"hoge\" == 'hoge'", True],
            ["'HOGE' == 'hoge'", False],
            ["'1' == '2'", False],
            ["!(123 == 456)", True],
            ["0000 == 0", True],
            ["124.5 == 124.50", True],
            [".5 == 0.500", True],
            ["1.5555 != 1.5556", True],
            ["123 < 456", True],
            ["123 >= 45 && 34 > 12", True],
            ["'hoge' == 'hoge'", True],
            ["'hoge' == 'hoe'", False],
            ["env.mode == 'low-latency'", True],
            ["env.mode == 'hoge'", False],
            ["env.rtt > 50", True],
            ["env.rtt <= 50", False],
            ["env.rtt == 51", True],
            ["env.rtt == '51'", False],
            ["env.rtt == 051", True],
            ["env.rtt != 50", True],
            ["env.rtt != 51", False],
            ["env.rtt != '51'", True],
            ["env.rtt > 'ill'", 'Anaplam\Model\NumericConvertException'],
            ["env.rtt < 'ill'", 'Anaplam\Model\NumericConvertException'],
            ["env.nokey == 'hoge'", False],
            ["'envpmode == 'low-latency'", 'Parle\ParserException'],
            ["123 != 456 && 234 != 987", True],
            ["23 != 456 && 234 == 987", False],
            ["45 != 50", True],
            ["100 == '100'", 'Parle\ParserException'],
            ["'45' != 50", True],
            ["100 >= 100", True],
            ["45 <= 45", True],
            ["100 > 45", True],
            ["17 < 45", True],
            ["'1' < '2'", 'Parle\ParserException'],
            ["'mode' == 'low-latency'", False],
            ['"Hoge" == "Hoge" == "Hoge"', False],
            ['"Hoge" == ("Hoge" == "Hoge")', False],
            ['("Hoge" != "Hoe") == ("Hoge" == "Hoge")', True],
            ['(("Hoge" != "Hoe") == "Hoge") == "Hoge"', False],
        ];
    }
    /**
    *   @dataProvider forTestEvaluate
    *
    */
    public function testEvaluate($in, $ev){
        try {
            $result = $this->parser->evaluate($in);
            $this->assertSame($result, (boolean)$ev);
        } catch (ParserException $e){
            $this->assertSame(get_class($e), $ev);
        } catch (Anaplam\Model\NumericConvertException $e){
            $this->assertSame(get_class($e), $ev);
        } catch (UnexpectedValueException $e){
            $this->assertSame(get_class($e), $ev);
        } catch (Exception $e){
            $this->assertFalse($e->getMessage());
        }
    }
}

