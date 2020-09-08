<?php

namespace Religisaci\ParserLatte;

class DependencyTemplates
{
    /**
     * @param string $directory
     * @param array $dependency
     * @return array|int
     */
    public function getTemplateDependency($directory = '', $dependency = [], $level = 1)
    {
        $file = file($directory);

        if (empty($dependency)) {
            $includes = DependencyTemplates::getFileIncludes($file);
            if (empty($includes)) {
                //echo 'This file doesnt have includes';
                return 0;
            } else {
                foreach ($includes as $include) {
                    $dir = constant("rootDirectory") . $include;
                    $dependency = DependencyTemplates::getTemplateDependency($dir, $includes);
                }
            }
        }
        //echo 'Nesting into > ' . $directory;
        $includes = DependencyTemplates::getFileIncludes($file);
        if (empty($includes)) {
            //echo 'This file doesnt have includes ';
            return $dependency;
        } else {
            $includesWithLevels = [];
            foreach ($includes as $include) {
                $include = $include . '?level=' . $level . '?parent=' . Startup::getLatteNameFromDir($directory);
                $includesWithLevels[] = $include;
            }
            //echo 'This file does have includes and depedency -- merging';
            $dependency = array_merge($dependency, $includesWithLevels);
            foreach ($includes as $include) {
                $dir = constant("rootDirectory") . $include;
                if (strpos('?level', $dir)) {
                    $split = explode('?level', $dir);
                    $dir = $split[0];
                }
                $dependency = DependencyTemplates::getTemplateDependency($dir, $dependency, $level + 1);
            }
        }

        //echo '<br> No more depedency inside file -- Emerging ';
        return $dependency;
    }

    /**
     * @param $file string[]
     * @return array
     */
    public function getFileIncludes($file)
    {
        $includes = [];

        foreach ($file as $line_num => $line) {
            if (strpos($line, "include") !== FALSE) {
                if (strpos($line, "=>")) {
                    $explodedLine = Variables::multiexplode(["=>", ","], $line);
                    $line = $explodedLine[0];
                }

                $line = trim($line);
                $line = str_replace("{include ", "", $line);
                $line = str_replace("}", "", $line);
                $line = str_replace("'", "", $line);
                $line = trim($line);

                array_push($includes, $line);
            }

            if (strpos($line, "{control") !== FALSE) {
                $forbidenVariables = Variables::getForbiddenVariables($file);

                if (strpos($line, "=>")) {
                    $explodedLine = Variables::multiexplode(["=>", ","], $line);
                    $line = $explodedLine[0];
                }

                if (strpos($line, ":")) {
                    $explodedLine = Variables::multiexplode([':'], $line);
                    $line = $explodedLine[0];
                }

                $line = trim($line);
                $line = str_replace("{control ", "", $line);
                $line = str_replace("}", "", $line);

                $controlTemplateName = '';

                if (strpos($line, "_")) {
                    $controlTemplateName = str_replace('_', '.', $line);

                    $controlTemplateName .= "Component.latte";
                }

                $controlTemplateName = trim(ucfirst($controlTemplateName));

                foreach ($forbidenVariables as $forbidenVariable) {
                    if (preg_match('/'. $forbidenVariable .'/i', $controlTemplateName) === 0) {
                        $controlTemplateName = '';
                    }
                }

                array_push($includes, $controlTemplateName);
            }
        }

        return $includes;
    }
}