<?php
	
	class Model {
		
		private static $_DB;
		private $_synced_data;
		var $_data;
		private $_i18n = array();
		var $is_synced;
		var $schema;
		var $model_name;
		
		const ERR_UNIQUE = 1;
		const ERR_REQUIRED = 2;
		public $invalidFields = array(); // Fields with validation issues from the latest validate() or save() call; further calls reset this
		public $errors = array(); // Types of validation errors found in the latest validate() or save() call; further calls reset this
		
		/**
			*  Instance Methods 
			*/
		
		function __construct($model_name)
		{
			if (!is_string($model_name)) debug::error('Model::__construct - $model_name must be a string.');
			$this->model_name = $model_name;
			$this->schema = ORM::getSchema($model_name);
			foreach ($this->schema as $field)
			{
				$this->_data[$field->name] = null;
				$this->_synced_data[$field->name] = null;
			}
			$this->is_synced = true;
		}
		
		function __set($name, $value)
		{
			if ( Kennel::getSetting('i18n', 'enabled')
				&& is_array($value)
				&& isset($value[Kennel::getSetting('i18n', 'default')]) )
			{
				$default_lang = Kennel::getSetting('i18n', 'default');
				$this->__set($name, $value[$default_lang]);
				
				foreach ($value as $lang=>$i18n_value)
				{
					$this->i18n($lang)->__set($name, $i18n_value);
					if (!$this->i18n($lang)->is_synced) $this->is_synced = false;
				}
				return $value;
			}
			
			$set_method = 'set' . ucfirst(strtolower($name));
			if (method_exists($this, $set_method))
				$value = $this->$set_method($value);
			
			if ($this->schema->isField($name) && $this->_data[$name] != $value)
				$this->is_synced = false;
			
			return $this->_data[$name] = $value;
		}
		
		function __get($name)
		{	
			$get_method = 'get' . ucfirst(strtolower($name));
			
			if (isset($this->_data[$name]))
			{
				if (method_exists($this, $get_method))
					return $this->$get_method($this->_data[$name]);
				else
					return $this->_data[$name];
			}
			else
			{
				return null;
			}
		}
		
		function __toString()
		{
			return $this->model_name;
		}
		
		function hydrate($field, $value)
		{
			$value = $this->schema->$field->cast($value);
			$this->_data[$field] = ($value !== null) ? $value : null;
			$this->_synced_data[$field] = $value;
		}
		
		function delete()
		{
			// Delete the localized versions too
			if (Kennel::getSetting('i18n', 'enabled') && substr($this->model_name, -5) !== '_i18n')
			{
				$this->_fetchI18n();
				foreach ($this->_i18n as $i18n)
				{
					$i18n->delete();
				}
			}
			
			// Delete the model
			$c = new Criteria($this->model_name);
			foreach ($this->schema as $field)
			{
				$c->add($field->name, $this->_data[$field->name]);
			}
			unset($this->_synced_data);
			unset($this->_data);
			return ORM::delete($c);
		}
		
		function getPrimaryKey()
		{
			$primaryKey = $this->schema->getPrimaryKey()->name;
			return $this->$primaryKey;
		}
		
		function validate()
		{
			$this->invalidFields = array();
			$this->errors = array();
			$uniques = array();
			
			foreach ($this->schema as $field)
			{
				// Simple required field validation
				$numeric_fields = array('int', 'tinyint', 'bigint', 'float', 'double', 'decimal');
				$field_is_numeric = array_search($field->type, $numeric_fields) !== false;
				
				if (
					(
						(!$field_is_numeric && $this->_data[$field->name] === NULL)
						|| ($field_is_numeric && !is_numeric($this->_data[$field->name]))
					)
					&& $field->required && !$field->primaryKey
				)
				{
					$this->invalidFields[$field->name][] = i18n::get('This field is required');
					if (!in_array(self::ERR_REQUIRED, $this->errors))
						$this->errors[] = self::ERR_REQUIRED;
				}
				
				// Gather unique fields
				if ($field->unique && !$field->primaryKey)
				{
					$uniques[] = $field->name;
				}
			}
			
			// Unique field validation only for new model instances
			if (!$this->getPrimaryKey())
				// Loop through each unique field and retrieve instances;
				// Could be refactored to reduce the number of queries made
				foreach ($uniques as $field_name)
				{
					$c = new Criteria($this->model_name);
					$c->add($field_name, $this->_data[$field_name]);
					if (ORM::count($c) > 0)
					{
						$this->invalidFields[$field_name][] = i18n::get('Already registered');
						if (!in_array(self::ERR_UNIQUE, $this->errors))
							$this->errors[] = self::ERR_UNIQUE;
					}
				}
			
			if (count($this->invalidFields) > 0) return false;
			else return true;
		}
		
		function save()
		{
			
			if (!$this->validate() && substr($this->schema->table, -5) !== '_i18n') {
				$msg = i18n::get('Trying to save invalid content for model "%0"', $this);
				debug::error($msg, 1);
				$this->dump();
				return false;
			}
			
			$primaryKey = $this->schema->getPrimaryKey()->name;
			
			// No need to save if the model has a PK set and is synced with the DB
			if ($this->$primaryKey && $this->is_synced)
				return null;
			
			$sql = $this->getSaveQuery();
			if (!self::$_DB) self::$_DB = new MySQL;
			self::$_DB->query($sql);
			
			if (!$this->$primaryKey && !$this->is_synced)
			{
				//Update the PrimaryKey
				$insertId = self::$_DB->insert_id();
				$this->_synced_data[$primaryKey] = $insertId;
				$this->_data[$primaryKey] = $insertId;
			}
			
			// Save the localized versions too
			if (Kennel::getSetting('i18n', 'enabled') && substr($this->model_name, -5) !== '_i18n')
				foreach ($this->_i18n as $i18n)
				{
					// Set the relationship IDs
					if ($i18n->__get("{$this->model_name}_{$primaryKey}") !== $this->$primaryKey);
						$i18n->__set("{$this->model_name}_{$primaryKey}", $this->$primaryKey);
					$i18n->save();
				}
			
			// Update the synced data
			$this->_synced_data = $this->_data;
			
			$this->is_synced = true;

			return true;
		}
		
		function hasI18n()
		{
			$path = Kennel::cascade("{$this->model_name}_i18n", 'schemas');
			return !!$path;
		}
		
		private function _fetchI18n()
		{
			$primaryKey = $this->schema->getPrimaryKey()->name;
			
			// Grab the localized versions if present
			if ($this->hasI18n() && !$this->_i18n) {
				$c = new Criteria("{$this->model_name}_i18n");
				$c->add("{$this->model_name}_{$primaryKey}", $this->$primaryKey);
				$i18n = ORM::retrieve($c);
				foreach ($i18n as $model)
					$this->_i18n[$model->lang] = $model;
			}
		}
		
		// Get the instance of the localized version of the model
		function i18n($lang=null)
		{
			// If i18n is not enabled, return itself
			if (!Kennel::getSetting('i18n', 'enabled')) return $this;
			
			if (!$lang) $lang = i18n::getLang();
			$primaryKey = $this->schema->getPrimaryKey()->name;
			
			$this->_fetchI18n();
			
			foreach ($this->_i18n as $i18n)
				if ($i18n->lang == $lang) return $i18n;
			
			// If no localized version was found, create one now
			$primaryKey = $this->schema->getPrimaryKey()->name;
			$i18n = new Model("{$this->model_name}_i18n");
			$i18n->__set("{$this->model_name}_{$primaryKey}", $this->$primaryKey);
			$i18n->lang = $lang;
			
			// … and populate it with default values, if any.
			// Useful as base data for internationalization of existing, populated models 
			foreach ($i18n->_data as $key=>$value)
			{
				if ($key != 'id' && isset($this->_data[$key]))
					$i18n->$key = $this->$key;
			}
			
			return $this->_i18n[] = $i18n;
		}
		
		function getSaveQuery($forceInsert=false)
		{
			$primaryKey = $this->schema->getPrimaryKey();
			
			$columns = array();
			$newValues = array();
			$syncedValues = array();
			
			// Add quotes for non-numeric field values, used to the SQL statement
			foreach ($this->schema as $field)
			{
				$columns[] = '`' . $field->name . '`';
				switch (strtolower($field->type))
				{
					case 'varchar':
					case 'char':
					case 'text':
					case 'datetime':
					case 'date':
					case 'time':
						
						if ($this->_data[$field->name] !== NULL)
							$newValues[] = '"' . MySQL::escape_string($this->_data[$field->name]) . '"';
						else
							$newValues[] = 'NULL';
							
						if ($this->_synced_data[$field->name] !== NULL)
							$syncedValues[] = '"' . MySQL::escape_string($this->_synced_data[$field->name]) . '"';
						else
							$syncedValues[] = 'NULL';
							
						break;
						
					case 'int':
					case 'tinyint':
					case 'bigint':
					case 'float':
					case 'double':
					case 'decimal':
						
						if ($this->_data[$field->name] !== NULL && is_numeric($this->_data[$field->name]))
							$newValues[] = $this->_data[$field->name];
						else
							$newValues[] = 'NULL';
						
						if ($this->_synced_data[$field->name] !== NULL && is_numeric($this->sunced_data[$field->name]))
							$syncedValues[] = $this->_synced_data[$field->name];
						else
							$syncedValues[] = 'NULL';
						
						break;
						
					default:
						debug::error("Model::save - Unsuported field type \"{$field->type}\" for field \"{$field->name}\" on model \"{$this->model_name}\"");
				}
			}
			
			// Check if it's an existing record, build SQL statement accordingly
			if (!$this->_synced_data[$primaryKey->name] || $forceInsert)
			{
				$sql = "INSERT INTO {$this->schema->table} (";
				$sql .= implode(', ', $columns);
				$sql .= ")\nVALUES (";
				$sql .= implode(', ', $newValues);
				$sql .= ");";
			} else
			{
				$dataList = array();
				foreach ($columns as $key=>$column)
					$dataList[] = "\n {$column} = {$newValues[$key]}";
				
				$c = new Criteria($this->model_name);
				foreach ($this->schema as $field)
					$c->add($field->name, $this->_synced_data[$field->name]);
				
				$sql = "UPDATE {$this->schema->table} SET " . implode(', ', $dataList);
				$sql .= "\nWHERE " . ORM::getWhereString($c) . ';';
			}
			
			return $sql;
		}
		
		function toArray()
		{
			return $this->_data;
		}
		
		function fromArray($data)
		{
			foreach ($data as $field=>$value)
			{
				$this->__set($field, $value);
			}
		}
		
		function dump($return=false, $dump_relationships=true)
		{
			// Cache current validation vars
			$errorsCache = $this->errors;
			$invalidFieldsCache = $this->invalidFields;
			
			$isValid = $this->validate();
			
			if ( Kennel::$ROOT_URL )
			{
  			// Running from an HTTP request
  						
  			$dump = '';
  			$dump .= '<div><pre style="padding: 5px; border: 1px solid #1E9C6D; background: #FFF; color: #1E9C6D; float: left; text-align: left;">';
  			$dump .= '<h2 style="margin: 0px 0px 5px 0px; padding: 0px 5px; background: #1E9C6D; color: #FFF;">';
  			$class = get_class($this);
  			$dump .= "{$this->model_name}: {$class}";
  			$dump .= '</h2>';
  			foreach ($this->schema as $field)
  			{
  				// Signs
  				if ($field->primaryKey) $dump .= '<strong>*</strong> ';
  				elseif ($field->foreignKey && $field->foreignModel) $dump .= '<strong>~</strong> ';
  				else $dump .= '&nbsp; ';
				
  				// Field name and content
  				//debug::dump($field->name, $field->type, $this->_data[$field->name], '--');
  				$value = var_export($this->_data[$field->name], true);
  				if ($field->required)
  					$dump .= "<strong>{$field->name}</strong> = {$value}";
  				else
  					$dump .= "<strong>[{$field->name}]</strong> = {$value}";
				
  				// Validation
  				if (!$isValid)
  				{
  					//debug::dump($field->name, array_key_exists($field->name, $this->invalidFields));
  					if (array_key_exists($field->name, $this->invalidFields))
  					{
  						$dump .= ' <strong style="color: #f00;">(!)</strong>';
  						foreach ($this->invalidFields[$field->name] as $errorMessage)
  							$dump .= '<br /><strong style="color: #f00; font-size: 80%; font-weight: normal;">&nbsp;&nbsp;&nbsp;'.$errorMessage.'</strong>';
  					}
  				}
				
  				$dump .= '<br />';
  			}
			
  			if ($dump_relationships)
  				foreach ($this->schema->getRelationships() as $relationship)
  				{
  					$foreignModel = $relationship->foreignModel;
  					if ($this->$foreignModel) $dump .= $this->$foreignModel->dump(true, true);
  				}
			
  			$dump .= '</pre></div><div style="clear: both;"></div>';
			
  			// Return validation variables to their original values
  			$this->errors = $errorsCache;
  			$this->invaliFields = $invalidFieldsCache;
  		}
  		else
  		{
  			// Running from the command line
  			
  			$class = get_class($this);
  			$dump = "\033[1;30;42m {$this->model_name}: {$class} \033[0m\n";
  			foreach ($this->schema as $field)
  			{
  				// Signs
  				if ($field->primaryKey) $dump .= '* ';
  				elseif ($field->foreignKey && $field->foreignModel) $dump .= '~ ';
  				else $dump .= '  ';
				
  				// Field name and content
  				//debug::dump($field->name, $field->type, $this->_data[$field->name], '--');
  				$value = var_export($this->_data[$field->name], true);
  				if ($field->required)
  					$dump .= "\033[2;32;40m{$field->name}:\033[0m {$value}";
  				else
  					$dump .= "[{$field->name}]: {$value}";
				
  				// Validation
  				if (!$isValid)
  				{
  					//debug::dump($field->name, array_key_exists($field->name, $this->invalidFields));
  					if (array_key_exists($field->name, $this->invalidFields))
  					{
  						$dump .= " \033[31m! ";
  						$dump .= implode('; ', $this->invalidFields[$field->name]);
  						$dump .= "\033[0m";
  					}
  				}
				
  				$dump .= "\n";
  			}
			
  			if ($dump_relationships)
  				foreach ($this->schema->getRelationships() as $relationship)
  				{
  					$foreignModel = $relationship->foreignModel;
  					if ($this->$foreignModel) $dump .= $this->$foreignModel->dump(true, true);
  				}
			
  			$dump .= "\033[0m\n";
			
  			// Return validation variables to their original values
  			$this->errors = $errorsCache;
  			$this->invaliFields = $invalidFieldsCache;
			}
			
			if ($return) return $dump;
			else print $dump;
		}
		
		/**
			*  Static methods 
			*/
		
		static function getInstance($model_name)
		{
			$path = Kennel::cascade($model_name, 'models');
			if ($path)
			{
				$class = ucfirst($model_name) . '_model';
				$instance = new $class;
			}
			else
			{
				$class = 'Model';
				$instance = new $class($model_name);
			}
			
			return $instance;
		}
		
	}
?>
