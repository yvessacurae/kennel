<?php

	/*
	* __autoload(string $resource)
	* Automatically requires Controllers and Kennel System files
	*/
	function __autoload($class_name)
	{
		// Controllers
		if (substr($class_name, -11) == '_controller')
		{
			$controller_name = strtolower(substr($class_name, 0, (strlen($class_name) - 11)));
			
			$path = Kennel::cascade($controller_name, 'controllers');
			if($path) require_once($path);
		}
		
		// Helpers
		if ($class_name == strtolower($class_name))
		{
			$path = Kennel::cascade($class_name, 'helpers');
			if($path) require_once($path);
		}
		
		// Models
		if (substr($class_name, -6) == '_model')
		{
			$model_name = strtolower(substr($class_name, 0, (strlen($class_name) - 6)));
			
			$path = Kennel::cascade($model_name, 'models');
			if($path) require_once($path);
		}
		
		// System Core Libraries
		$path = Kennel::cascade($class_name, 'libraries');
		if($path) require_once($path);
	}
	
	/*
	* url(string $action)
	* Returns a Kennel formated url.
	* @action - the controller and actions. Eg. "blog/post"
	*/
	function url($action=null) {
		if(isset($action))
		{
			if(Kennel::getSetting('application', 'use_mod_rewrite'))
				$url= Kennel::$ROOT_URL . "/{$action}";
			else
				$url= Kennel::$ROOT_URL . '/index.php/' . action;
		}
		else 
		{
			$url = Kennel::$ROOT_URL;
		}
		
		return $url;
	}
	
	/*
	 * The magic starts here.
	 */
	class Kennel {
		
		private static $_APP_SETTINGS;
		private static $_CASCADE_CACHE;
		static $ROOT_PATH;
		static $ROOT_URL;
		
		static $MODULES;
		static $MODULE_SETTINGS;
		
		static $request_query_string;
		static $request_uri;
		
		static $CONTROLLER_INSTANCE;
		
		static $time_init;
		static $time_final;
		
		/*
		* Kennel::init()
		*/
		static function init() {
			//begin the benchmark
			self::$time_init = microtime(true);
			register_shutdown_function(array('Kennel', 'onShutdown'));
			
			//get the application path and root uri
			self::$ROOT_PATH = dirname($_SERVER['SCRIPT_FILENAME']);
			self::$ROOT_URL = trim("http://{$_SERVER['HTTP_HOST']}", '\\/') . '/' . trim(substr(self::$ROOT_PATH, strlen($_SERVER['DOCUMENT_ROOT'])), '\\/');
			
			//get the application settings
			require_once('settings.php');
			self::$_APP_SETTINGS = $settings;
			
			//process the request
			Request::process();
		}
		
		/*
		* Kennel::onShutdown()
		*/
		static function onShutdown() {
			if(self::getSetting('application', 'show_benchmark')) self::printBenchmark();
		}
		
		/**
			*	 Kennel::cascade(string $resource, string $type[, string $return]);
			*
			*	 @resource: String. The resource identifier to cascade. Must include the file extension in case of assets.
			*	 @type: String. The type of the resource. Possible values:
			*	        libraries, models, views, controllers, helpers, css, img, js, flash, file
			*	 @return_url: Boolean. Defaults to false. Set to true if the method should return an URL instead of a physical path.
			*/
		function cascade($resource, $type, $return_url=false)
		{
			if (isset(self::$_CASCADE_CACHE[$type][$resource]))
			{
				if ($return_url) return self::$ROOT_URL . self::$_CASCADE_CACHE[$type][$resource];
				else return self::$ROOT_PATH . self::$_CASCADE_CACHE[$type][$resource];
			}
			
			switch ($type)
			{
				case 'libraries':
					$application_path = "/application/libraries/{$resource}.php";
					$module_path = "/modules/{module}/libraries/{$resource}.php";
					$system_path = "/system/core/{$resource}.php";
					break;
				case 'schemas':
					$application_path = "/application/models/{$resource}.xml";
					$module_path = "/modules/{module}/models/{$resource}.xml";
					$system_path = "/system/models/{$resource}.xml";
					break;
				case 'models':
				case 'views':
				case 'controllers':
				case 'helpers':
					$application_path = "/application/{$type}/{$resource}.php";
					$module_path = "/modules/{module}/{$type}/{$resource}.php";
					$system_path = "/system/{$type}/{$resource}.php";
					break;
				case 'css':
				case 'img':
				case 'js':
				case 'flash':
				case 'file':
					$application_path = "/application/assets/{$type}/{$resource}";
					$module_path = "/modules/{module}/assets/{$type}/{$resource}";
					$system_path = "/system/assets/{$type}/{$resource}";
					break;
			}
			
			// Application (user) resource
			if (is_file(Kennel::$ROOT_PATH . $application_path))
			{
				self::$_CASCADE_CACHE[$type][$resource] = $application_path;
				if ($return_url) return Kennel::$ROOT_URL . $application_path;
				else return Kennel::$ROOT_PATH . $application_path;
			}
			
			// Module resource
			if(!self::$MODULES) self::fetchModules();
			foreach (self::$MODULES as $module=>$info)
			{
				$path = str_replace('{module}', $module, $module_path);
				if (is_file(Kennel::$ROOT_PATH . $path))
				{
					self::$_CASCADE_CACHE[$type][$resource] = $path;
					if ($return_url) return Kennel::$ROOT_URL . $path;
					else return Kennel::$ROOT_PATH . $path;
				}
			}
			
			// System resource
			if(is_file(Kennel::$ROOT_PATH . $system_path))
			{
				self::$_CASCADE_CACHE[$type][$resource] = $system_path;
 				if ($return_url) return Kennel::$ROOT_URL . $system_path;
				else return Kennel::$ROOT_PATH . $system_path;
			}
			
			// Resource not found
			return false;
		}
		
		/*
		* Kennel::printBenchmark();
		*/
		static function printBenchmark() {
			//finish the benchmark
			self::$time_final = microtime(true);
			print '<br /><small style="color: #666"><p>Finished in <strong>' . (self::$time_final - self::$time_init) . '</strong> seconds</p>';
			print '<p>Using <strong>' . (memory_get_usage() / 1024) . '</strong> kbs</p></small>';
		}
		
		/*
		* Kennel::getSetting(str $category, str $setting)
		*/
		static function getSetting($category, $setting) {
			return self::$_APP_SETTINGS[$category][$setting];
		}
		
		/*
		* Kennel::getPath(str $directory)
		*/
		static function getPath($directory='') {
			return trim(self::$ROOT_PATH, '/') . '/' . $directory;
		}
		
		/*
		* Kennel::controllerAction(misc $controller, string $action, array $args);
		*/
		static function controllerAction($controller, $action='index', $args=null)
		{
			// Accepts both strings and objects
			if (is_string($controller))
			{
				$controller_class = ucfirst($controller) . "_controller";
				self::$CONTROLLER_INSTANCE = Controller::getInstance($controller_class);
			} else {
				// Set the controller as the current controller instance
				self::$CONTROLLER_INSTANCE = $controller;
			}
			
			// Check for the existance of the action as a method in the controller class
			if(method_exists(self::$CONTROLLER_INSTANCE, $action))
			{
				// Call the method
				if (is_array($args)) call_user_func_array(array(self::$CONTROLLER_INSTANCE, $action), $args);
				else  call_user_func(array(self::$CONTROLLER_INSTANCE, Request::$ACTION));
			}
			// Workaround in case the action does not exist
			else
			{
				// Select the 'notfound' method if present, or the 'index' method if not
				if(method_exists(self::$CONTROLLER_INSTANCE, 'notfound')) Request::$ACTION = 'notfound';
				else Request::$ACTION = 'index';
				
				// Call the workaround method
				if(is_array($args)) call_user_func_array(array(self::$CONTROLLER_INSTANCE, Request::$ACTION), $args);
				else call_user_func(array(self::$CONTROLLER_INSTANCE, Request::$ACTION));
			}
		}
		
		/*
		* Kennel::isModuleController(string $controller)
		*/
		static function fetchModules()
		{
			// Get the module settings
			self::$MODULE_SETTINGS = parse_ini_file('modulesettings.ini', true);
			
			// Initialize the variable
			self::$MODULES = array();
			
			// Get through the file list in the modules directory
			$files = scandir(self::getPath('modules'));
			foreach ($files as $file)
			{
				// Get only valid directories
				if (is_dir(Kennel::getPath('modules') . '/' . $file) &&
					$file != '.' && $file != '..' && $file != '.svn')
					{
						include Kennel::getPath('modules') . "/{$file}/info.php";
						self::$MODULES[$file] = $info[$file];
					}
			}
		}
		
		static function getModuleOption($module, $option)
		{
			if (isset(self::$MODULE_SETTINGS[$module][$option])) return self::$MODULE_SETTINGS[$module][$option];
			else return null;
		}
		
	}
?>