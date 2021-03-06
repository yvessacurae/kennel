<?php
	
	class View
	{
		private $view;
		private $parent_view;
		private $vars = array();
		
		function __construct($view, $parentView=null)
		{
			$this->view = $view;
			if ($parentView)
				$this->parent_view = $parentView;
		}
		
		function __toString()
		{
			return strval($this->output());
		}
		
		function __get($var)
		{
			if(isset($this->vars[$var])) return $this->vars[$var];
			else debug::error("'{$var}' is not defined");
		}
		
		function __set($var, $value)
		{
			if (is_object($value) && get_class($value) == 'View')
				$value->parent_view = $this;
				
			$this->vars[$var] = $value;
		}
		
		function getTemplateVars()
		{
			return $this->vars;
		}
		
		function output() {
			//set all template variables
			foreach ($this->vars as $var =>$val)
				$$var = $val;
			if($this->parent_view)
				foreach ($this->parent_view->vars as $var =>$val)
					$$var = $val; // TODO: Check extract() for an alternative method
			
			$path = Kennel::cascade("{$this->view}", 'views');
			if (!$path) return debug::error("View <strong>{$this->view}</strong> not found.");
			
			//begin intercepting the output buffer
			ob_start();
			
			if($path) require($path);
			
			//return the output and close the buffer
			return ob_get_clean();
			
			//unset all template variables
			foreach ($this->vars as $var =>$val) {
				unset($$var);
			}
		}
		
		function render()
		{
			print $this->output();
		}
	}
?>
