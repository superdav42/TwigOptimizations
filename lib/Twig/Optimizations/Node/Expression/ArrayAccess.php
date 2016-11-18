<?php

/*
 * This file is part of Twig.
 *
 * (c) 2016 David Stone
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Optimizations_Node_Expression_ArrayAccess extends Twig_Node_Expression
{
    public function __construct(Twig_Node_Expression $node, Twig_Node $name, $lineno)
    {
        parent::__construct(array('node' => $node, 'name' => $name), array('safe' => false), $lineno);

        if ($node instanceof Twig_Node_Expression_Name) {
            $node->setAttribute('always_defined', true);
        }
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->subcompile($this->getNode('node'))
            ->raw('[')
            ->subcompile($this->getNode('name'))
            ->raw(']')
        ;
    }
}
