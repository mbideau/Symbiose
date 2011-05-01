<?php

namespace Symbiose\Component\ClassLoader;

interface ClassLoaderInterface
{
	public function getNamespaces();
    
	public function getPrefixes();
    
    /**
     * Registers an array of namespaces
     *
     * @param array $namespaces An array of namespaces (namespaces as keys and locations as values)
     */
    public function registerNamespaces(array $namespaces);
    
    /**
     * Registers a namespace.
     *
     * @param string $namespace The namespace
     * @param string $path      The location of the namespace
     */
    public function registerNamespace($namespace, $path);
    
    /**
     * Registers an array of classes using the PEAR naming convention.
     *
     * @param array $classes An array of classes (prefixes as keys and locations as values)
     */
    public function registerPrefixes(array $classes);
    
    /**
     * Registers a set of classes using the PEAR naming convention.
     *
     * @param string $prefix The classes prefix
     * @param string $path   The location of the classes
     */
    public function registerPrefix($prefix, $path);
    
    /**
     * Registers this instance as an autoloader.
     */
    public function register();
    
    /**
     * Loads the given class or interface.
     *
     * @param string $class The name of the class
     */
    public function loadClass($class);
}