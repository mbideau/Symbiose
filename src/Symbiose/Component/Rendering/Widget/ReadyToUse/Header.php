<?php

namespace  Symbiose\Component\Rendering\Widget\ReadyToUse;

use Symbiose\Component\Rendering\Widget\WidgetTemplate;

class Header
	extends WidgetTemplate
{
	public function __construct($templateManager, array $parameters = array())
	{
		parent::__construct($templateManager);
		$html = <<<EOF
<div id="header">
	<h1>{{ title }}</h1>
</div>
EOF
		;
		$this->setHtml($html);
	}
}