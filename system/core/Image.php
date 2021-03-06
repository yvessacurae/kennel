<?php
	class Image
	{
		private $image;
		var $width;
		var $height;
		private $mime;
		private $path;
		
		function __construct($image) {
			if (is_string($image))
			{
				if (!is_file($image)) return debug::error("Image <strong>{$image}</strong> not found at");
				$this->path = pathinfo($image);
				$imageSize = getimagesize($image);
				$this->width = $imageSize[0];
				$this->height = $imageSize[1];
				$this->mime = $imageSize['mime'];
				
				switch ($this->mime)
				{
					case 'image/jpeg':
						$this->image = imagecreatefromjpeg($image);
						break;
					case 'image/png':
						$this->image = imagecreatefrompng($image);
						break;
					case 'image/gif':
						$this->image = imagecreatefromgif($image);
						break;
				}
			} else {
				$this->width = imagesx($image);
				$this->height = imagesy($image);
				$this->image = $image;
			}
		}
		
		function __destruct()
		{
			if (!$this->image) return;
			imagedestroy($this->image);
		}
		
		function output($debug=false) {
			if (!$debug) header("Content-Type: image/jpeg");
			imagejpeg($this->image, null, 95);
		}
		
		function fit($width, $height, $bgColor=array(255, 0, 0))
		{
			// Does exactly the same thing as Image::thumb, but fits the whole original image in the destination image instead of croping
			//TODO: set background color
			if (!$this->image) return;
			$ratio = $this->width / $this->height;
		 
			if ($width / $height < $ratio) {
				 $new_height = $width / $ratio;
				 $new_width = $width;
			} else {
				 $new_width = $height * $ratio;
				 $new_height = $height;
			}
		 
			$x_mid = $new_width / 2;  //horizontal middle
			$y_mid = $new_height / 2; //vertical middle
		 
			$process = imagecreatetruecolor(round($new_width), round($new_height));
		 
			imagecopyresampled($process, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
			$fit = imagecreatetruecolor($width, $height);
			imagecopyresampled($fit, $process, 0, 0, ($x_mid-($width/2)), ($y_mid-($height/2)), $width, $height, $width, $height);
			
			imagedestroy($process);
			return $this->image = $fit;
		}
		
		function thumb($width, $height)
		{
		  // If one of the dimensions is null or zero, assume a proportional value,
		  // not allowing it to be more than 3x greater than the other
		  if (!$height) {
        $reduction = $width / $this->width;
        $height = $this->height * $reduction;
        if ($height > 3*$width) $height = 3*$width;
		  }
		  if (!$width) {
        $reduction = $height / $this->height;
        $width = $this->width * $reduction;
        if ($width > 3*$height) $width = 3*$height;
		  }
		  
			// Picks the best fit from the original to the destination image, cropping when necessary
			if (!$this->image) return;
			$ratio = $this->width / $this->height;
		 
			if ($width / $height > $ratio) {
				 $new_height = $width / $ratio;
				 $new_width = $width;
			} else {
				 $new_width = $height * $ratio;
				 $new_height = $height;
			}
		 
			$x_mid = $new_width / 2;  //horizontal middle
			$y_mid = $new_height / 2; //vertical middle
		 
			$process = imagecreatetruecolor(round($new_width), round($new_height));
		 
			imagecopyresampled($process, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
			$thumb = imagecreatetruecolor($width, $height);
			imagecopyresampled($thumb, $process, 0, 0, ($x_mid-($width/2)), ($y_mid-($height/2)), $width, $height, $width, $height);
			
			imagedestroy($process);
			return $this->image = $thumb;
		}
		
		function resize($width, $height)
		{
			if (!$this->image) return null;
			if ($this->width == $width && $this->height == $height) return $this->image;
			
			$resized = imagecreatetruecolor($width, $height);
			imagecopyresampled($resized, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
			
			$this->width = $width;
			$this->height = $height;
			
			$this->image = $resized;
			imagedestroy($resized);
			return $this->image;
		}
		
		function limit($side)
		{
			if (!$this->image) return null;
			if ($this->width < $side && $this->height < $side) return $this->image;
			
			$prop = $this->width > $this->height ? $side / $this->width : $side / $this->height;
			
			$new_w = $this->width * $prop;
			$new_h = $this->height * $prop;
			
			$resized = imagecreatetruecolor($new_w, $new_h);
			imagecopyresampled($resized, $this->image, 0, 0, 0, 0, $new_w, $new_h, $this->width, $this->height);
			
			$this->width = $new_w;
			$this->height = $new_h;
			
			$this->image = $resized;
			return $this->image;
		}
		
		function square($side)
		{
			if (!$this->image) return;
			$prop = $this->width<$this->height?$side/$this->width:$side/$this->height;
			
			$new_w = $this->width * $prop;
			$new_h = $this->height * $prop;
			
			$new_x = ($new_w - $side) / 2;
			$new_y = ($new_h - $side) / 2;
			
			$cropped = imagecreatetruecolor($side, $side);
			imagecopyresampled($cropped, $this->image, 0, 0, $new_x, $new_y, $new_w, $new_h, $this->width, $this->height);
			
			$this->width = $new_w;
			$this->height = $new_w;
			
			return $this->image = $cropped;
		}
		
		function save($filename) {
			if (!$this->image) return;
			imagejpeg($this->image, $filename, 95);
		}
    
	}
?>
