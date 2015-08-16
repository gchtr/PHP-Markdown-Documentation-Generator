<?php
namespace PHPDocsMD;


/**
 * Object describing a class or an interface
 * @package PHPDocsMD
 */
class ClassEntity extends CodeEntity {

    /**
     * @var \PHPDocsMD\FunctionEntity[]
     */
    private $functions = array();

    /**
     * @var bool
     */
    private $isInterface = false;

    /**
     * @var bool
     */
    private $abstract = false;

    /**
     * @var bool
     */
    private $hasIgnoreTag = false;

    /**
     * @var string
     */
    private $extends = '';

    /**
     * @var array
     */
    private $interfaces = array();

    /**
     * @var array
     */
    private $properties = array();

    /**
     * @param null|bool $toggle
     */
    public function isAbstract($toggle=null)
    {
        if ( $toggle === null ) {
            return $this->abstract;
        } else {
            $this->abstract = (bool)$toggle;
        }
    }

    /**
     * @param null|bool $toggle
     * @return bool
     */
    public function hasIgnoreTag($toggle=null)
    {
        if( $toggle === null ) {
            return $this->hasIgnoreTag;
        } else {
            $this->hasIgnoreTag = (bool)$toggle;
        }
    }

    /**
     * @param null|bool $toggle
     * @return bool
     */
    public function isInterface($toggle=null)
    {
        if( $toggle === null ) {
            return $this->isInterface;
        } else {
            $this->isInterface = (bool)$toggle;
        }
    }

    /**
     * @param string $extends
     */
    public function setExtends($extends)
    {
        $this->extends = self::sanitizeClassName($extends);
    }

    /**
     * @return string
     */
    public function getExtends()
    {
        return $this->extends;
    }

    /**
     * @param \PHPDocsMD\FunctionEntity[] $functions
     */
    public function setFunctions(array $functions)
    {
        $this->functions = $functions;
    }

    /**
     * @param array $implements
     */
    public function setInterfaces(array $implements)
    {
        $this->interfaces = array();
        foreach($implements as $interface) {
            $this->interfaces[] = self::sanitizeClassName($interface);
        }
    }

    /**
     * @return array
     */
    public function getInterfaces()
    {
        return $this->interfaces;
    }

    /**
     * @return \PHPDocsMD\FunctionEntity[]
     */
    public function getFunctions()
    {
        return $this->functions;
    }

    /**
     * @param string $name
     */
    function setName($name)
    {
        parent::setName(self::sanitizeClassName($name));
    }

    /**
     * Check whether this object is referring to given class name or object instance
     * @param string|object $class
     * @return bool
     */
    function isSame($class)
    {
        $className = is_object($class) ? get_class($class) : $class;
        return self::sanitizeClassName($className) == $this->getName();
    }

    /**
     * @param string $name
     * @return string
     */
    public static function sanitizeClassName($name)
    {
        return '\\'.trim($name, ' \\');
    }

    /**
     * Generate a title describing the class this object is referring to
     * @param string $format
     * @return string
     */
    function generateTitle($format='%label%: %name% %extra%')
    {
        $translate = array(
            '%label%' => $this->isInterface() ? 'Interface' : 'Class',
            '%name%' => substr_count($this->getName(), '\\') == 1 ? substr($this->getName(), 1) : $this->getName(),
            '%extra%' => ''
        );

        if( strpos($format, '%label%') === false ) {
            if( $this->isInterface() )
                $translate['%extra%'] = '(interface)';
            elseif( $this->isAbstract() )
                $translate['%extra%'] = '(abstract)';
        } else {
            $translate['%extra%'] = $this->isAbstract() && !$this->isInterface() ? '(abstract)' : '';
        }

        return trim(strtr($format, $translate));
    }

    /**
     * Generates an anchor link out of the generated title (see generateTitle)
     * @return string
     */
    function generateAnchor()
    {
        $title = $this->generateTitle();
        return strtolower(str_replace(array(':', ' ', '\\', '(', ')'), array('', '-', '', '', ''), $title));
    }

    /**
     * @param string $properties
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
    }

    /**
     * @return string
     */
    public function getProperties()
    {
        return $this->properties;
    }
}
