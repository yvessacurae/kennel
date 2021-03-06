<?php

	class ORM
	{
		static $DB;
		private static $SCHEMA_CACHE = array();
		
		function __construct()
		{
			self::$DB = new MySQL;
		}
		
		static function retrieve(Criteria $criteria)
		{
			if (!self::$DB) self::$DB = new MySQL;
			
			$schema = self::getSchema($criteria->from_model_name);
			$relationships = $schema->getRelationships();
			
			foreach ($relationships as $rel)
			{
				$criteria->addJoin($rel->foreignModel, "{$criteria->from_model_name}.{$rel->name}", "{$rel->foreignModel}.{$rel->foreignKey}", Criteria::LEFT_JOIN);
			}
			
			$sql = self::getSelectString($criteria);
			$rs = self::$DB->query($sql);
			
			$model_array = array();
			while ($data = self::$DB->fetch($rs))
			{
				$model = Model::getInstance($criteria->from_model_name);
				self::hydrateModel($model, $data);
				
				foreach ($relationships as $relationship)
				{
					$foreignModel_name = $relationship->foreignModel;
					$model->$foreignModel_name = Model::getInstance($foreignModel_name);
					self::hydrateModel($model->$foreignModel_name, $data);
				}
				
				$model_array[] = $model;
			}
			
			return $model_array;
		}
		
		static function count(Criteria $criteria)
		{
			if (!self::$DB) self::$DB = new MySQL;
			
			$schema = self::getSchema($criteria->from_model_name);
			$relationships = $schema->getRelationships();
			
			foreach ($relationships as $rel)
			{
				$criteria->addJoin(
					$rel->foreignModel,
					"{$criteria->from_model_name}.{$rel->name}", "{$rel->foreignModel}.{$rel->foreignKey}",
					Criteria::LEFT_JOIN
				);
			}
			
			$sql = self::getSelectString($criteria);
			$rs = self::$DB->query($sql);
			return self::$DB->num_rows($rs);
		}
		
		static function dump(Criteria $criteria)
		{
			if (!self::$DB) self::$DB = new MySQL;
			
			$schema = self::getSchema($criteria->from_model_name);
			$relationships = $schema->getRelationships();
			
			foreach ($relationships as $rel)
			{
				$criteria->addJoin(
					$rel->foreignModel,
					"{$criteria->from_model_name}.{$rel->name}", "{$rel->foreignModel}.{$rel->foreignKey}",
					Criteria::LEFT_JOIN
				);
			}
			
			$sql = self::getSelectString($criteria);

			if ( Kennel::$ROOT_URL )
				echo syntax::mysql($sql); // Running from HTTP request
			else
				echo $sql; // Running from the cli
			return;
		}
		
		static function retrieveFirst(Criteria $criteria)
		{
			$criteria->setLimit(1);
			$items = self::retrieve($criteria);
			if (count($items) > 0) return $items[0];
			else return null;
		}
		
		static function delete(Criteria $criteria)
		{
			if (!self::$DB) self::$DB = new MySQL;
			$sql = self::getDeleteString($criteria);
			self::$DB->query($sql);
			return self::$DB->affected_rows();
		}
		
		static function create($model)
		{
			if (!self::$DB) self::$DB = new MySQL;
			
			$schema = self::getSchema($model);
			$sql = $schema->getCreateString();
			
			self::$DB->query($sql);
			return self::$DB->affected_rows();
		}
		
		static function retrieveByPrimaryKey($model_name, $primary_key_value)
		{
			$schema = self::getSchema($model_name);
			$primaryKey = $schema->getPrimaryKey();
			if(!$primaryKey) debug::error("ORM::retrieveByPrimaryKey error: no primary key defined for model <strong>{$model_name}</strong>.");
			
			$c = new Criteria($model_name);
			$c->add($primaryKey->name, $primary_key_value);
			$c->setLimit(1);
			
			$instancies = ORM::retrieve($c);
			
			if (isset($instancies[0])) return $instancies[0];
			else return null;
		}
		
		static function retrieveAll($model_name)
		{
			$c = new Criteria($model_name);
			return self::retrieve($c);
		}
		
		static function getSchema($model_name)
		{
			if (!isset(self::$SCHEMA_CACHE[$model_name]))
				self::$SCHEMA_CACHE[$model_name] = new Schema($model_name);
			
			return self::$SCHEMA_CACHE[$model_name];
		}
		
		static function hydrateModel($model, $data)
		{
			foreach ($data as $key=>$value)
			{
				if (
					(substr($key, 0, strlen($model->schema->table)) == $model->schema->table) // Reference begins with the table name...
					&& (substr($key, strlen($model->schema->table), 2) === '__')// ...imediatelly followed by double underscores
				)
				{
					$field_name = substr($key, strlen($model->schema->table . '__'));
					$model->hydrate($field_name, $value);
				}
			}
		}
		
		// ORM::getSelectString(Criteria $criteria)
		static function getSelectString(Criteria $criteria)
		{
			// SELECT
			$sql = "SELECT ";
			$sql .= self::getFieldListString($criteria);
			
			// FROM
			$sql .= "\nFROM ";
			$sql .= self::getFromString($criteria);
			
			// JOINS
			$join_string = self::getJoinString($criteria);
			if ($join_string) $sql .= $join_string;
			
			// WHERE
			$where_string = self::getWhereString($criteria);
			if ($where_string) $sql .= "\nWHERE {$where_string}";
			
			// GROUP
			$group_string = self::getGroupString($criteria);
			if ($group_string) $sql .= "\nGROUP BY {$group_string}";
			
			// ORDER
			$order_string = self::getOrderString($criteria);
			if ($order_string) $sql .= "\nORDER BY {$order_string}";
			
			// LIMIT
			$limit_string = self::getLimitString($criteria);
			if($limit_string) $sql .= "\nLIMIT {$limit_string}";
			
			$sql .= ';';
			
			return $sql;
		}
		
		// ORM::getDeleteString(Criteria $criteria)
		static function getDeleteString(Criteria $criteria)
		{
			// DELETE
			$sql = "DELETE ";
			
			// FROM
			$sql .= "\nFROM ";
			$sql .= self::getFromString($criteria);
			
			// WHERE
			$where_string = self::getWhereString($criteria);
			if ($where_string) $sql .= "\nWHERE {$where_string}";
			
			$sql .= ';';
			
			return $sql;
		}

		
		// ORM::getFieldListString(Criteria $criteria)
		static function getFieldListString(Criteria $criteria)
		{
			$select_array = array();
			
			$schema = self::getSchema($criteria->from_model_name);
			foreach ($schema as $field)
			{
				$select_array[] = "\n `{$schema->table}`.`{$field->name}` AS `{$schema->table}__{$field->name}`";
			}
			foreach ($criteria->custom_select_columns as $custom_field)
			{
				$select_array[] = "\n {$custom_field['definition']} AS `{$schema->table}__{$custom_field['alias']}`";
			}
			
			foreach ($criteria->joins as $join)
			{
				$schema = self::getSchema($join['model_name']);
				foreach ($schema as $field)
				{
					$select_array[] = "\n `{$schema->table}`.`{$field->name}` AS `{$schema->table}__{$field->name}`";
				}
			}
			
			$select_string = implode(', ', $select_array);
			
			return $select_string;
		}
		
		// ORM::getFromString(Criteria $criteria)
		static function getFromString(Criteria $criteria)
		{
			$schema = self::getSchema($criteria->from_model_name);
			return "\n `{$schema->table}`";
		}
		
		// ORM::getJoinString(Criteria $criteria)
		static function getJoinString(Criteria $criteria)
		{
			$joins = array();
			foreach($criteria->joins as $join)
			{
				$schema = self::getSchema($join['model_name']);
				$left_column_reference = self::formatColumnReference($join['left_column'], $criteria);
				$right_column_reference = self::formatColumnReference($join['right_column'], $criteria);
				$joins[] = "\n {$join['join_type']} {$schema->table} ON {$left_column_reference} = {$right_column_reference}";
			}
			
			return implode('', $joins);
		}
		
		// ORM::getWhereString(Criteria $criteria)
		static function getWhereString(Criteria $criteria)
		{
			$where_groups = array();
			foreach($criteria->criterion_groups as $group_key=>$criterion_group)
			{
				
				foreach ($criterion_group as $criterion)
				{
					// Custom Criterion (simple strings)
					if (is_string($criterion))
					{
						$where_groups[$group_key][] = $criterion;
						continue;
					}
					
					$column = self::formatColumnReference($criterion->column, $criteria);
					
					// Criteria::NOW
					if ($criterion->value === Criteria::NOW)
						$where_groups[$group_key][] = $column . ' ' . $criterion->operator . ' NOW()';
					// NULL value or Criteria::IS_NULL
					elseif ($criterion->value === NULL || $criterion->value === Criteria::IS_NULL)
						$where_groups[$group_key][] = $column . ' IS NULL';
					// Criteria::IS_NOT_NULL
					elseif ($criterion->value === Criteria::IS_NOT_NULL)
						$where_groups[$group_key][] = $column . ' IS NOT NULL';
					// IN operator
					elseif (($criterion->operator == Criteria::IN || $criterion->operator == Criteria::NOT_IN) && is_array($criterion->value))
						$where_groups[$group_key][] = $column . " {$criterion->operator} (" . implode(', ', MySQL::escape_string($criterion->value)) .  ')';
					// X = Y
					else
						$where_groups[$group_key][] = $column . ' ' . $criterion->operator . ' "' . MySQL::escape_string($criterion->value) . '"';
				}
			}
			
			$where = array();
			foreach ($where_groups as $where_group)
			{
				$where[] = "(" . implode("\n AND ", $where_group) . ')';
			}
			
			return implode(' OR ', $where);
		}
		
		// ORM::getOrderString(Criteria $criteria)
		static function getOrderString(Criteria $criteria)
		{
			$order_params = array();
			
			foreach($criteria->order_by as $order_by)
				if ($order_by['direction'] == 'ASC' or $order_by['direction'] == 'DESC')
					$order_params[] = "\n " . self::formatColumnReference($order_by['column'], $criteria) . " {$order_by['direction']}";
				elseif ($order_by['direction'] == 'RAND')
					$order_params[] = "\n " . "RAND()";
			
			return implode(', ', $order_params);
		}
		
		// ORM::getGroupString(Criteria $criteria)
		static function getGroupString(Criteria $criteria)
		{
			$params = array();
			foreach($criteria->group_by as $group_by)
				$params[] = "\n " . self::formatColumnReference($group_by, $criteria);
			
			return implode(', ', $params);
		}
		
		// ORM::getLimitString(Criteria $criteria)
		static function getLimitString(Criteria $criteria)
		{
			if ($criteria->limit)
			{
				if ($criteria->offset) return "{$criteria->offset}, {$criteria->limit}";
				else return "{$criteria->limit}";
			}
			else
				return null;
		}
		
		// ORM::formatColumnReference(String $column, Criteria $criteria);
		static function formatColumnReference($column_reference, Criteria $criteria)
		{
			// Reference has table/column
			if (strpos($column_reference, '.') > 0)
			{
				$column_composition = explode('.', $column_reference);
				$schema = ORM::getSchema($column_composition[0]);
				return '`' . trim($schema->table, '`') . '`.`' . trim($column_composition[1], '`') . '`';
			}
			// Reference to a custom select column
			elseif (self::isCustomSelectColumn($column_reference, $criteria))
			{
				$schema = self::getSchema($criteria->from_model_name);
				return "`{$schema->table}__{$column_reference}`";
			}
			// Standard reference (just the column name)
			else
			{
				$schema = ORM::getSchema($criteria->from_model_name);
				return '`' . trim($schema->table, '`') . '`.`' . $column_reference . '`';
			}
		}
		
		static function isCustomSelectColumn($column_reference, Criteria $criteria)
		{
			foreach ($criteria->custom_select_columns as $custom_column)
				if ($custom_column['alias'] == $column_reference)
					return true;
			return false;
		}
	}
	
?>
