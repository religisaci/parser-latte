<?php

namespace Religisaci\ParserLatte;

class Variables
{
    /**
     * @param $array string[]
     * @param $chars string[]
     * @return array
     */
    public function explodeArrayItemsByChar($array, $chars)
    {
        foreach ($chars as $char) {
            foreach ($array as $key => $item) {
                if (strpos($item, $char)) {
                    unset($array[$key]);
                    $items = explode($char, $item);
                    if (strpos($items[0], '$') && strpos($items[1], '$')) {
                        array_push($array, $items[0], $items[1]);
                    } elseif (strpos($items[0], '$') && !strpos($items[1], '$')) {
                        $array[] = $items[0];
                    } elseif (!strpos($items[0], '$') && strpos($items[1], '$')) {
                        $array[] = $items[1];
                    } else {
                        continue;
                    }
                }
            }
        }
        return $array;
    }

    /**
     * @param $template string
     * @return array
     */
    public function getVariablesFromTemplate($template)
    {
        $conditionTags = ['if', 'elseif', 'switch', 'ifset'];
        $variables = [];
        $directory = $template;
        $template = file($directory);

        $unwantedVariables = Variables::getUnwantedVarFromTemplate($template);
        $simpleVariables = Variables::getSimpleVariables($template, $unwantedVariables);
        $conditionVariables = Variables::getVariablesFromConditions($template, $conditionTags, $unwantedVariables);
        $loopVariables = Variables::getVariablesFromLoops($template, ['foreach'], $unwantedVariables);
        $resourceManagerVariables = Variables::getVariablesFromResourceManagerTags($template);
        $variables = array_merge($variables, $simpleVariables, $conditionVariables, $loopVariables, $resourceManagerVariables);

        // přidáno 'set' do funkce truncateUnnecessaryDataFromStrings(), ať se odstraní z něchtěných dat = zůstavalo po podmínce if při podmínce ifset
        $variablesWithoutFunctions = Variables::truncateUnnecessaryDataFromStrings($variables, ["empty", "isset", "in_array", "set", "trim"], FALSE);
        $variables = Variables::removeCharsFromStringArray(
            Variables::truncateUnnecessaryDataFromStrings($variablesWithoutFunctions, ["->", "=>", "::", "=\"", "'#'", ":"])
        );
        $variables = Variables::explodeArrayItemsByChar($variables, ["?", "+"]);
        $forbidenVariables = Variables::getForbiddenVariables($template);
        $variables = Startup::removeArrayBracesFromVariables(array_unique($variables, SORT_STRING));
        $variables = Variables::removeForbiddenVariablesFromArray($variables, $forbidenVariables);

        return array_filter($variables);
    }

    /**
     * @param $variables string[]
     * @param $forbidenVariables string[]
     * @return array
     */
    public function removeForbiddenVariablesFromArray($variables, $forbidenVariables)
    {
        foreach ($forbidenVariables as $forbidenVariable) {
            if (in_array($forbidenVariable, $variables)) {
                $indexes = array_keys($variables, $forbidenVariable);
                foreach ($indexes as $index) {
                    if ($index !== FALSE) {
                        unset($variables[$index]);
                    }
                }
            }
        }
        return $variables;
    }

    //Začíná: {$
    //Pokud obsahuje: |, } tak rozdělí a vrátí první polovinu
    /**
     * @param array $template
     * @param $unwantedVariables
     * @return array
     */
    public function getSimpleVariables($template, $unwantedVariables)
    {
        $variables = [];
        $pattern = '{$';
        foreach ($template as $lineNum => $line) {
            if (strpos($line, $pattern) !== false) {
                $line = substr($line, strpos($line, $pattern));

                preg_match_all('#\{\$(.*?)\}#', $line, $matches);

                $removesCharacters = array("*", "/", "+", "-", "|");

                foreach ($matches[1] as &$singleMatch) {
                    foreach ($removesCharacters as $char) {
                        if (strpos($singleMatch, $char)) {
                            if ($char == "-") // ošetření, že se nejedná o pomlčku, např. $text-text
                            {
                                $exploded = explode($char, $singleMatch);

                                if (is_numeric($exploded[1])) {
                                    $singleMatch = explode($char, $singleMatch);
                                    $singleMatch = $singleMatch[0];

                                    break;
                                } else {
                                    // Vyskočím z cyklu - nechci ořezávat proměnnou, která má v názvu pomlčku, nejedná se
                                    // tedy o znak mínus.
                                    break;
                                }
                            }

                            $singleMatch = explode($char, $singleMatch);
                            $singleMatch = $singleMatch[0];
                        }
                    }

                    $singleMatch = '$' . $singleMatch;

                    $keySearched = array_search($singleMatch, $unwantedVariables, TRUE);

                    if ($keySearched !== FALSE) {
                        $unwantedVariable = Variables::checkPositionVariableFromTemplate($lineNum, $keySearched);

                        if ($unwantedVariable) {
                            array_splice($matches[1], array_search($singleMatch, $matches[1]));
                        }
                    }
                }
                unset($singleMatch);

                $variables = array_merge($variables, $matches[1]);
            }
        }
        return array_unique($variables, SORT_STRING);
    }

