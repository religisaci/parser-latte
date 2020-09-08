<?php

namespace Religisaci\ParserLatte;

class DependencyTree
{
    public $dependencyTree = array();
    public $arrayPages = array();

    /**
     * DependencyTree constructor.
     * @param array $dependencies
     */
    public function __construct($dependencies, $objectsPages)
    {
        $dependenciesArray = json_decode(json_encode($dependencies), true);
        $this->dependencyTree = $dependenciesArray;

        $arrayPages = json_decode(json_encode($objectsPages), true);
        $variablesToUnsetInObjectsPages = ['directory', 'dependency', 'leveledDepedency'];

        foreach ($arrayPages as $key => $item) {
            foreach ($variablesToUnsetInObjectsPages as $variable) {
                unset($arrayPages[$key][$variable]);
            }
        }

        $this->arrayPages = array_values($arrayPages);

        usort($this->dependencyTree,
        function($a, $b)
        {
            return $a['level'] <=> $b['level'];
        });

        $tree = $this->generateTree($this->dependencyTree, $this->dependencyTree[0]['parent']);

        echo $tree;
    }

    /**
     * @param $dependencies
     * @param int $depth
     * @param string $parent
     * @return string
     */
    function generateTree($dependencies, $parent = '', $depth = 1)
    {
        if ($depth > 1000)
        {
            return ''; // ošetření proti mnohoněkolikanásobné rekurzi
        }

        $tree = '';

        for($i = 0, $ni = count($dependencies); $i < $ni; $i++)
        {
            if($dependencies[$i]['parent'] == $parent)
            {
                $key = array_search($dependencies[$i]['dir'], array_column($this->arrayPages, 'name'));

                $tree .= str_repeat('--', $depth);
                $tree .= '<h4>&nbsp;' . $dependencies[$i]['dir'] . '</h4><br/>';

                if ($key === FALSE) {
                    $tree .= '<i>Špatný název šablony pro nalezení proměnných</i><br />';
                }
                else {
                    if (empty($this->arrayPages[$key]['variables'])) {
                        $tree .= '<i>Nenalezeny žádné proměnné</i><br />';
                    }
                    else if (count($this->arrayPages[$key]['variables']) == 1) {
                        $tree .= '<i>Variables: ' . $this->arrayPages[$key]['variables'][0] . '</i><br />';
                    }
                    else {
                        foreach ($this->arrayPages[$key]['variables'] as $variable) {
                            if ($variable === reset($this->arrayPages[$key]['variables'])) {
                                $tree .= '<i>Variables: ' . $variable . ' | ';
                            }
                            else if ($variable === end($this->arrayPages[$key]['variables'])) {
                                $tree .= $variable . '</i><br />';
                            }
                            else {
                                $tree .= $variable . ' | ';
                            }
                        }
                    }
                }

                $tree .= $this->generateTree($dependencies, $dependencies[$i]['dir'], $depth + 1);
            }
        }

        return $tree;
    }
}

