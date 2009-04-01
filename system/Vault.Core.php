<?php
	function __autoload($resource) {
		//controller
		if (substr($resource, -11) == '_controller')
		{
			$controller_name = strtolower(substr($resource, 0, (strlen($resource) - 11)));
			if (is_file(Vault::getPath('controllers') . "/{$controller_name}.php"))
				require_once Vault::getPath('controllers') . "/{$controller_name}.php";
		}
		elseif (is_file(Vault::getPath('system') . "/Vault.{$resource}.php"))
		{
			require_once Vault::getPath('system') . "/Vault.{$resource}.php";
		}
	}
	
	class Vault {
		
		static $app_settings;
		static $app_index_path;
		static $app_main_controller;
		
		static $request_query_string;
		static $request_uri;
		
		static $time_init;
		static $time_final;
		
		/*
		* Vault::init()
		*/
		static function init() {
			//begin the benchmark
			self::$time_init = microtime(true);
			register_shutdown_function(array("Vault","onShutdown"));
			
			//get the application path
			self::$app_index_path = dirname($_SERVER["SCRIPT_FILENAME"]);
			
			//get the application settings
			require_once('settings.php');
			self::$app_settings = $settings;
			
			//process the request
			self::processRequest();
		}
		
		/*
		* Vault::onShutdown()
		*/
		static function onShutdown() {
			if(self::getSetting('application', 'show_benchmark')) self::printBenchmark();
		}
		
		/*
		* Vault::printBenchmark();
		*/
		static function printBenchmark() {
			//finish the benchmark
			self::$time_final = microtime(true);
			print '<br /><small style="color: #666"><p>Finished in <strong>' . (self::$time_final - self::$time_init) . '</strong> seconds</p>';
			print '<p>Using <strong>' . (memory_get_usage() / 1024) . '</strong> kbs</p></small>';
		}
		
		/*
		* Vault::getSetting(str $category, str $setting)
		*/
		static function getSetting($category, $setting) {
			return self::$app_settings[$category][$setting];
		}
		
		/*
		* Vault::getPath(str $directory)
		*/
		static function getPath($directory='') {
			return self::$app_index_path . self::getSetting('path', $directory);
		}
		
		/*
		* Vault::requestSystemFile(str $file)
		*/
		static function requireSystemFile($file) {
			$file = ucfirst($file);
			require_once(self::getPath('system') . "/Vault.$file.php");
		}
		
		/*
		* Vault::requireResource(str $file)
		*/
		static function requireResource($resource_type, $resource_name) {
			switch($resource_type) {
				case 'controller':
					$resource_name = ucfirst($resource_name);
					require_once(self::getPath('controllers') . "/$resource_name.php");
					break;
				case 'view':
					require_once(self::getPath('views') . "/$resource_name.php");
					break;
				case 'template':
					break;
			}
		}
		
		/*
		* Vault::getResourcePath(string $resource_type, string $resource_name);
		*/
		static function getResourcePath($resource_type, $resource_name) {
			switch($resource_type) {
				case 'controller':
					$resource_name = ucfirst($resource_name);
					return self::getPath('controllers') . "/controller.$resource_name.php";
				case 'view':
					return self::getPath('views') . "/$resource_name.php";
				case 'template':
					break;
			}
		}
		
		/*
		* Vault::processRequest()
		*/
		static function processRequest() {
			//instancialize the main controller
			self::requireSystemFile('controller');
			self::requireResource('controller', 'Main');
			self::$app_main_controller = new Main_controller();
			
			//get the request args
			if(self::getSetting('application', 'use_mod_rewrite')) {
				$document_root_strlen = strlen($_SERVER['DOCUMENT_ROOT']);
				$app_uri_location = substr(self::$app_index_path, $document_root_strlen);
				//todo
			} else {
				self::$request_uri = $_SERVER['QUERY_STRING'];
				$request_args = array_filter(explode('/', self::$request_uri));
			}
			
			//convert $request_args to lowercase
			foreach($request_args as $key=>$value) {
				$request_args[$key] = strtolower($value);
			}
			
			//display the Home page if no request_args are suplied
			if(count($request_args) == 0) {
				call_user_func(array(&self::$app_main_controller, "index"));
				
				
			//first check: method in the main controller
			} elseif(method_exists(self::$app_main_controller, $request_args[0])) {
				call_user_func_array(array(&self::$app_main_controller, array_shift($request_args)), $request_args);
				
			//second check: controller...
			} elseif(is_file(self::getPath('controllers') . "/{$request_args[0]}.php")) {
				self::requireResource('controller', $request_args[0]);
				$controller_name = "{$request_args[0]}_controller";
				$controller = new $controller_name();
				
				//...index...
				if(count($request_args1) == 1 || !method_exists(&$controller, $request_args[1])) {
					call_user_func_array(array(&$controller, 'index'), array_slice($request_args, 2));
					
				//...or specified method (a second request arg is present and exists as method)
				} elseif(count($request_args) > 1 && method_exists(&$controller, $request_args[1])) {
					call_user_func_array(array(&$controller, $request_args[1]), array_slice($request_args, 2));
				}
			//if the first request argument is not a method of the main controller nor a controller, send 404
			} else {
				if(method_exists(&self::$app_main_controller, 'notfound')) {
					call_user_func(array(&self::$app_main_controller, 'notfound'));
				} else {
					call_user_func(array(&self::$app_main_controller, 'index'));
				}
			}
		}
		
		/*
		* Vault::debugRequest()
		*/
		static function debugRequest() {
			print '<table><tr><td>APP_INDEX_PATH:</td><td>';
			var_dump(self::$app_index_path);
			print '</td></tr>';
			print '<tr><td>REQUEST_URI:</td><td>';
			var_dump(self::$request_uri);
			print '</td></tr>';
			print '<tr><td>HTTP_HOST:</td><td>';
			var_dump($_SERVER['HTTP_HOST']);
			print '</td></tr>';
			print '<tr><td>DOCUMENT_ROOT:</td><td>';
			var_dump($_SERVER['DOCUMENT_ROOT']);
			print '</td></tr></table>';
		}
		
		/*
		* Vault::dump($variable)
		*/
		static function dump($variable, $return=false) {
			$dump = '<pre>'.var_export($variable, true).'</pre>';
			if($return) return $dump;
			else print $dump;
		}
		
	}
?>