    /**
     * @param $template
     * @return array
     */
    public function getVariablesFromResourceManagerTags($template)
    {
        $variables = [];
        $pattern = "{ReligisCore\ResourceManager::addFile(";

        foreach ($template as $lineNum => $line) {
            if (strpos($line, $pattern) !== false) {
                // 1. odstraní řetězec před manažerem
                $line = substr($line, strpos($line, $pattern));
                // 2. Odstraní tagy + {}
                $line = Variables::removeCharsFromString($line, [$pattern]);
                $explodedCondition = explode(")}", $line);
                $line = $explodedCondition[0];
                // 3. Explode &&, || a odstranění druhé části
                $splitLine = Variables::multiexplode(["."], $line);
                foreach ($splitLine as $sline) {
                    $sline = trim($sline);

                    if (strpos($sline, '$') !== FALSE) {
                        array_push($variables, $sline);
                    }
                }
            }
        }

        return $variables;
    }

    //V podmínce může být spojené: &&, || tzn. Postupně parsovat a odebírat nepotřebné
    /**
     * @param $template
     * @param array $tags
     * @param $unwantedVariables
     * @return array
     */
    public function getVariablesFromConditions($template, $tags, $unwantedVariables)
    {
        $variables = [];
        foreach ($tags as $tag) {
            $pattern = '{' . $tag;
            foreach ($template as $lineNum => $line) {
                if (strpos($line, $pattern) !== FALSE && strpos($line, "ReligisCore") == FALSE) {
                    // 1. odstraní řetězec před podmínkou
                    $line = substr($line, strpos($line, $pattern));
                    // 2. Odstraní tagy + {}
                    $line = Variables::removeCharsFromString($line, [$pattern]);
                    $explodedCondition = explode("}", $line);
                    $line = $explodedCondition[0];
                    // 3. Explode &&, || a odstranění druhé části
                    $splitLine = Variables::multiexplode(["&&", "||"], $line);
                    $splitLine = Variables::truncateUnnecessaryDataFromStrings($splitLine, ["===", "==", "!=", "->", ">", "<", "{", "count"]);
                    $splitLine = array($splitLine[0]);
                    // 4. Vložení do variables
                    foreach ($splitLine as $sline) {
                        $variable = trim($sline);
                        $keySearched = array_search($variable, $unwantedVariables, TRUE);

                        if ($keySearched !== FALSE) {
                            $unwantedVariable = Variables::checkPositionVariableFromTemplate($lineNum, $keySearched);

                            if ($unwantedVariable) {
                                $variable = 0;
                            }
                        }

                        if (!empty($variable)) {
                            $variables[] = $variable;
                        }
                    }
                }
            }
        }

        return array_unique($variables, SORT_STRING);
    }

    // Získá proměnné z foreach, jiný cyklus nepoužíváme
    /**
     * @param array $template
     * @param array $tags
     * @param $unwantedVariables
     * @return array
     */
    public function getVariablesFromLoops($template, $tags, $unwantedVariables)
    {
        $variables = [];
        foreach ($tags as $tag) {
            $pattern = '{' . $tag;

            foreach ($template as $lineNum => $line) {
                if (strpos($line, $pattern) !== false) {
                    // 1. odstraní řetězec před cyklem
                    $line = substr($line, strpos($line, $pattern));
                    // 2. Odstraní tagy + {}
                    $line = Variables::removeCharsFromString($line, [$pattern, '}']);
                    // 3. Odstranění druhé části
                    $splitLine = Variables::truncateUnnecessaryDataFromStrings(array($line), [" as "]);
                    foreach ($splitLine as $sLine) {
                        $variable = trim($sLine);
                        $keySearched = array_search($variable, $unwantedVariables, TRUE);

                        if ($keySearched !== FALSE) {
                            $unwantedVariable = Variables::checkPositionVariableFromTemplate($lineNum, $keySearched);

                            if ($unwantedVariable) {
                                $variable = 0;
                            }
                        }

                        if (!empty($variable)) {
                            $variables[] = $variable;
                        }
                    }
                }
            }
        }

        return array_unique($variables, SORT_STRING);
    }

