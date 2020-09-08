<?php

namespace Religisaci\ParserLatte;

class Dependency
{
    public $dir = '';
    public $level = 0;
    public $parent = '';

    /**
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param string $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * Dependency constructor.
     * @param string $dir
     * @param int $level
     * =@param string $parent
     */
    public function __construct($dir, $level, $parent)
    {
        $this->dir = $dir;
        $this->level = $level;
        $this->parent = $parent;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @param string $dir
     */
    public function setDir($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->level = $level;
    }

}