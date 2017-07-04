<?php
namespace PHPDocsMD;


/**
 * Class that can compute ClassEntity objects out of real classes
 * @package PHPDocsMD
 */
class Reflector implements ReflectorInterface
{
    /**
     * @var string
     */
    private $className;

    /**
     * @param string $className
     */
    function __construct($className) {
        $this->className = $className;
    }

    /**
     * @return \PHPDocsMD\ClassEntity
     */
    function getClassEntity() {
        $classReflection = new \ReflectionClass($this->className);
        $class = $this->createClassEntity($classReflection);

        $publicFunctions = array();
        $protectedFunctions = array();

        foreach($classReflection->getMethods() as $methodReflection) {
            $func = $this->createFunctionEntity($methodReflection, $class);
            if( $func ) {
                if( $func->getVisibility() == 'public' ) {
                    $publicFunctions[$func->getName()] =  $func;
                } else {
                    $protectedFunctions[$func->getName()] = $func;
                }
            }
        }
        ksort($publicFunctions);
        ksort($protectedFunctions);
        $class->setFunctions(array_values(array_merge($publicFunctions, $protectedFunctions)));

        //get variables/properties
        $properties = $classReflection->getProperties();
        foreach($properties as &$prop) {
            $prop = $this->createPropertyEntity($prop, $class);
        }
        $class->setProperties($properties);
        return $class;
    }

    protected function createPropertyEntity(\ReflectionProperty $property, ClassEntity $class)
    {
        $prop = null;
        $prop = new ParamEntity();
        $tags = $this->createEntity($property, $prop);
        $propName = $property->getName();

        $docs = isset($tags['var']['propName']) ? $tags['var'][$propName] : array();
        $declaredType = self::getPropertyType($tags);
        $propDescription = self::getPropertyDescription($tags);
        $prop->setName($propName);
        $prop->setDescription($propDescription);
        $prop->setType($declaredType);
        $prop->setTags($tags);
        return $prop;
    }

    /**
     * @param \ReflectionMethod $method
     * @param ClassEntity $class
     * @return bool|FunctionEntity
     */
    protected function createFunctionEntity(\ReflectionMethod $method, ClassEntity $class)
    {
        $func = new FunctionEntity();
        $tags = $this->createEntity($method, $func);

        if( $this->shouldIgnoreFunction($tags, $method, $class) ) {
            return false;
        }

        $params = array();
        $myparams = $method->getParameters();
        foreach ($method->getParameters() as $param) {
            $paramName = '$'.$param->getName();
            $docs = isset($tags['params'][$paramName]) ? $tags['params'][$paramName] : array();
            $params[$param->getName()] = $this->createParameterEntity($param, $docs);
        }

        if (empty($tags['return'])) {
            $tags['return'] = $this->guessReturnTypeFromFuncName($func->getName());
        }

        $return = explode(' ', $tags['return']);

        $return_type = array_shift($return);
        $return_type = $this->sanitizeDeclaration($return_type, $method->getDeclaringClass()->getNamespaceName());

        $return_desc = trim(implode(' ', $return));

        $func->setReturnType($return_type);
        $func->setReturnDesc($return_desc);
        $func->setParams(array_values($params));
        $func->isStatic($method->isStatic());
        $func->hasInternalTag(isset($tags['internal']));
        $func->setVisibility($method->isPublic() ? 'public' : 'protected');
        $func->isAbstract($method->isAbstract());
        $func->setClass($class->getName());
        $func->setTags($tags);

        return $func;
    }

    /**
     * @param array $tags
     * @param \ReflectionMethod $method
     * @param ClassEntity $class
     * @return bool
     */
    protected function shouldIgnoreFunction($tags, \ReflectionMethod $method, $class)
    {
        return isset($tags['ignore']) ||
                $method->isPrivate() ||
                !$class->isSame($method->getDeclaringClass()->getName());
    }