    // Funkce pro získání vars definovaných v šabloně
    /**
     * @param array $template
     * @return array
     */
    public function getUnwantedVarFromTemplate($template = [])
    {
        $unwantedVariables = [];
        $pattern = "{var";

        foreach ($template as $lineNum => $line) {
            if (strpos($line, $pattern) !== false) {
                // 1. odstraní řetězec před var
                $line = substr($line, strpos($line, $pattern));
                //2. odstraní řetězec za znakem =, který nás nezajímá
                $line = Variables::removeCharsFromString($line, [$pattern]);
                $explodedVar = explode("=", $line);
                $variable = trim($explodedVar[0]);

                if (!empty($variable)) {
                    $unwantedVariables[$lineNum] = $variable;
                }
            }
        }

        return array_unique($unwantedVariables, SORT_STRING);
    }

    public function checkPositionVariableFromTemplate($variableLineNumber, $unwantedVariableLineNumber)
    {
        if ($variableLineNumber >= $unwantedVariableLineNumber) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * @param $string string
     * @param $charsToRemove string[]
     * @return string|string[]
     */
    public function removeCharsFromString($string, $charsToRemove)
    {
        foreach ($charsToRemove as $needle) {
            $string = str_replace($needle, '', $string);
        }
        return $string;
    }

    // Rozdělí stríng podle daných značek např "->", "=>"]
    /**
     * @param $delimiters string[]
     * @param $string
     * @return false|string[]
     */
    public function multiexplode($delimiters, $string)
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return $launch;
    }


    // Očese shody od zbytečností
    /**
     * @param $strings string[]
     * @return array
     */
    public function truncateUnnecessaryDataFromStrings($strings, $needles, $FromLeft = TRUE)
    {
        $pattern = "";
        if (count($needles) == 1) {
            $pattern = $pattern . "(" . $needles[0] . ")";
        } else {
            foreach ($needles as $counter => $needle) {
                if ($counter == 0) {
                    $pattern = $pattern . "(" . $needle . "|";
                } elseif ($counter == count($needles) - 1) {
                    $pattern = $pattern . $needle . "|)";
                } else {
                    $pattern = $pattern . $needle . "|";
                }
            }
        }

        $returnString = [];
        foreach ($strings as $string) {
            if (preg_match($pattern, $string) === 1) {
                $split = Variables::multiexplode($needles, $string);
                if ($FromLeft === TRUE) {
                    $variable = $split[0];
                } else {
                    $variable = end($split);
                }
                if (!in_array($variable, $returnString)) {
                    array_push($returnString, $variable);
                }
            } else {
                if (!in_array($string, $returnString)) {
                    array_push($returnString, $string);
                }
            }
        }

        return $returnString;
    }


    /**
     * @param $variables string[]
     * @return mixed
     */
    public function removeCharsFromStringArray($variables = [])
    {
        foreach ($variables as $key => $variable) {
            unset($variables[$key]);
            $variables[] = trim(Variables::removeCharsFromString($variable, ['(', ')', '!']));
        }
        return $variables;
    }


    public function getForbiddenVariables($template)
    {
        $forbidenVariables = [];
        $pattern = '{foreach';
        foreach ($template as $lineNum => $line) {
            if (strpos($line, $pattern) !== false) {
                $split = explode(' as ', $line);
                $line = $split[1];

                if (strpos($line, '=>')) {
                    $split = explode('=>', $line);
                    $line = $split[1];
                }
                if (strpos($line, '{')) {
                    $split = explode('{', $line);
                    $line = $split[0];
                }
                $variable = Variables::removeCharsFromString($line, ['}']);
                $forbidenVariables[] = trim($variable);
            }
        }
        return $forbidenVariables;
    }
}