<?php

namespace Religisaci\ParserLatte;

class Page
{
    public $name = '';
    public $directory = '';
    public $dependency = [];
    public $variables = [];
    public $leveledDepedency = [];

    /**
     * @return array
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * @param array $variables
     */
    public function setVariables($variables)
    {
        $this->variables = $variables;
    }


    /**
     * Page constructor.
     * @param $name string
     * @param $directory string
     * @param $dependency string[]
     * @param $leveledDepedency array
     */
    public function __construct($name, $directory, $dependency, $leveledDepedency)
    {
        $this->name = $name;
        $this->directory = $directory;
        $this->dependency = $dependency;
        $this->leveledDepedency = $leveledDepedency;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param string $directory
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;
    }

    /**
     * @return array
     */
    public function getDependency()
    {
        return $this->dependency;
    }

    /**
     * @param array $dependency
     */
    public function setDependency($dependency)
    {
        $this->dependency = $dependency;
    }

    /**
     * @return array
     */
    public function getLeveledDepedency()
    {
        return $this->leveledDepedency;
    }

    /**
     * @param array $leveledDepedency
     */
    public function setLeveledDepedency($leveledDepedency)
    {
        $this->leveledDepedency = $leveledDepedency;
    }

}