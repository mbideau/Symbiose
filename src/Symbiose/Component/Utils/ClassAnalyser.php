<?php

namespace Symbiose\Component\Utils;

class ClassAnalyser
{
	protected $class;
	protected $reflectionClass;
	protected $namespace;
	protected $path;
	
	public function __construct($class)
	{
		/*// add slash before, for classes not namespaced
		if(strrpos($class, '\\') === false) {
			$class = '\\' . $class;
		}
		// removes the slash before classes namespaced
		elseif(substr_count($class, '\\') > 1 && strpos($class, '\\') === 0) {
			$class = substr($class, 1);
		}*/
		$this->class = trim($class, '\\');
		$this->reflectionClass = new \ReflectionClass($this->class);
    	$this->namespace = $this->reflectionClass->getNamespaceName();
    	$this->path = $this->reflectionClass->getFileName();
	}
	
	public function getDependencies($output = false)
	{
		$parents = array('_hierarchy' => array(), '_usage' => array());
        // if the path is empty it is a system class
		if(empty($this->path)) {
			// no dependency
			return $parents;
		}
		$content  = file_get_contents($this->path);
    	$tokens   = token_get_all($content);
        $count    = count($tokens);
        $i        = 0;
        $classAliases = array();
        $currentStatment = '';
        $currentStatmentType = null;
        $storeAlias = false;
        while ($i < $count) {
            $token = $tokens[$i];
            
            /*if(is_array($token)){
            	if($output) echo '[DEBUG] token => ' . $token[1] . ' - ' . token_name($token[0]) . ' : ' .  $token[2] . PHP_EOL;
            }
            else{
            	if($output) echo '[DEBUG] token => ' . $token . PHP_EOL;
            }*/

            
            // if statement ending, save the result
            if($currentStatmentType != null && $this->isTokenEndOfStatement($token)) {
            	if($output) echo '   end of statement [' . $currentStatment . ' - ' . token_name($currentStatmentType) . ']' . PHP_EOL;
            	if(!empty($currentStatment)) {
	            	switch($currentStatmentType) {
	            		case T_USE:
	            			$currentStatment = trim($currentStatment, '\\');
	            			// save statment
	            			if($output) echo '         using class ' . $currentStatment . PHP_EOL;
	            			$parents['_usage'][] = $currentStatment;
	            			// if alias exists
	            			if(!empty($alias)) {
                        		if($output) echo '         adding alias ' . $alias . PHP_EOL;
                        		$classAliases[$alias] = $currentStatment;
                        		$alias = '';
                        		$storeAlias = false;
                        	}
	            			// reset statment
	            			$currentStatment = '';
	            			// token separator
	            			if(is_string($token) && $token == ',') {
	            				// start a new statement (same type)
	            				if($output) echo '   start a new statement (same type : ' . token_name($currentStatmentType) . ')' . PHP_EOL;
	            			}
	            			// real end
	            			else {
	            				// close statement
	            				$currentStatmentType = null;
	            				if($output) echo '   closing statement' . PHP_EOL;
	            			}
	            			break;
	            		case T_EXTENDS:
	            		case T_IMPLEMENTS:
	            			// save the statement (after adding the namespace or replacing it by its alias value)
	            			$classname = $this->correctClassStatement($currentStatment, $classAliases, $parents);
	            			if($output) echo '      parent class ' . $classname . PHP_EOL;
	            			$parents['_hierarchy'][] = $classname;
	            			// reset statment
	            			$currentStatment = '';
	            			// token separator
	            			if(is_string($token) && $token == ',') {
	            				// start a new statement (same type)
	            				if($output) echo '   start a new statement (same type : ' . token_name($currentStatmentType) . ')' . PHP_EOL;
	            			}
	            			// real end
	            			else {
	            				// close statement
	            				$currentStatmentType = null;
	            				if($output) echo '   closing statement' . PHP_EOL;
	            			}
	            			break;
	            	}
            	}
            }
            
            // token is the begining of a class block
            if(is_string($token) && $token == '{') {
            	// stop parsing
            	break;
            }
            // token is an array
            elseif(is_array($token)) {
            	list($tid, $tcontent, $tline) = $token;
            	switch ($tid) {
	            	case T_USE:
	            	case T_EXTENDS:
	                case T_IMPLEMENTS:
	            			if($output) echo '   ' . token_name($tid) . ' token found' . PHP_EOL;
	            			$currentStatmentType = $tid;
	            			$currentStatment = '';
	            			if($output) echo '   starting new statement ' . token_name($currentStatmentType) . PHP_EOL;
	            		break;
	            	case T_STRING:
						if($currentStatmentType != null) {
		            		if($output) echo '      T_STRING token found [' . $tcontent . ' - ' . var_export($storeAlias, true) . " - $i]" . PHP_EOL;
							if($storeAlias) {
								$alias = $tcontent;
							}
							else {
								$currentStatment .= $tcontent;
							}
						}
						else {
							//if($output) echo 'token skiped: ' . $tcontent . "[" . token_name($tid) . ", $tline]". PHP_EOL;
						}
						break;
					case T_NS_SEPARATOR:
						if($currentStatmentType != null) {
							//if($output) echo '      T_NS_SEPARATOR token found' . PHP_EOL;
							$currentStatment .= '\\';
						}
						break;
	            	case T_AS:
	            		if($currentStatmentType == T_USE) {
		            		if($output) echo '      T_AS token found' . PHP_EOL;
		            		$alias = '';
		            		$storeAlias = true;
	            		}
	            		break;
	            	default:
	            		//if($output) echo 'token skiped: ' . $tcontent . "[" . token_name($tid) . ", $tline]". PHP_EOL;
	            		break;
	            }
            }
            // go to next token
            ++$i;
        }
        
        // if we've stopped due to begining of the class block
        if ($i < $count) {
        	$currentStatment = '';
        	$currentStatmentType = null;
        	$startRecording = false;
        	// continue to parse to find class used in functions
        	while ($i < $count) {
        		$token = $tokens[$i];
        		
        		if(is_string($token)) {
        			if($currentStatmentType == T_FUNCTION) {
        				if($token == '(' || $token == ',') {
        					$startRecording = true;
        				}
        				elseif($token == ')') {
        					$startRecording = false;
        					$currentStatmentType = null;
        				}
        			}
        			elseif($currentStatmentType == T_NEW) {
        				if(!empty($currentStatment) && $currentStatment != 'self' && $currentStatment != 'parent') {
	        				// save statment
	        				$classname = $this->correctClassStatement($currentStatment, $classAliases, $parents);
	            			if(!in_array($classname, $parents['_usage'])) {
	        					if($output) echo "      T_NEW \t\t using class $classname" . PHP_EOL;
	        					array_push($parents['_usage'], $classname);
	            			}
        				}
        				$startRecording = false;
        				$currentStatmentType = null;
        			}
        			//echo '[DEBUG] [token] ' . $token . PHP_EOL;
        		}
        		elseif(is_array($token)) {
        			list($tid, $tcontent, $tline) = $token;
        			switch($tid) {
        				case T_FUNCTION:
        				case T_NEW:
        					$currentStatmentType = $tid;
        					$currentStatment = '';
        					$startRecording = false;
        					break;
        				case T_WHITESPACE:
        				case T_VARIABLE;
        					if($startRecording) {
        						if($currentStatmentType == T_FUNCTION) {
        							if(!empty($currentStatment)) {
        								if($currentStatment != 'array') {
	        								// save statment
		        							$classname = $this->correctClassStatement($currentStatment, $classAliases, $parents);
			            					if(!in_array($classname, $parents['_usage'])) {
		        								if($output) echo "      T_FUNCTION \t using class $classname" . PHP_EOL;
		        								array_push($parents['_usage'], $classname);
			            					}
        								}
        								$currentStatment = '';
			        					$startRecording = false;
        							}
        							elseif($tid == T_VARIABLE) {
        								$startRecording = false;
        							}
        						}
        					}
        					break;
        				case T_STRING:
        					if($startRecording || $currentStatmentType == T_NEW) {
        						$currentStatment .= $tcontent;
        						$startRecording = true;
        					}
        					break;
        				case T_NS_SEPARATOR:
        					if($startRecording || $currentStatmentType == T_NEW) {
        						$currentStatment .= '\\';
        						$startRecording = true;
        					}
        					break;
        				case T_DOUBLE_COLON:
        					if(is_array($previous) && $previous[1] != 'parent' && $previous[1] != 'self') {
        						// go back to get the class
        						$currentStatment = '';
        						$stop = false;
        						$j = $i;
        						do {
        							$tback = $tokens[--$j];
        							if(is_string($tback)) {
        								$stop = true;
        							}
        							elseif(is_array($tback)) {
        								list($tbackId, $tbackContent, $tbackLine) = $tback;
        								switch($tbackId) {
        									case T_STRING:
        										$currentStatment = $tbackContent . $currentStatment;
        										break;
        									case T_NS_SEPARATOR:
					        					$currentStatment = '\\' . $currentStatment;
					        					break;
        									case T_WHITESPACE:
        										$stop = true;
        										break;
        								}
        							}
        						}
        						while(!$stop);
        						if(!empty($currentStatment)) {
        							// save statment
        							$classname = $this->correctClassStatement($currentStatment, $classAliases, $parents);
	            					if(!in_array($classname, $parents['_usage'])) {
        								if($output) echo "      T_DOUBLE_COLON \t using class $classname" . PHP_EOL;
        								array_push($parents['_usage'], $classname);
	            					}
        						}
        					}
        					break;
        			}
        		}
        		$previous = $token;
            	// go to next token
        		++$i;
        	}
        }
        
        // cleaning parents
        if(!empty($parents['_usage'])) {
        	foreach($parents['_usage'] as $pu) {
        		if(!class_exists($pu) && !interface_exists($pu)) {
        			if($output) echo '[DEBUG] removing used class/interface ' . $pu . ' (exist: false)' . PHP_EOL;
        			unset($parents['_usage'][array_search($pu, $parents['_usage'], true)]);
        		}
        		elseif($pu == $this->class) {
        			if($output) echo '[DEBUG] removing used class/interface ' . $pu . ' (same, circular)' . PHP_EOL;
        			unset($parents['_usage'][array_search($pu, $parents['_usage'], true)]);
        		}
        	}
        }
        elseif(!empty($parents['_hierarchy'])) {
        	foreach($parents['_hierarchy'] as $ph) {
        		if(!class_exists($ph) && !interface_exists($ph)) {
        			if($output) echo '[DEBUG] removing parent class/interface ' . $ph . ' (exist: false)' . PHP_EOL;
        			unset($parents['_hierarchy'][array_search($ph, $parents['_hierarchy'], true)]);
        		}
        		elseif($ph == $this->class) {
        			if($output) echo '[DEBUG] removing parent class/interface ' . $ph . ' (same, circular)' . PHP_EOL;
        			unset($parents['_hierarchy'][array_search($ph, $parents['_hierarchy'], true)]);
        		}
        	}
        }
        
        /*
        // check if in usage class there is no namespace
        if(!empty($parents['_usage'])) {
        	foreach($parents['_usage'] as $pu) {
        		// if the class doesn't exist
        		if(!class_exists($pu) && !interface_exists($pu)) {
        			echo '[DEBUG] [test] class ' . $pu . " doesn't exist in file " . $this->path . PHP_EOL;
        			// it is maybe a namespace
        			$ns_end = $pu;
        			if(strpos($pu, '\\') !== false) {
        				$ns_end = substr(strrchr($pu, '\\'), 1);
        			}
        			//echo '[DEBUG] [test] its namepsace end could be ' . $ns_end . PHP_EOL;
        			$regex = '#' . $ns_end . '((\\\\\w+)+)#';
        			echo '[DEBUG] [test] regex to match against ' . $regex . PHP_EOL;
        			$matches = array();
        			if(preg_match_all($regex, $content, $matches)) {
        				$numberOfMatches = count($matches[0]);
        				$lastNsPartMatches = array_unique($matches[1]);
        				if(!empty($lastNsPartMatches)) {
        					foreach($lastNsPartMatches as $ns_part) {
        						if(!empty($ns_part)) {
	        						$newPu = $pu . $ns_part;
	        						// if not already registered
	        						if((class_exists($newPu) || interface_exists($newPu)) && !in_array($newPu, $parents['_usage'])) {
	        							// add it to class usage
	        							//echo 'using class ' . $newPu . PHP_EOL;
	        							array_push($parents['_usage'], $newPu);
	        						}
	        						else {
	        							echo '[DEBUG] not using class ' . $newPu . ' (exist: ' . var_export(class_exists($newPu), true) . ')' . PHP_EOL;
	        						}
        						}
        					}
        					unset($parents['_usage'][array_search($pu, $parents['_usage'], true)]);
        					//print_r($parents['_usage']); die();
        				}
        			}
        			else {
        				echo "[DEBUG] Can't find class $pu in file " . $this->path . PHP_EOL;
        			}
        		}
        	}
        }*/
        //print_r($parents); die();
        return $parents;
	}
	
