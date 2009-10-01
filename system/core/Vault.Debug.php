<?php
	
	class Debug
	{
		
		static $backtrace;
		static $notice = E_USER_NOTICE;
		static $warning = E_USER_WARNING;
		static $error = E_USER_ERROR;
		
		/*
		* Debug::dump($variable)
		*/
		static function dump($variable, $return=false)
		{
			$dump = '<pre>'.var_export($variable, true).'</pre>';
			if($return) return $dump;
			else print $dump;
		}
		
		/*
		* Debug::backtrace();
		*/
		static function backtrace($level=0, $limit = null, $return=false)
		{
			$full_backtrace = debug_backtrace();
			
			$table = XML::element('table', null, array('border'=>'1'));
			
			$tr = XML::element('tr', $table);
			$th = XML::element('th', $tr, array('colspan'=>4), 'Backtrace');
			
			$tr = XML::element('tr', $table);
			$th = XML::element('th', $tr, null, 'class');
			$th = XML::element('th', $tr, null, 'function');
			$th = XML::element('th', $tr, null, 'file');
			$th = XML::element('th', $tr, null, 'line');

			for($n=0; $n<count($full_backtrace); $n++)
			{
				if($limit === $n) break;
				$backtrace = $full_backtrace[$n];
				$tr = XML::element('tr', $table);
				
				if(isset($backtrace['class'])) $td = XML::element('td', $tr, null, $backtrace['class']);
				else $td = XML::element('td', $tr);
				
				if(isset($backtrace['function'])) $td = XML::element('td', $tr, null, $backtrace['function']);
				else $td = XML::element('td', $tr);
				
				if(isset($backtrace['file'])) $td = XML::element('td', $tr, null, $backtrace['file']);
				else $td = XML::element('td', $tr);
				
				if(isset($backtrace['line'])) $td = XML::element('td', $tr, null, $backtrace['line']);
				else $td = XML::element('td', $tr);
			}
			
			if(!$return) print $table;
			else return $table;
		}
		
		/*
		* Debug::dumpError($error)
		*/
		static function dumpError($error)
		{
			$table = XML::element('table', null, array('border'=>'1'));
			
			$tr = XML::element('tr', $table);
			$th = XML::element('th', $tr, array('colspan'=>'2'), "Error");
			
			$tr = XML::element('tr', $table);
			$th = XML::element('th', $tr, null, 'error');
			$td = XML::element('td', $tr, null, $error);
			
			if(Settings::get('application', 'debug_mode')) self::backtrace(3, 1);
			
			print $table;
			die();
		}
		
		static function error_handler($errno = E_USER_WARNING, $errstr)
		{
			self::$backtrace = debug_backtrace();
			
			$ignore = array(E_STRICT);
			
			if(array_search($errno, $ignore) === false && Settings::get('debug'))
			{
				print '<pre style="text-align: left;">';
				debug_print_backtrace();
				print '</pre>';
			}
			
			if(Settings::get('log_errors')) {
				error_log("{$message}", 3, "errorlog.txt");
			}
			
		}
		
		static function error($errstr, $backtrace=false)
		{
			if(Settings::get('debug_mode'))
			{
				self::dumpError($errstr);
				die();
			}
		}
		
	}
?>
