<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Libraries;

if (!defined('PATH')) die();

class MySQLExportParser
{
    private $sqlFileContent;
    private $tableFieldsArray;
    private $insertValuesArray;

    public function __construct($sqlContent)
    {
        $this->sqlFileContent = $sqlContent;
        $this->parseExportFile();
    }

    private function parseExportFile()
    {
        $patternCreate = '/CREATE TABLE `([^`]*)` \(([^;]*)\)/si';
        preg_match_all($patternCreate, $this->sqlFileContent, $matchesCreate);

        $tables = $matchesCreate[1];
        $fieldsDefinitions = $matchesCreate[2];

        $this->tableFieldsArray = [];
        $this->insertValuesArray = [];

        foreach ($tables as $index => $tableName) {
            $fieldLines = explode(',', $fieldsDefinitions[$index]);
            $tableFieldNames = [];

            foreach ($fieldLines as $fieldLine) {
                $fieldLine = trim($fieldLine);
                preg_match('/`([^`]*)`/', $fieldLine, $fieldNameMatches);

                if (!empty($fieldNameMatches[1])) {
                    $tableFieldNames[] = $fieldNameMatches[1];
                }
            }

            $this->tableFieldsArray[$tableName] = $tableFieldNames;
        }

        // Insert value extraction
        $insertValues = $this->extractInsertValues($this->sqlFileContent, $tables);
        $this->insertValuesArray = $insertValues;
    }

    private function extractInsertValues($sqlContent, $tableNames)
    {
        $insertValues       = array();
        $lines              = explode("\n", $sqlContent);
        $isInsideInsert     = false;
        $currentTable       = '';
        $currentInsert      = '';


        foreach ($lines as $line) {
            // Check if the line starts with 'INSERT INTO'
            if (strpos(trim($line), 'INSERT INTO') === 0) {
                $isInsideInsert = true;
                $currentTable   = $this->getTableName($line);
                $currentInsert  = '';
            }

            if ($isInsideInsert && in_array($currentTable, $tableNames)) {
                // Append the line to the current insert statement
                $currentInsert .= $line;

                // Check if the current insert statement is complete
                if (substr(trim($line), -1) === ';') {
                    $insertValues[$currentTable] = $this->extractValuesFromQuery($currentInsert);
                    $isInsideInsert = false;
                }
            }
        }

        return $insertValues;
    }

    private function getTableName($insertStatement)
    {
        $pattern = '/INSERT INTO `([^`]*)`/i';
        preg_match($pattern, $insertStatement, $matches);
        return $matches[1];
    }

    private function extractValuesFromQuery($stringQuery)
    {
        $pattern = '/\((.*?)\)/';
        preg_match_all($pattern, $stringQuery, $matches);

        $values = $matches[1];
        $values = array_map('trim', $values);

        // Separate each set of values into its own array
        $insertArrays = [];
        foreach ($values as $key => $value) {
            $insertArrays[$key] = explode(',', str_replace("'", "", $value));
        }

        return $insertArrays;
    }

    public function getTableFields($tableName)
    {
        if (isset($this->tableFieldsArray[$tableName])) {
            return $this->tableFieldsArray[$tableName];
        }
        return null;
    }

    public function getInsertValues($tableName)
    {
        if (isset($this->insertValuesArray[$tableName])) {
            return $this->insertValuesArray[$tableName];
        }
        return null;
    }

    public function getTablesData($tables)
    {
        $tablesFields = $this->tableFieldsArray;
        $result = [];
        foreach ($tablesFields as $tableName => $tableFields) {
            if (in_array($tableName, $tables)) {
                $result[$tableName] = $this->getTableDate($tableName);
            }
        }

        return  $result;
    }

    private function getTableDate($tableName)
    {

        $fields = $this->tableFieldsArray[$tableName];
        $values = $this->insertValuesArray[$tableName];

        $newData = [];

        foreach ($values as  $num => $value) {
            foreach ($value as $key => $val) {
                if (isset($fields[$key])) {
                    $newData[$num][$fields[$key]] = $val;
                }
            }
        }

        return $newData;
    }
}
