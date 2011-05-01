<?php

namespace Modules\SimpleWebsite\Controllers;

use Symbiose\Framework\Controller\Controller;

class IndexController
	extends Controller
{
	public function indexAction()
	{
		return $this->redirect(array('controller' => 'hello'));
	}
}