<?php
class StreamWriter {
    var $buffer = array();
    var $close;

    function __construct() {
        $this->close = false;
    }

    function write($data) {
        if ($this->close)
            new Exception('tried to write to closed stream');
        $this->buffer[] = $data;
    }

    function close() {
        $this->close = true;
        return implode('', $this->buffer);
    }
}

class Evaluator {
    static function gt($l, $r) { return $l > $r; }
    static function ge($l, $r) { return $l >= $r; }

    static function lt($l, $r) { return $l < $r; }
    static function le($l, $r) { return $l <= $r; }

    static function eq($l, $r) { return $l == $r; }
    static function ne($l, $r) { return $l != $r; }

    static function plus($l, $r) { return $l + $r; }
    static function minus($l,$r) { return $l - $r; }
    static function mul($l, $r)  { return $l * $r; }
    static function div($l, $r)  { return $l / $r; }
    static function mod($l, $r)  { return $l % $r; }

    static function not_($bool) { return !$bool; }
    static function and_($l, $r) { return ($l and $r); }
    static function or_($l, $r) { return ($l or $r); }

    static $op_precedence;

    static function higher_op_on_stack( $op, $stack ) {
        if ( count($stack) < 1 ) return false;
        else return Evaluator::$op_precedence[end($stack)] > Evaluator::$op_precedence[$op];
    }

    static function eval_op_stack( &$op_stack, &$number_stack, &$pos ) {
        $op = array_pop($op_stack);
        if ( $op == 'not' ) {
            $l = array_pop($number_stack);
            if ( is_null($l) ) throw new Exception("Not enough arguments to $op at $pos");
            $pos = $pos-2;
            return ! $l;
        } else {
            $r = array_pop($number_stack);
            $l = array_pop($number_stack);
            if ( is_null($r) or is_null($l) ) throw new Exception("Not enough arguments to $op at $pos");
            $pos = $pos-3;
            return call_user_func(array("Evaluator", $op), $l, $r);
        }
    }

    static function eval_expression( $args, $context ) {
        $op_stack = array();
        $num_stack = array();
        $expression_pos = 0; // Tracks position in the expression for better error reporting.
        $back_track_pos = 0; // Tracks position in the expression when backtracking for better error reporting.
        foreach( $args as $arg ) {
            $expression_pos++;
            if ( (is_array($arg) && isset($arg['operator'])) ) {
                $back_track_pos = $expression_pos;
                while ( Evaluator::higher_op_on_stack( $arg['operator'], $op_stack ) ) {
                    $val = Evaluator::eval_op_stack( $op_stack, $num_stack, $back_track_pos );
                    $num_stack[] = $val;
                }
                $op_stack[] = $arg['operator'];
            } else if ( (is_array($arg) && isset($arg['parentheses'])) ) {
                if ( $arg['parentheses'] === '(' ) $op_stack[] = '(';
                else {
                    $back_track_pos = $expression_pos;
                    while( $op = array_pop( $op_stack ) ) {
                        if ( $op === '(' ) break;
                        $op_stack[] = $op;
                        $num_stack[] = Evaluator::eval_op_stack( $op_stack, $num_stack, $back_track_pos );
                    }
                    if ( is_null($op) ) throw new Exception("No opening paren for ')' at {$expression_pos}");
                }
            } else $num_stack[] = $context->resolve($arg);
        }
        $back_track_pos = $expression_pos;
        while ( count( $op_stack ) > 0 ) $num_stack[] = Evaluator::eval_op_stack( $op_stack, $num_stack, $back_track_pos );
        return array_pop($num_stack);
    }

    # Currently only support single expression with no preceddence ,no boolean expression
    #    [expression] =  [optional binary] ? operant [ optional compare operant]
    #    [operant] = variable|string|numeric|boolean
    #    [compare] = > | < | == | >= | <=
    #    [binary]    = not | !
    static function exec($args, $context) {
        return Evaluator::eval_expression( $args, $context );
    }
}

Evaluator::$op_precedence = array(
            '('   => -1,
            'not' => 0, 'or_'=>0, 'and_'=>0,
            'eq'  => 1, 'gt' => 1, 'lt' => 1, 'ge' => 1, 'le' => 1,
            'mod' => 2,
            'plus'  => 3, 'minus' => 3,
            'mul'  => 4, 'div' => 4
);

/**
 * $type of token, Block | Variable
 */
class H2o_Token {
    function __construct ($type, $content, $position) {
        $this->type = $type;
        $this->content = $content;
        $this->result='';
        $this->position = $position;
    }

    function write($content){
        $this->result= $content;
    }
}

/**
 * a token stream
 */
class TokenStream  {
    var $pushed;
    var $stream;
    var $closed;
    var $c;

    function __construct() {
        $this->pushed = array();
        $this->stream = array();
        $this->closed = false;
    }

    function pop() {
        if (count($this->pushed))
        return array_pop($this->pushed);
        return array_pop($this->stream);
    }

    function feed($type, $contents, $position) {
        if ($this->closed)
            throw new Exception('cannot feed closed stream');
        $this->stream[] = new H2o_Token($type, $contents, $position);
    }

    function push($token) {
        if (is_null($token))
            throw new Exception('cannot push NULL');
        if ($this->closed)
            $this->pushed[] = $token;
        else
            $this->stream[] = $token;
    }

    function close() {
        if ($this->closed)
        new Exception('cannot close already closed stream');
        $this->closed = true;
        $this->stream = array_reverse($this->stream);
    }

    function isClosed() {
        return $this->closed;
    }

    function current() {
        return $this->c ;
    }

    function next() {
        return $this->c = $this->pop();
    }
}

class H2o_Info {
    var $h2o_safe = array('filters', 'extensions', 'tags');
    var $name = 'H2o Template engine';
    var $description = "Django inspired template system";
    var $version = H2O_VERSION;

    function filters() {
        return array_keys(h2o::$filters);
    }

    function tags() {
        return array_keys(h2o::$tags);
    }

    function extensions() {
        return array_keys(h2o::$extensions);
    }
}

/**
 * Functions
 */
function sym_to_str($string) {
    return substr($string, 1);
}

function is_sym($string) {
    return isset($string[0]) && $string[0] === ':';
}

function symbol($string) {
    return ':'.$string;
}

function strip_regex($regex, $delimiter = '/') {
    return substr($regex, 1, strrpos($regex, $delimiter)-1);
}
?>
