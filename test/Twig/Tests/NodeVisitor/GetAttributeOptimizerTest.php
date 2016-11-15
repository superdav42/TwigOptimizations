<?php

/*
 *
 * (c) David Stone
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Tests_NodeVisitor_GetAttributeOptimizerTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider getTests
     */
    public function testGetAttributeOptimizer($data, $template, $output, $compiledCode, $optimizedCompiledCode, $differentData, $differentOutput)
    {
        $twig = new Twig_Environment(new Twig_Loader_Array(array('template' => $template)), array('cache' => false, 'autoescape' => false));
        $twig->addExtension(new Twig_Optimizations_Extension_GetAttributeOptimizer(false));

        $moduleNode = $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('template')));

        $actualCompiledCode = $twig->compile($moduleNode->getNode('body'));
        
        $this->assertEquals($compiledCode, $actualCompiledCode);

        $actual = $twig->render('template', $data);

        $this->assertEquals($output, $actual);

        $optimizedModuleNode = $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext('template')));

        $actualOptimizedCompiledCode = $twig->compile($optimizedModuleNode->getNode('body'));

        $this->assertEquals($optimizedCompiledCode, $actualOptimizedCompiledCode);

        $actualDifferentData = $twig->render('template', $differentData);

        $this->assertEquals($differentOutput, $actualDifferentData);

    }
    public function getTests()
    {
        return array(
            array(
                array('foo' => new dataHolder('foobar')),
                '{{ foo.bar }}',
                'foobar',
                <<<'EOF'
// line 1
echo $this->env->getExtension('Twig_Optimizations_Extension_GetAttributeOptimizer')->getAttribute($this, 0, (isset($context["foo"]) ? $context["foo"] : null), "bar", $this->getAttribute((isset($context["foo"]) ? $context["foo"] : null), "bar", array()));

EOF
                , <<<'EOF'
// line 1
echo ((($context["foo"] instanceof dataHolder)) ? ($context["foo"]->bar()) : ($this->getAttribute($context["foo"], "bar", array())));

EOF
                ,array('foo' => array('bar' => 'foobar')),
                'foobar'
            ),
            array(
                array('foo' => new dataHolderChild('foobar')),
                '{{ foo.bar }}',
                'foobar',
                <<<'EOF'
// line 1
echo $this->env->getExtension('Twig_Optimizations_Extension_GetAttributeOptimizer')->getAttribute($this, 0, (isset($context["foo"]) ? $context["foo"] : null), "bar", $this->getAttribute((isset($context["foo"]) ? $context["foo"] : null), "bar", array()));

EOF
                , <<<'EOF'
// line 1
echo ((($context["foo"] instanceof dataHolder)) ? ($context["foo"]->bar()) : ($this->getAttribute($context["foo"], "bar", array())));

EOF
                ,array('foo' => array('bar' => 'foobar')),
                'foobar'
            ),
            array(
                array('foo' => array('bar' => 'foobar')),
                '{{ foo.bar }}',
                'foobar',
                <<<'EOF'
// line 1
echo $this->env->getExtension('Twig_Optimizations_Extension_GetAttributeOptimizer')->getAttribute($this, 0, (isset($context["foo"]) ? $context["foo"] : null), "bar", $this->getAttribute((isset($context["foo"]) ? $context["foo"] : null), "bar", array()));

EOF
                , <<<'EOF'
// line 1
echo ((isset($context["foo"][bar])) ? ($context["foo"][bar]) : ($this->getAttribute($context["foo"], "bar", array())));

EOF
                ,array('foo' => new dataHolderChild('foobar')),
                'foobar'
            ),
            array(
                array('foo' => array('bar' => 'foobar')),
                '{{ foo.bar }}',
                'foobar',
                <<<'EOF'
// line 1
echo $this->env->getExtension('Twig_Optimizations_Extension_GetAttributeOptimizer')->getAttribute($this, 0, (isset($context["foo"]) ? $context["foo"] : null), "bar", $this->getAttribute((isset($context["foo"]) ? $context["foo"] : null), "bar", array()));

EOF
                , <<<'EOF'
// line 1
echo ((isset($context["foo"][bar])) ? ($context["foo"][bar]) : ($this->getAttribute($context["foo"], "bar", array())));

EOF
                ,array(),
                ''
            ),
            array(
                array('foo' => new dataHolder('foobar', 'obprop')),
                '{{ foo.prop }}',
                'obprop',
                <<<'EOF'
// line 1
echo $this->env->getExtension('Twig_Optimizations_Extension_GetAttributeOptimizer')->getAttribute($this, 0, (isset($context["foo"]) ? $context["foo"] : null), "prop", $this->getAttribute((isset($context["foo"]) ? $context["foo"] : null), "prop", array()));

EOF
                , <<<'EOF'
// line 1
echo ((($context["foo"] instanceof dataHolder)) ? ($context["foo"]->prop) : ($this->getAttribute($context["foo"], "prop", array())));

EOF
                ,array(),
                ''
            ),
        );
    }
}

class dataHolder
{
    private $bar;
    public $prop;

    public function __construct($bar, $prop = '') {
        $this->bar = $bar;
        $this->prop = $prop;
    }

    public function getBar()
    {
        return $this->bar;
    }
}

class dataHolderChild extends dataHolder
{
    
}