    /**
     * @param \ReflectionParameter $reflection
     * @param array $docs
     * @return ParamEntity
     */
    private function createParameterEntity(\ReflectionParameter $reflection, $docs)
    {
        // need to use slash instead of pipe or md-generation will get it wrong
        $def = false;
        $type = 'mixed';
        $declaredType = self::getParamType($reflection);
        if( !isset($docs['type']) )
            $docs['type'] = '';

        if( $declaredType && !($declaredType=='array' && substr($docs['type'], -2) == '[]') && $declaredType != $docs['type']) {
            if( $declaredType && $docs['type'] ) {
                $posClassA = end(explode('\\', $docs['type']));
                $posClassB = end(explode('\\', $declaredType));
                if( $posClassA == $posClassB ) {
                    $docs['type'] = $declaredType;
                } else {
                    $docs['type'] = empty($docs['type']) ? $declaredType : $docs['type'].'/'.$declaredType;
                }
            } else {
                $docs['type'] = empty($docs['type']) ? $declaredType : $docs['type'].'/'.$declaredType;
            }
        }

        try {
            $def = $reflection->getDefaultValue();
            $type = $this->getTypeFromVal($def);
            if( is_string($def) ) {
                $def = "`'$def'`";
            } elseif( is_bool($def) ) {
                $def = $def ? 'true':'false';
            } elseif( is_null($def) ) {
                $def = 'null';
            } elseif( is_array($def) ) {
                $def = 'array()';
            }
        } catch(\Exception $e) {}

        $varName = '$'.$reflection->getName();

        if( !empty($docs) ) {
            $docs['default'] = str_replace('`', '', $def);
            if( $type == 'mixed' && $def == 'null' && strpos($docs['type'], '\\') === 0 ) {
                $type = false;
            }
            if( $type && $def && !empty($docs['type']) && $docs['type'] != $type && strpos($docs['type'], '|') === false) {
                if( substr($docs['type'], strpos($docs['type'], '\\')) == substr($declaredType, strpos($declaredType, '\\')) ) {
                    $docs['type'] = $declaredType;
                } else {
                    $docs['type'] = $type.'/'.$docs['type'];
                }
            } elseif( $type && empty($docs['type']) ) {
                $docs['type'] = $type;
            }
        } else {
            $docs = array(
                'descriptions'=>'',
                'name' => $varName,
                'default' => $def,
                'type' => $type
            );
        }

        $param = new ParamEntity();
        $param->setDescription(isset($docs['description']) ? $docs['description']:'');
        $param->setName($varName);
        $param->setDefault($docs['default']);
        $param->setType(empty($docs['type']) ? 'mixed':str_replace(array('|', '\\\\'), array('/', '\\'), $docs['type']));
        return $param;
    }



    /**
     * Tries to find out if the type of the given parameter. Will
     * return empty string if not possible.
     *
     * @example
     * <code>
     *  <?php
     *      $reflector = new \\ReflectionClass('MyClass');
     *      foreach($reflector->getMethods() as $method ) {
     *          foreach($method->getParameters() as $param) {
     *              $name = $param->getName();
     *              $type = Reflector::getParamType($param);
     *              printf("%s = %s\n", $name, $type);
     *          }
     *      }
     * </code>
     *
     * @param \ReflectionParameter $refParam
     * @return string
     */
    static function getParamType(\ReflectionParameter $refParam)
    {
        $export = \ReflectionParameter::export(
            array(
                $refParam->getDeclaringClass()->name,
                $refParam->getDeclaringFunction()->name
            ),
            $refParam->name,
            true
        );

        $export =  str_replace(' or NULL', '', $export);

        $type = preg_replace('/.*?([\w\\\]+)\s+\$'.current(explode('=', $refParam->name)).'.*/', '\\1', $export);
        if( strpos($type, 'Parameter ') !== false ) {
            return '';
        }

        if( $type != 'array' && strpos($type, '\\') !== 0 ) {
            $type = '\\'.$type;
        }

        return $type;
    }

    static function getPropertyType(array $tags)
    {
        if ( isset($tags['var']) && $tags['var'] ) {
            $words = explode(' ', trim($tags['var']));
            return $words[0];
        }
    }

    static function getPropertyDescription(array $tags) {
        if ( isset($tags['var']) && $tags['var'] ) {
            $words = explode(' ', trim($tags['var']));
            array_shift($words);
            $words = implode(' ', $words);
            return $words;
        }
    }

    /**
     * @param string $name
     * @return string
     */
    private function guessReturnTypeFromFuncName($name)
    {
        $mixed = array('get', 'load', 'fetch', 'find', 'create');
        $bool = array('is', 'can', 'has', 'have', 'should');

        foreach ($mixed as $prefix) {
            if (strpos($name, $prefix) === 0 ) {
                return 'mixed';
			}
        }

        foreach ($bool as $prefix) {
            if (strpos($name, $prefix) === 0 ) {
                return 'bool';
			}
        }

        return 'void';
    }

