# TwigOptimizations
Twig extensions which enable some experimental optimizations on compiled code

# Installation
Add to composer
```
composer require dstone/twig-optimizations
```
Register extension in your twig envirnment with something like:
```php
$twig->addExtension(new Twig_Optimizations_Extension_GetAttributeOptimizer());
```
If you are using symfony you can add it as a service definition in config.yml like:
```yml
services:
    twig.extension.optimizations:
        class: Twig_Optimizations_Extension_GetAttributeOptimizer
        tags:
            - { name: twig.extension }
```

# Optimizations
The main optimization done at this time is elimnating the use of the Twig_Template::getAttribute() when possible. Most of the work done by getAttribute involves looking at the type of the twig var and its defined methods. This extension records the class or type of each twig var passed to getAttribute the first time a template is rendered. Then it recompiles the twig template replacing getAttribute calls with a instanceof check and a direct call to the method or fallback to using getAttribute if the twig var uses a different type. The end result is most calls to getAttribute are removed but a few may remain if several different types of objects or arrays are used for a twig var in a template. 
