<?php
	
	class Pagination {
		
		private $shebang;
		private $pages;
		private $items_per_page;
		private $page_number;
		
		function __construct($shebang, $items_per_page, $urlformat='?pg={page}') {
			$this->shebang = $shebang;
			$this->items_per_page = $items_per_page;
			$this->urlformat = $urlformat;
			$this->pages = array_chunk($shebang, $items_per_page);
		}
		
		function getPage($page_number=1) {
			$this->page_number = $page_number;
			
			return $this->pages[$this->page_number-1];
		}
		
		function printLinks($return=false) {
			$links = '';
			foreach($this->pages as $page => $item) {
				$page++; //user-readable page is aways +1 relative to the array index
				
				if($page == $this->page_number) $links .= '<span>'.$page.'</span>';
				else $links .= '<a href="'.str_replace('{page}', $page, $this->urlformat).'">'.$page.'</a>';
			}
			if($return) return $links;
			else print $links;
		}
		
	}
	
?>