    /**
     * @param string $def
     * @return string
     */
    private function getTypeFromVal($def)
    {
        if( is_string($def) ) {
            return 'string';
        } elseif( is_bool($def) ) {
            return 'bool';
        } elseif( is_array($def) ) {
            return 'array';
        } else {
            return 'mixed';
        }
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflection
     * @param CodeEntity $code
     * @return array
     */
    private function createEntity($reflection, $code)
    {
        $comment = $this->getCleanDocComment($reflection);
        $tags = $this->extractTagsFromComment($comment, 'description', $reflection);
        $code->setName($reflection->getName());
        $code->setDescription(!empty($tags['description']) ? $tags['description']:'');
        $code->setExample( !empty($tags['example']) ? $tags['example']:'');

        if( !empty($tags['deprecated']) ) {
            $code->isDeprecated(true);
            $code->setDeprecationMessage($tags['deprecated']);
        }

        return $tags;
    }

    /**
     * @param \ReflectionClass $reflection
     * @return string
     */
    private function getCleanDocComment($reflection)
    {
    	$comment = $reflection->getDocComment();
        $comment = str_replace(array('/**', '*/'), '', $comment);

        /**
		 * Replace everything that’s in front of the actual text for a comment with a newline.
		 * DocBlocks for methods normally contain a tab character, so we need to account for that, too.
		 * This regex also keeps new lines inside comments.
		 */
        $comment = preg_replace('/(\n?\t?[\s|^]\*\s)/', "\n", $comment);
		$comment = trim($comment);

    	return $comment;
    }

    /**
     * @param string $comment
     * @param string $current_tag
     * @param \ReflectionMethod|\ReflectionClass $reflection
     * @return array
     */
    private function extractTagsFromComment($comment, $current_tag='description', $reflection)
    {
        $ns = $reflection instanceof \ReflectionClass ? $reflection->getNamespaceName() : $reflection->getDeclaringClass()->getNamespaceName();
        $tags = array($current_tag => '');
		$lines = explode(PHP_EOL, $comment);

        foreach ($lines as $line) {
            if ($current_tag !== 'example') {
                $line = trim($line);
            }

            $words = explode(' ', trim($line));

	        // Remove empty array elements and reindex.
	        $words = array_values(array_filter($words));

            if (strpos($words[0], '@') === false) {
                // Append to tag
                $joinWith = $current_tag == 'example' ? PHP_EOL : ' ';

                if ($current_tag !== 'param') {
                    $tags[$current_tag] .= $joinWith . $line;

				// Add description to parameter
                } elseif (isset($current_param_name) && $current_param_name)  {
                	$tags['params'][$current_param_name]['description'] .= $joinWith . $line;
                }
            }
            elseif ($words[0] == '@param') {
            	// Start new tag
                $current_tag = substr($words[0], 1);

                // Get parameter declaration
                if ($paramData = $this->figureOutParamDeclaration($words, $ns)) {
                    list($name, $data) = $paramData;
                    $tags['params'][$name] = $data;

                    $current_param_name = $name;
                }
            }
            else {
                // Start new tag
                $current_tag = substr($words[0], 1);
                $current_param_name = null;

                array_splice($words, 0 ,1);
                if( empty($tags[$current_tag]) ) {
                    $tags[$current_tag] = '';
                }
                $tags[$current_tag] .= trim(join(' ', $words));
            }
        }

        foreach($tags as $name => $val) {
            if( is_array($val) ) {
                foreach($val as $subName=>$subVal) {
                    if( is_string($subVal) )
                        $tags[$name][$subName] = trim($subVal);
                }
            } else {
                $tags[$name] = trim($val);
            }
        }

        return $tags;
    }

    private function figureOutParamDeclaration($words, $ns)
    {
        $param_desc = '';
        $param_type = '';

        if( strpos($words[1], '$') === 0) {
            $param_name = $words[1];
            $param_type = 'mixed';
            array_splice($words, 0, 2);
        } elseif( isset($words[2]) ) {
            $param_name = $words[2];
            $param_type = $words[1];
            array_splice($words, 0, 3);
        }

        if( !empty($param_name) ) {
            $param_name = current(explode('=', $param_name));
            if( count($words) > 1 ) {
                $param_desc = join(' ', $words);
            }

            $param_type = $this->sanitizeDeclaration($param_type, $ns);
            $data = array(
                'description' => $param_desc,
                'name' => $param_name,
                'type' => $param_type,
                'default' => false
            );

            return array($param_name, $data);
        }

        return false;
    }

    /**
     * @param string $param_type
     * @return bool
     */
    private function shouldPrefixWithNamespace($param_type)
    {
        return strpos($param_type, '\\') !== 0 && $this->isClassReference($param_type);
    }

    /**
     * @param string $str
     * @return bool
     */
    private function isClassReference($str)
    {
        $natives = array('mixed', 'string', 'int', 'number', 'bool', 'boolean', 'object', 'mixed', 'false', 'true', 'null', 'array', 'void');
        return !in_array(trim(strtolower($str)), $natives) && strpos($str, ' ') === false;
    }

    /**
     * @param \ReflectionClass $reflection
     * @return ClassEntity
     */
    protected function createClassEntity(\ReflectionClass $reflection)
    {
        $class = new ClassEntity();
        $classTags = $this->createEntity($reflection, $class);
        $class->isInterface($reflection->isInterface());
        $class->isAbstract($reflection->isAbstract());
        $class->hasIgnoreTag(isset($classTags['ignore']));
        $class->setInterfaces(array_keys($reflection->getInterfaces()));

        if ($reflection->getParentClass()) {
            $class->setExtends($reflection->getParentClass()->getName());
            return $class;
        }
        return $class;
    }

    /**
     * @param $param_type
     * @param $ns
     * @return string
     */
    private function sanitizeDeclaration($param_type, $ns, $delim = '|')
    {
        $parts = explode($delim, $param_type);

        foreach($parts as $i => $p) {
            if ($this->shouldPrefixWithNamespace($p)) {
                $p = ClassEntity::sanitizeClassName('\\' . trim($ns, '\\') . '\\' . $p);
            } elseif ($this->isClassReference($p)) {
                $p = ClassEntity::sanitizeClassName($p);
            }

            $parts[$i] = $p;
        }

        return implode('/', $parts);
    }
}
