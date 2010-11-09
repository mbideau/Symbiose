<?php

namespace Modules\SimpleWebsite\Controllers;

use Falcon\Site\Framework\Controller\Controller;

class HelloController
	extends Controller
{
	public function indexAction($name = null)
	{
		return $this->createResponse('Hello '.htmlentities($name));
	}
}