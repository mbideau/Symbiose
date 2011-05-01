<?php

namespace Symbiose\Component\Rendering\Twig;

use Twig_Extension,
	Twig_TokenParser,
	Twig_Token
;

class TwigOptimiseExtension extends Twig_Extension
{
  public function getTokenParsers()
  {
    return array(new OptimiseTokenParser());
  }
 
  // ...
}

class OptimiseTokenParser extends Twig_TokenParser
{
  public function parse(Twig_Token $token)
  {
    $lineno = $token->getLine();
    $name = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
    $this->parser->getStream()->expect(Twig_Token::OPERATOR_TYPE, '=');
    $value = $this->parser->getExpressionParser()->parseExpression();
 
    $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
 
    return new Project_Set_Node($name, $value, $lineno, $this->getTag());
  }

  public function getTag()
  {
    return 'js';
  }
}
