<?php

namespace Falcon\Site\Framework\Module;

interface ModuleManagerInterface
{
	public function addModule($modulePath);
	public function addModules(array $modulesPath);
	public function registerModulesDirs(array $modulesDirs);
	public function loadModules();
}