<?php

	class Criteria
	{
		// Operators
		const EQUAL = '=';
		const NOT_EQUAL = '!=';
		const LIKE = 'LIKE';
		const IN = 'IN';
		const NOT_IN = 'NOT IN';
		const GREATER_THAN = '>';
		const LESS_THAN = '<';
		const GREATER_EQUAL = '>=';
		const LESS_EQUAL = '<=';
		
		// Joins
		const LEFT_JOIN = 'LEFT JOIN';
		const RIGHT_JOIN = 'RIGHT JOIN';
		const INNER_JOIN = 'INNER JOIN';
		
		// Values
		const NOW = 'NOW()';
		const IS_NULL = 'IS NULL';
		const NULL = 'IS NULL'; //alias
		const IS_NOT_NULL = 'IS NOT NULL';
		const NOT_NULL = 'IS NOT NULL'; //alias
		
		// Query variables
		var $criterion_groups = array();
		var $from_model_name;
		var $joins = array();
		var $order_by = array();
		var $group_by = array();
		var $limit;
		var $offset = 0;
		var $custom_select_columns = array();
		
		function __construct($model_name=null)
		{
			$this->from_model_name = $model_name;
		}
		
		function add($column, $value, $operator=Criteria::EQUAL)
		{
			$this->criterion_groups[0][] = new Criterion($column, $value, $operator);
		}
		
		function addCustom($custom_criterion)
		{
			$this->criterion_groups[0][] = $custom_criterion;
		}
		
		function limit($limit)
		{
			$this->limit = $limit;
		}
		
		function setLimit($limit) // alias to Criteria::limit(), deprecated
		{
			$this->limit($limit);
		}
		
		function offset($offset)
		{
			$this->offset = $offset;
		}
		
		function setOffset($offset) // alias to Criteria::offset(), deprecated
		{
			$this->offset($offset);
		}
		
		function addAscendingOrder($column)
		{
			$this->order_by[] = array('column'=>$column, 'direction'=>'ASC');
		}
		
		function addDescendingOrder($column)
		{
			$this->order_by[] = array('column'=>$column, 'direction'=>'DESC');
		}
		
		function addRandomOrder()
		{
			$this->order_by[] = array('direction'=>'RAND');
		}
		
		function addGroupBy($column)
		{
			$this->group_by[] = $column;
		}
		
		function setFrom($model_name)
		{
			$this->from_model_name = $model_name;
		}
		
		function addJoin($model_name, $left_column, $right_column, $join_type=self::INNER_JOIN)
		{
			foreach ($this->joins as $join)
				if ($join['model_name'] == $model_name) return false; // This should actually throw a Warning
			
			$this->joins[] = array(
				'model_name' => $model_name,
				'left_column' => $left_column,
				'right_column' => $right_column,
				'join_type' => $join_type
			);
			
		}
		
		function addCustomSelectColumn($definition, $alias)
		{
			$this->custom_select_columns[] = array(
				'definition'=>$definition,
				'alias'=>$alias
			);
		}
		
		function addOr()
		{
			$this->criterion_groups[] = func_get_args();
		}
		
		function getNewCriterion($field, $value, $operator=Criteria::EQUAL)
		{
			return new Criterion($field, $value, $operator);
		}
		
		function get($field, $value, $operator=Criteria::EQUAL) // Alias to getNewCriterion
		{
			return $this->getNewCriterion($field, $value, $operator);
		}
		
	}
	
?>
