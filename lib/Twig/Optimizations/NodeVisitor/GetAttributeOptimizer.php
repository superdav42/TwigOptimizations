<?php

/*
 * (c) 2016 David Stone
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Twig_Optimizations_NodeVisitor_GetAttributeOptimizer optimizes getAttribute() calls
 * by removing them if possible and replacing them with a direct call to the array index,
 * object property or method.
 *
 *
 *
 * @author David Stone <david@nnucomputerwhiz.com>
 */
class Twig_Optimizations_NodeVisitor_GetAttributeOptimizer extends Twig_BaseNodeVisitor
{

    private $index = 0;

    private $types = array();

    private $cache = array();

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function doEnterNode(Twig_Node $node, Twig_Environment $env)
    {
        if ($node instanceof Twig_Node_Module) {
            $allTypes = $env->getExtension('attr_optimizer')->getTypes();
            $templateName = $node->getAttribute('filename');

            if(isset($allTypes[$templateName])) {
                $this->types = $allTypes[$templateName];
            }
        }
        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(Twig_Node $node, Twig_Environment $env) {
        if ($node instanceof Twig_Node_Expression_GetAttr) {

            if (Twig_Template::ARRAY_CALL === $node->getAttribute('type') || 
                (Twig_Template::METHOD_CALL !== $node->getAttribute('type') && isset($this->types[$this->index]) && 'array' === $this->types[$this->index]['class'])) {

                $node = $this->getArrayAccessNode($node);

            } elseif (isset($this->types[$this->index])) {

                $type = $this->types[$this->index];

                if(Twig_Template::METHOD_CALL !== $node->getAttribute('type') && ($newType = $this->getTypeWithProperty($type['class'], $type['attr']))) {

                    $node =  $this->getObjectPropertyNode($node, $newType);

                } elseif(($newType = $this->getTypeWithMethod($type['class'], $type['attr']))) {

                    if($newType['class'] == 'array') {
                        $node = $this->getArrayAccessNode($node);
                    } else {
                        $node =  $this->getMethodCallNode($node, $newType);
                    }
                }
                
            } else {

                $node = $this->getRecordGetAttributeCallsNode($node);
            }

            $this->index++;
        } elseif ($node instanceof Twig_Node_Module) {
            $this->types = array();
            $this->index = 0;
        }
        return $node;
    }
    
    private function getTypeWithProperty($class, $propertyName)
    {
        if(empty($class)) {
            return null;
        }
        
        $refClass = $this->getReflectionClass($class);

        if($refClass->hasProperty($propertyName)) {

            $prop = $refClass->getProperty($propertyName);

            if($prop->isPublic()) {
                return array('class' => $prop->class, 'attr' => $propertyName);
            }
        }
        return null;
    }

    private function getTypeWithMethod($class, $methodName)
    {
        if(empty($class) || $class == 'array' || is_a($class, 'Twig_Template', true)) {
            return null;
        }

        $refClass = $this->getReflectionClass($class);

        if (($refClass->hasMethod($methodName) &&       ($method = $refClass->getMethod($methodName))       && $method->isPublic()) ||
            ($refClass->hasMethod('get'.$methodName) && ($method = $refClass->getMethod('get'.$methodName)) && $method->isPublic()) ||
            ($refClass->hasMethod('is'.$methodName) &&  ($method = $refClass->getMethod('is'.$methodName))  && $method->isPublic()) ||
            ($refClass->hasMethod('has'.$methodName) && ($method = $refClass->getMethod('has'.$methodName)) && $method->isPublic())
                ) {

            return array('class' => $method->class, 'attr' => $method->getName());

        } elseif ($refClass->hasMethod('__call') && ($method = $refClass->getMethod('__call')) && $method->isPublic()) {
            return array('class' => $method->class, 'attr' => $methodName);
        } elseif ($refClass->implementsInterface('ArrayAccess')) {
            return array('class' => 'array', 'attr' => $methodName);
        }

        return false;
    }

    /**
     *
     * @param string $class
     * @return ReflectionClass
     */
    private function getReflectionClass($class)
    {
        if (!isset( $this->cache[$class])) {
            $this->cache[$class] = new ReflectionClass($class);
        }

        return $this->cache[$class];
    }

    private function getObjectPropertyNode($node, $type)
    {
        $nameNode = clone $node->getNode('node');
        $nameNode->setAttribute('ignore_strict_check', true);

        $testExpr = new Twig_Optimizations_Node_Expression_Binary_InstanceOf(
            $nameNode,
            $type['class'],
            $node->getLine()
        );

        if ($node->getAttribute('is_defined_test')) {
            $attrNode = new Twig_Node_Expression_Constant(true, $node->getLine());
        } else {
            $attrNode = new Twig_Optimizations_Node_Expression_GetProperty(
                clone $node->getNode('node'),
                $type['attr'],
                $node->getLine()
            );
        }


        if($attrNode) {
            return new Twig_Node_Expression_Conditional(
                $testExpr,
                $attrNode,
                $node,
                $node->getLine()
            );
        } else {
            return $node;
        }
    }

    private function getMethodCallNode($node, $type)
    {
        $nameNode = clone $node->getNode('node');
        $nameNode->setAttribute('ignore_strict_check', true);
        $testExpr = new Twig_Optimizations_Node_Expression_Binary_InstanceOf(
            $nameNode,
            $type['class'],
            $node->getLine()
        );

        if ($node->getAttribute('is_defined_test')) {
            $attrNode = new Twig_Node_Expression_Constant(true, $node->getLine());
        } else {
            $attrNode = new Twig_Node_Expression_MethodCall(
                clone $node->getNode('node'),
                $type['attr'],
                $node->getNode('arguments'),
                $node->getLine()
            );
        }

        if($attrNode) {
            return new Twig_Node_Expression_Conditional(
                $testExpr,
                $attrNode,
                $node,
                $node->getLine()
            );
        } else {
            return $node;
        }
    }

    private function getArrayAccessNode($node)
    {
        $originalAttributeNode = $node->getNode('attribute');

        $attrNode = new Twig_Optimizations_Node_Expression_ArrayAccess(
            clone $node->getNode('node'),
            $originalAttributeNode,
            $node->getLine()
        );

        if ($node->getAttribute('is_defined_test')) {
            $simpleAttrNode = new Twig_Node_Expression_Constant(true, $node->getLine());

        } elseif ($originalAttributeNode instanceof Twig_Node_Expression_Function && $originalAttributeNode->getAttribute('name') == 'optimizer_twig_get_attribute') {
            $realGetAttr = $originalAttributeNode->getNode('arguments')->getNode(4);

            $node->setNode('attribute', $realGetAttr);

            $simpleAttrNode = clone $attrNode;
            $simpleAttrNode->setNode('name', $realGetAttr);
        }  else {
            $simpleAttrNode = $attrNode;
        }

        $testExpr = new Twig_Node_Expression_Function(
            'isset',
            new Twig_Node(array($attrNode), array(), $node->getLine()),
            $node->getLine()
        );

        return new Twig_Node_Expression_Conditional(
            $testExpr,
            $simpleAttrNode,
            $node,
            $node->getLine()
        );
    }
    private function getRecordGetAttributeCallsNode($node)
    {
        $nameNode = clone $node->getNode('node');
        $nameNode->setAttribute('ignore_strict_check', true);

        return new Twig_Node_Expression_Function(
            'optimizer_twig_get_attribute',
            new Twig_Node(
                array(
                    new Twig_Node_Expression_Name('_self', $node->getLine()),
                    new Twig_Node_Expression_Constant($this->index, $node->getLine()),
                    $nameNode,
                    $node->getNode('attribute'),
                    $node,
                ),
                array(),
                $node->getLine()
            ),
            $node->getLine()
        );
    }
}
