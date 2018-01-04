<?php

class UserSearch {
    
    public function __construct($params) {

        $paramsTemp = json_decode($params, true);
        $this->validate($paramsTemp);
        
        $this->params = $paramsTemp;
    }
    
    private $params;
    
    // Fields, that are used in SELECT output
    private $selectedFields = [
        '`users`.`id`',
        '`users`.`email`', 
        '`users`.`role`', 
        '`users`.`reg_date`'
    ];
    
    private $mainTable = 'users';
    private $secondaryTable = 'users_about';
    
    private $paramProperties = [
        'id' => [
            'table' => 'users',
            'type' => PDO::PARAM_INT
        ],
        'email' => [
            'table' => 'users',
            'type' => PDO::PARAM_STR
        ],
        'country' => [
            'table' => 'users_about',
            'type' => PDO::PARAM_STR
        ],
        'firstname' => [
            'table' => 'users_about',
            'type' => PDO::PARAM_STR
        ],
        'state' => [
            'table' => 'users_about',
            'type' => PDO::PARAM_STR
        ],
    ];
    
    // Hardcoded lists of logical and comparizon operators to avoid injection
    private $logicalOperators = [
        'AND',
        'OR'
    ];
    private $comparizonOperators = [
        '=',
        '!='
    ];
    
    public $dbconfig = [
        'dsn' => "mysql:host=localhost;dbname=mailiq",
        'username' => 'root',
        'password' => 'root'
    ];
    
    public function search(){
        $query = $this->makeQuery();
        
        $db = new PDO($this->dbconfig['dsn'], $this->dbconfig['username'], $this->dbconfig['password']);
        $statement = $db->prepare($query);
        $parameters = $this->makeParameters($this->params);
        
        foreach($parameters as $placeholder => &$value){
            $statement->bindParam(':'.$placeholder, $value, $this->paramProperties[$placeholder]['type']);
        }
        
        $statement->execute();
        
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function makeQuery(){
        
        $query = "SELECT ";
        $query .= implode(', ', $this->selectedFields);
        $query .= " FROM `{$this->mainTable}` ";
        $query .= $this->makeJoins($this->params);
        $query .= " WHERE ".$this->makeWhere($this->params);

        return $query;
    }
    
    private function makeJoins($condition){
        $joins = [];
        if(self::isNestedCondition($condition)){
            foreach($condition['conditions'] as $cond){
                $joins[] = $this->makeJoins($cond);
            }
        }
        elseif(self::isSingleCondition($condition)){
            if($this->paramProperties[$condition['param']]['table'] == $this->secondaryTable){
                $tableAlias = "{$this->secondaryTable}_{$condition['param']}";
                return "LEFT JOIN "
                        . "`{$this->secondaryTable}` AS `$tableAlias` "
                        . "ON `{$this->mainTable}`.`id` = `$tableAlias`.`user` "
                        . "AND `$tableAlias`.`item` = '{$condition['param']}' ";
            }
        }
        
        return implode(' ', $joins);
    }
    
    private function makeWhere($param){
        if(self::isNestedCondition($param)){
            $wheres = [];
            foreach($param['conditions'] as $condition){
                $wheres[] = $this->makeWhere($condition);
            }
            return '('. implode(' '.$param['logical_operator'].' ', $wheres).')';
        }
        elseif(self::isSingleCondition($param)){
            if($this->paramProperties[$param['param']]['table'] == $this->mainTable){
                return " (`{$this->paramProperties[$param['param']]['table']}`.`{$param['param']}` {$param['comparison_operator']} :{$param['param']}) ";
            }
            elseif($this->paramProperties[$param['param']]['table'] == $this->secondaryTable){
                $tableAlias = $this->paramProperties[$param['param']]['table'].'_'.$param['param'];
                return " (`$tableAlias`.`item` = '{$param['param']}' AND `$tableAlias`.`value` {$param['comparison_operator']} :{$param['param']}) ";
            }
            
        }
        
        return ' TRUE ';
    }
    
    private function makeParameters($param){
        $parameters = [];
        if(self::isNestedCondition($param)){
            foreach($param['conditions'] as $condition){
                $parameters = array_merge($parameters, $this->makeParameters($condition));
            }
        }
        elseif(self::isSingleCondition($param)){
            $parameters["{$param['param']}"] = $param['value'];
        }
        
        return $parameters;
    }
    
    private static function isNestedCondition(Array $condition){
        if(
                array_key_exists('logical_operator', $condition) 
                && 
                array_key_exists('conditions', $condition) 
                && 
                is_array($condition['conditions'])
            ){
            return true;
        }
        return false;
    }

    private static function isSingleCondition(Array $condition){
        if(
                array_key_exists('comparison_operator', $condition) 
                &&
                array_key_exists('param', $condition) 
                &&
                array_key_exists('value', $condition) 
            ){
            return true;
        }
        return false;
    }
    
    // Throw exceptions for malformed input
    private function validate($params){
        if(!$params){
            throw new Exception("Bad JSON", 400);
        }
        array_walk_recursive($params, function($value, $key){
            switch($key){
                case 'logical_operator':
                    if(!in_array($value, $this->logicalOperators)){
                        throw new Exception("Bad 'logical_operator'. Must be one of:". implode(', ', $this->logicalOperators), 400);
                    }
                    break;
                case 'conditions':
                    if(!is_array($value)){
                        throw new Exception("Bad conditions. Must be an array.", 400);
                    }
                    break;
                case 'comparison_operator':
                    if(!in_array($value, $this->comparizonOperators)){
                        throw new Exception("Bad comparison_operator. Must be one of: ".implode(', ', $this->comparizonOperators), 400);
                    }
                    break;
                case 'param':
                    if(!in_array($value, array_keys($this->paramProperties))){
                        throw new Exception("Bad param. Must be one of: ".implode(', ', array_keys($this->paramProperties)), 400);
                    }
                    break;
                case 'value':
                    break;
            }
        });
    }
}