	protected function correctClassStatement($class, $classAliases, $parents)
	{
		$corrected = $class;
		if(strpos($class, '\\') !== false) {
			if(strpos($class, '\\') === 0) {
				$corrected = trim($class, '\\');
			}
			elseif(false === ($corrected = $this->concatUsedNamespace($class, $parents['_usage']))) {
				$corrected = $this->namespace . '\\' . $class;
			}
		}
		else {
			if(array_key_exists($class, $classAliases)) {
				$corrected = $classAliases[$class];
			}
			elseif(false === ($corrected = $this->replaceByUsedClass($class, $parents['_usage']))) {
				if(!empty($this->namespace)) {
					$corrected = $this->namespace . '\\' . $class;
				}
				else {
					$corrected = $class;
				}
			}
		}
		return $corrected;
	}
	
	protected function concatUsedNamespace($class, array $used)
	{
		$classBegining = strstr($class, '\\', true);
		foreach($used as $c) {
			if(preg_match("#\\\\$classBegining\$#", $c)) {
				return $c .'\\' . substr($class, strlen($classBegining) + 1);
			}
		}
		return false;
	}
	
	protected function replaceByUsedClass($class, array $used)
	{
		foreach($used as $c) {
			if(preg_match("#$class\$#", $c)) {
				return $c;
			}
		}
		return false;
	}
	
	protected function isTokenEndOfStatement($token) {
		
		return (
			(is_string($token) && (
				$token == ';'
				|| $token == ','
				|| $token == '{'
			))
			|| (in_array($token[0], array(
            	T_USE,
            	T_EXTENDS,
            	T_IMPLEMENTS
			)))
		);
	}
}