<?php

namespace Falcon\Site\Component\Object;

interface StatefulInterface
{
	public function getState();
	public function setState($state);
	public function updateState();
	public function saveState(array $parameters = array());
	public function restoreState(array $parameters = array());
}