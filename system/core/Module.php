<?php
	class Module {
		
		var $id;
		var $variables;
		var $values;
		var $dependencies = array();
		private static $INSTANCES = array();
		
		function __construct($id)
		{
			$this->id = $id;
			$this->doc = new DOMDocument;
			$this->doc->load(Kennel::$ROOT_PATH . '/modulesettings.xml');
			$root = $this->doc->getElementsByTagName('modules')->item(0);
			
			foreach ($root->childNodes as $node)
				if ($node->nodeType == 1 && $node->getAttribute('id') == $id)
					$this->settings = $node->childNodes;
			
		}
		
		static function getInstance($id)
		{
			if (isset(self::$INSTANCES[$id]))
				return self::$INSTANCES[$id];
			else
				return new Module($id);
		}
		
		function get($variable)
		{
			foreach ($this->settings as $node)
				if ($node->nodeType == 1 && $node->getAttribute('name') == $variable)
					return $node->getAttribute('value');
		}
		
		function set($variable, $value)
		{
			foreach ($this->settings as $node)
				if ($node->nodeType == 1 && $node->getAttribute('name') == $variable)
				{
					$result = $node->setAttribute('value', $value);
					$this->doc->save(Kennel::$ROOT_PATH . '/modulesettings.xml');
					return $result;
				}
		}
		
		function checkPermission()
		{
			return is_writable(Kennel::$ROOT_PATH . '/modulesettings.xml');
		}
		
	}
?>
