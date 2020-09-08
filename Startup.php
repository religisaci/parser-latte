<?php

namespace Religisaci\ParserLatte;

error_reporting(E_ERROR | E_PARSE);

class Startup
{
    public function startup($latteDir)
    {
        require_once 'DependencyTemplates.php';
        require_once 'Variables.php';
        require_once 'Model/Page.php';
        require_once 'Model/Dependency.php';
        require_once 'Model/DependencyTree.php';

        define("rootDirectory", $latteDir);

        // Získá všechny soubory v nasteveném dir
        $directories = glob(constant("rootDirectory") . "*.latte");

        // Projde adresář, najde všechny složky, zjistí jejich závislosti a uloží do pages
        $pages = Startup::getPagesFromDirectories($directories);

        //Projde pages a zjistí jejich proměnné
        $pages = Startup::updatePagesVariables($pages);

        //Vypíše všechny stránky, jejich závislosti a proměnné
        Startup::printAll($pages);
    }

    /**
     * @param $pages array
     * @return array
     */
    public function getPagesFromDirectories($directories)
    {
        foreach ($directories as $directory) {
            $allDependencies = DependencyTemplates::getTemplateDependency($directory);
            $dependencies = [];
            $levelDepedencies = [];
            foreach ($allDependencies as $dependency) {
                if (strpos($dependency, "?level")) {
                    $split = explode('?', $dependency);
                    $levelSplit  = explode('=', $split[1]);
                    $level = $levelSplit[1];
                    $parentSplit  = explode('=', $split[2]);
                    $parent = $parentSplit[1];
                    if (!Startup::dependenciesContainsSameDir($split[0], $levelDepedencies)) {
                        $levelDepedencies[] = new Dependency($split[0], $level, $parent);
                    } else {
                        $levelDepedencies = Startup::updateLevelByDir($split[0], $level, $levelDepedencies);
                    }
                    $dependencies[] = $split[0];
                } else {
                    $dependencies[] = $dependency;
                }
            }
            $page = new Page(Startup::GetLatteNameFromDir($directory), $directory, array_unique($dependencies), $levelDepedencies);
            $pages[] = $page;
        }
        return $pages;
    }

    /**
     * @param $dir string
     * @param $level int
     * @param $depedencies array
     * @return array
     */
    public function updateLevelByDir ($dir, $level, $depedencies) {
        foreach ($depedencies as $key => $depedency) {
            if ($depedency->getDir() == $dir) {
                $depedency->setLevel($level);
            }
        }
        return $depedencies;
    }

    /**
     * @param $dir string
     * @param $depedencies
     * @return bool
     */
    public function dependenciesContainsSameDir ($dir, $depedencies) {
        foreach ($depedencies as $depedency) {
            if($depedency->getDir() == $dir) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * @param $pages array
     * @return array
     */
    public function updatePagesVariables($pages)
    {
        foreach ($pages as $page) {
            $pageVariables = Variables::getVariablesFromTemplate($page->getDirectory());
//        $dependencyVariables = [];
//        $dependency = $page->getDependency();
//        foreach ($dependency as $depend) {
//            $templateVariables = getVariablesFromTemplate(getDirFromLatteName($depend));
//            if (!empty($templateVariables)) {
//                $dependencyVariables = array_merge($dependencyVariables, $templateVariables);
//            }
//        }
            $variables = array_unique(
                array_merge($pageVariables), SORT_STRING);
            $page->setVariables($variables);
        }

        return $pages;
    }

    /**
     * @param $variables string[]
     * @return string[]
     */
    public function removeArrayBracesFromVariables($variables)
    {
        foreach ($variables as $key => $variable) {
            if (strpos($variable, '[')) {
                $splitVar = explode('[', $variable);
                $variables[] = $splitVar[0];
                unset($variables[$key]);
            }
        }
        return $variables;
    }

    /**
     * @param $dir string
     * @return mixed|string
     */
    public function getLatteNameFromDir($dir)
    {
        $latteName = explode('/', $dir);
        return end($latteName);
    }

    /**
     * @param $objects object[]
     */
    public function printAll($objects)
    {
        Startup::printStyle();
        Startup::printTable($objects);

    }

    public function printTable($objects)
    {
        echo "<hr><br><h1>Pages and dependency: </h1>";

        echo "<table>  
            <tr>
                <th>Template name</th>
                <th>Hierarchy</th>
            </tr> 
          ";
        foreach ($objects as $object) {
            if (preg_match('/Component/i', $object->getName()) === 0) {
                echo "  
                <tr>
                <td valign='top' style='padding-top: 13px;'> " . $object->getName() . "</td>
                <td style = 'color: #e74646'> <h4>&nbsp;This template variables</h4><br />
                ";
                if (!empty($object->getVariables())) {
                    foreach ($object->getVariables() as $item) {
                        if ($item === end($object->getVariables())) {
                            echo($item);
                        }
                        else {
                            echo($item . " | ");
                        }
                    }
                }
                else {
                    echo "<i>Nenalezeny žádné proměnné</i>";
                }
                echo "<br />";
                if (!empty($object->getLeveledDepedency())) {
                    new DependencyTree($object->getLeveledDepedency(), $objects);
                }
                echo "</td></tr>";
            }
        }
        echo "</table>";
    }

    public function printStyle()
    {
        echo "
        <style>
            table { 
                margin: 0 auto; 
                border-collapse: collapse; 
                border-style: hidden; 
                /*Remove all the outside 
                borders of the existing table*/ 
            } 
            table td { 
                padding: 0.5rem; 
                border: 1px solid; 
            } 
            table th { 
                padding: 0.5rem; 
                border: 1px solid; 
            } 
            h4 {
                display: inline-block;
                margin: 5px 0 0 0;
            }
    </style> ";
    }
}