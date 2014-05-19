<?php
/*
		Nodes
*/

class H2o_Node {
    var $position;
	function __construct($argstring) {}
	
	function render($context, $stream) {}
}

class NodeList extends H2o_Node implements IteratorAggregate  {
	var $parser;
	var $list;
	
	function __construct(&$parser, $initial = null, $position = 0) {
	    $this->parser = $parser;
        if (is_null($initial))
            $initial = array();
        $this->list = $initial;
        $this->position = $position;
	}

	function render($context, $stream) {
		foreach($this->list as $node) {
			$node->render($context, $stream);
		}
	}
	
    function append($node) {
        array_push($this->list, $node);
    }

    function extend($nodes) {
        array_merge($this->list, $nodes);
    }

    function getLength() {
        return count($this->list);
    }
    
    function getIterator() {
        return new ArrayIterator( $this->list );
    }
}

class VariableNode extends H2o_Node {
    private $filters = array(), $expression = array();

	function __construct($variable, $position = 0) {
            $vlen = count($variable);
            for($i=0;(! is_array($variable[$i]) || ! isset($variable[$i][0]) || $variable[$i][0] !== 'expression_end') &&
                     ($variable[$i] !== 'expression_end') &&
                     ($i<$vlen);$i++) {
              $this->expression[] = $variable[$i];
            }
            $this->filters = (is_array($variable[$i]) && isset($variable[$i]['filters']) ) ? $variable[$i]['filters'] : array();
	}

	function render($context, $stream) {
            $exp_value = Evaluator::eval_expression($this->expression,$context);
            $value = $context->applyFilters($exp_value, $this->filters);
            $value = $context->escape($value, array('filters'=>$this->filters));
            $stream->write($value);
	}
}

class CommentNode extends H2o_Node {}

class TextNode extends H2o_Node {
    var $content;
	function __construct($content, $position = 0) {
		$this->content = $content;
		$this->position = $position;
	}
	
	function render($context, $stream) {
		$stream->write($this->content);
	}
	
	function is_blank() {
	    return strlen(trim($this->content));
	}
}


?>
