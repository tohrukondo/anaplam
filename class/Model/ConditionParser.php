<?php

namespace Anaplam\Model;

use UnexpectedValueException;
use Parle\{Parser, ParserException, Lexer, Token, Stack};
use Anaplam\Utility as Util;

class ConditionParser
{
    private $p;
    private $lex;
    private $stack;
    private $reduces;
    private $env;

    private static $tokens = [
        "NUM" => "[+-]?(?:\\d+\\.?\\d*|\\.\\d+)",
        "STR" => "[\\w-]+",
        "EQ"  => "==",
        "NE"  => "!=",
        "GE"  => ">=",
        "LE"  => "<=",
        "OR"  => "\|\|",
        "AND" => '&&',
        "SQ"  => "'",
        "WQ"  => '\\"',
        "ENV" => 'env\.',
    ];
    private static $nontokens = [
        "'>'" =>  ">",
        "'<'" =>  "<",
        "'!'" =>  "!",
        "'('" => "\\(",
        "')'" => "\\)",
    ];
    private static $oprs = [
        "OR"          => "left",
        "AND"         => "left",
        "EQ NE"       => "left",
        "GE LE GT LT" => "left",
        "'!'"         => "right",
    ];

    function __construct($env = [])
    {
        $this->env = $env ?? [];

        $this->p = new Parser;
        $this->lex = new Lexer;
        $this->stack = new Stack;

        # トークン登録
        foreach(self::$tokens as $token => $regex)
            $this->p->token($token);

        # 演算子の優先順位登録
        foreach(self::$oprs as $oprs => $assoc)
            $this->p->$assoc($oprs);

        # reduce登録
        $this->reduces = [
            $this->p->push("start", "exp")       => function(){ $this->reduce0(); },
            $this->p->push("exp", "'(' exp ')'") => function(){ $this->stack->pop(); $this->reduce1(); },
            $this->p->push("exp", "exp EQ NUM")  => function(){ $this->reduce(function($r, $l){ return ($r == $l); }); },
            $this->p->push("exp", "NUM EQ exp")  => function(){ $this->reduce(function($r, $l){ return ($r == $l); }); },
            $this->p->push("exp", "exp EQ exp")  => function(){ $this->reduce(function($r, $l){ return ($r === $l); }); },
            $this->p->push("exp", "exp NE NUM")  => function(){ $this->reduce(function($r, $l){ return ($r != $l); }); },
            $this->p->push("exp", "NUM NE exp")  => function(){ $this->reduce(function($r, $l){ return ($r != $l); }); },
            $this->p->push("exp", "exp NE exp")  => function(){ $this->reduce(function($r, $l){ return ($r !== $l); }); },
            $this->p->push("exp", "exp GE exp")  => function(){ $this->reduce(function($r, $l){ return ($this->num($r) >= $this->num($l)); }); },
            $this->p->push("exp", "exp LE exp")  => function(){ $this->reduce(function($r, $l){ return ($this->num($r) <= $this->num($l)); }); },
            $this->p->push("exp", "exp '>' exp") => function(){ $this->reduce(function($r, $l){ return ($this->num($r) > $this->num($l)); }); },
            $this->p->push("exp", "exp '<' exp") => function(){ $this->reduce(function($r, $l){ return ($this->num($r) < $this->num($l)); }); },
            $this->p->push("exp", "exp AND exp") => function(){ $this->reduce(function($r, $l){ return ($r && $l); }); },
            $this->p->push("exp", "exp OR exp")  => function(){ $this->reduce(function($r, $l){ return ($r || $l); }); },
            $this->p->push("exp", "'!' exp")     => function(){ $this->reduce1(function($v){ return !$v; }); },
            $this->p->push("exp", "ENV lit")     => function(){ $this->reduce1(function($v){ return $this->reduce_env($v); }); },
            $this->p->push("exp", "SQ lit SQ")   => function(){ $this->stack->pop(); $this->reduce1(); },
            $this->p->push("exp", "WQ lit WQ")   => function(){ $this->stack->pop(); $this->reduce1(); },
        ];
#        var_dump($this->reduces);

        $this->p->push("exp", "NUM | STR");
        $this->p->push("lit", "NUM | STR");
        # Parserビルド
        $this->p->build();
#        $this->p->dump();

        # トークン正規表現登録
        foreach(self::$tokens as $token => $regex)
            $this->lex->push($regex, $this->p->tokenId($token));
        # 一文字リテラル正規表現登録
        foreach(self::$nontokens as $token => $regex)
            $this->lex->push($regex, $this->p->tokenId($token));
        # スキップトークン登録
        $this->lex->push("\\s+", Token::SKIP);
        # lexerビルド
        $this->lex->build();
    }

