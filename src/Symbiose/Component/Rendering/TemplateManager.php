<?php

namespace Symbiose\Component\Rendering;

use Twig_Environment as BaseTemplateManager,
	Twig_LoaderInterface,
	Twig_LexerInterface,
	Twig_ParserInterface,
	Twig_CompilerInterface
;

class TemplateManager
	extends BaseTemplateManager
{
	public function __construct(Twig_LoaderInterface $loader = null, $options = array(), Twig_LexerInterface $lexer = null, Twig_ParserInterface $parser = null, Twig_CompilerInterface $compiler = null)
    {
        return parent::__construct($loader, $options, $lexer, $parser, $compiler);
    }
    
	public function loadTemplate($name)
    {
        $cls = $this->getTemplateClass($name);

        if (isset($this->loadedTemplates[$cls])) {
            return $this->loadedTemplates[$cls];
        }

        if (!class_exists($cls, false)) {
            if (false === $cache = $this->getCacheFilename($name)) {
                $source = $this->loader->getSource($name);
                $compiledSource = $this->compileSource($source, $name);
                /*
                var_dump(array(
                	'source' => $source,
                	'compiled source' => $compiledSource
                ));
                */
            	eval('?>'.$compiledSource);
            } else {
                if (!file_exists($cache) || ($this->isAutoReload() && !$this->loader->isFresh($name, filemtime($cache)))) {
                    $source = $this->loader->getSource($name);
                	$compiledSource = $this->compileSource($source, $name);
	                /*
                	var_dump(array(
	                	'source' => $source,
	                	'compiled source' => $compiledSource
	                ));
	                */
                	$this->writeCacheFile($cache, $compiledSource);
                }

                require_once $cache;
            }
        }

        if (!$this->runtimeInitialized) {
            $this->initRuntime();
        }

        return $this->loadedTemplates[$cls] = new $cls($this);
    }
}