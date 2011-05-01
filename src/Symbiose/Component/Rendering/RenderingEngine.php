<?php

namespace Symbiose\Component;

use Symbiose\Component\Rendering\Exception\RenderingException as Exception;

class RenderingEngine
{
	public function getTemplate($id, $file = null)
	{
		
	}
	
	public function render($filePath, array $parameters = array(), $content = null)
	{
		if(empty($filePath)) {
			throw new Exception("RenderingEngine::render : file path is empty");
		}
		if(!file_exists($filePath)) {
			throw new Exception("RenderingEngine::render : file '$filePath' doesn't exist");
		}
		ob_implicit_flush(false);
		ob_start();
		include $filePath;
		$content = ob_get_contents();
		//ob_end_flush();
		ob_end_clean();
		return $content;
	}
}