    # 数値変換
    protected function num($v)
    {
        if (!is_numeric($v)){
            throw new NumericConvertException("env '$v' is not numeric.");
        }
        return $v;
    }
    # env解決
    protected function reduce_env($key)
    {
        $v = $this->env[$key] ?? null;
        if ($v === null){
            throw new UnresolveEnvException("env '" . $key . "' variable could not be resolved.");
        }
        return $v;
    }
    # 変換
    protected function reduce0($reducefunc = null)
    {
        $var = $this->stack->top ?? $this->p->sigil(0);
#        if ($this->stack->top)
#          echo "pop -> ". $this->stack->top . "\n";
        $this->stack->pop();
        if ($reducefunc != null)
            $var = $reducefunc($var);
#        echo "push -> " . $var . "\n";
        $this->stack->push($var);
    }
    # 単項演算子
    protected function reduce1($reducefunc = null)
    {
        $var = $this->stack->top ?? $this->p->sigil(1);
#        if ($this->stack->top)
#          echo "pop -> $this->stack->top\n";
        $this->stack->pop();
        $this->stack->pop();
        if ($reducefunc != null)
            $var = $reducefunc($var);
#        echo "push -> " . $var . "\n";
        $this->stack->push($var);
    }
    # 二項演算子
    protected function reduce($reducefunc)
    {
        $r = $this->stack->top ?? $this->p->sigil(2);
        $this->stack->pop();
        $this->stack->pop();
        $l = $this->stack->top ?? $this->p->sigil(0);
        $this->stack->pop();
#        echo "lvalue = ". $l . ", rvalue = " . $r . "\n";
        $this->stack->push($reducefunc($l, $r));
#        echo "push -> " . $this->stack->top . "\n";
    }
    # 式有効チェック
    public function validate($in)
    {
        if (!$this->p->validate($in, $this->lex)) {
            $this->throwErrorInfo();
        }
    }
    # 式評価
    public function evaluate($in)
    {
#        echo $in . "\n";
#        Util::syslog($in);
        $this->validate($in);
        $this->p->consume($in, $this->lex);

        $this->stack = new Stack;
        try {
            while (Parser::ACTION_ACCEPT != $this->p->action) {
#            echo "trace: ". $this->p->trace()."\n";
                switch ($this->p->action) {
                case Parser::ACTION_SHIFT:
                    $this->stack->push(null);
                    break;
                case Parser::ACTION_REDUCE:
                    ($this->reduces[$this->p->reduceId] ?? function(){})();
                    break;
                case Parser::ACTION_ERROR:
#                echo "ACTION_ERROR\n";
                    $this->throwErrorInfo();
                }
                $this->p->advance();
#            echo "stack size = " . $this->stack->size . ", stack->top = ". $this->stack->top . "\n";
            }
        } catch (UnresolveEnvException $e){
#            Util::syslog($e->getMessage());
            return False;
        }
        $result = null;
        while (!$this->stack->empty){
            $result = $this->stack->top;
#            echo "result = " . $result ."\n";
#            Util::syslog("result = " . $result);
            $this->stack->pop();
        }
        if ($result === True)
            return True;
        else if ($result == False)
            return False;
        throw new UnexpectedValueException("'" . $in . "' is not expected value. evaluated '" . strval($result) . "'.");
    }
    protected function throwErrorInfo()
    {
        $e = $this->p->errorInfo() ?? null;
        if ($e){
#            var_dump($e);
            throw new ParserException("condition parse error '" . ($e->token->value ?? "") . "'.");
        }
    }
}

class UnresolveEnvException extends ParserException
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
class NumericConvertException extends ParserException
{
    public function __construct($msg)
    {
        parent::__construct($msg);
    }
}
