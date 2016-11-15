<?php

/*
 *
 * (c) 2016 David Stone
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Optimizations_Node_Expression_Binary_InstanceOf extends Twig_Node_Expression_Binary
{
    public function __construct(Twig_Node $left, $right, $lineno)
    {
        if(is_string($right)) {
            $right = new Twig_Node_Expression_Constant($right, $lineno);
        }
        parent::__construct($left, $right, $lineno);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->raw('(')
            ->subcompile($this->getNode('left'))
            ->raw(' ')
        ;
        $this->operator($compiler);
        $compiler
            ->raw(' ');
        $right = $this->getNode('right');
        if($right instanceof Twig_Node_Expression_Constant) {
            $compiler->raw($right->getAttribute('value'));
        } else {
            $compiler->subcompile($this->getNode('right'));
        }
        $compiler->raw(')');
    }


    public function operator(Twig_Compiler $compiler)
    {
        return $compiler->raw('instanceof');
    }
}
