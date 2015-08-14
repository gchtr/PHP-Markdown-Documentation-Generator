<?php
namespace PHPDocsMD;


/**
 * Object describing a function
 * @package PHPDocsMD
 */
class FunctionEntity extends CodeEntity {

    /**
     * @var \PHPDocsMD\ParamEntity[]
     */
    private $params = array();

    /**
     * @var string
     */
    private $returnType = 'void';

    /**
     * @var string
     */
    private $visibility = 'public';

    /**
     * @var bool
     */
    private $abstract = false;

    /**
     * @var bool
     */
    private $hasInternalTag = false;

    /**
     * @var bool
     */
    private $isStatic = false;

    /**
     * @var string
     */
    private $class = '';

    /**
     * @param null|bool $toggle
     */
    public function isStatic($toggle=null)
    {
        if ( $toggle === null ) {
            return $this->isStatic;
        } else {
            $this->isStatic = (bool)$toggle;
        }
    }

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
     * @return boolean
     */
    public function hasInternalTag( $toggle = null )
    {
        if( $toggle === null ) {
            return $this->hasInternalTag;
        } else {
            $this->hasInternalTag = (bool)$toggle;
        }
    }

    /**
     * @return bool
     */
    public function hasParams()
    {
        return !empty($this->params);
    }

    /**
     * @param \PHPDocsMD\ParamEntity[] $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * @return \PHPDocsMD\ParamEntity[]
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $returnType
     */
    public function setReturnType($returnType)
    {
        $this->returnType = $returnType;
    }

    /**
     * @return string
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * @param string $visibility
     */
    public function setVisibility($visibility)
    {
        $this->visibility = $visibility;
    }

    /**
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}

