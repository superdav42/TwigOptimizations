<?php

/*
 * (c) 2016 David Stone
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Optimizations_Extension_GetAttributeOptimizer extends Twig_Extension implements Twig_Extension_InitRuntimeInterface
{
    private $types = array();
    
    private $env;

    public function __construct($registerShutdownFunction = true)
    {
        if($registerShutdownFunction) {
            register_shutdown_function(array($this, 'recompileOptimizableTemplates'));
        }
    }

    public function initRuntime(Twig_Environment $environment)
    {
        $this->env = $environment;
    }

    public function getNodeVisitors()
    {
        return array(new Twig_Optimizations_NodeVisitor_GetAttributeOptimizer());
    }

    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('optimizer_twig_get_attribute', array($this, 'getAttribute')),
            new Twig_SimpleFunction('isset', 'isset'),
        );
    }

    public function getName()
    {
        return 'attr_optimizer';
    }

    public function getTypes()
    {
        return $this->types;
    }

    public function getAttribute(Twig_Template $template, $nodeId, $object, $item, $result)
    {
        $templateName = $template->getTemplateName();

        $this->types[$templateName][$nodeId] = array('attr' => (string) $item, 'class' => is_array($object) ? 'array' : (is_object($object) ? get_class($object) : false));

        return $result;
    }

    public function recompileOptimizableTemplates()
    {
        if(empty($this->env)) {
            return;
        }
        $cache = $this->env->getCache(false);

        foreach ($this->env->getExtension('attr_optimizer')->getTypes() as $name => $types) {
            $cls = $this->env->getTemplateClass($name);
            $content = $this->env->compileSource($this->env->getLoader()->getSource($name), $name);
            $key = $cache->generateKey($name, $cls);
            $cache->write($key, $content);
        }
    }
}
