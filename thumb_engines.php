<?php
/**
 * DokuWiki Plugin mediathumbnails (thumb_engine class and subclasses)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Thomas Schäfer <thomas.schaefer@itschert.net>
 */
 
abstract class thumb_engine {
	
	private ?thumbnail $thumbnail = null;
	
	public function __construct(thumbnail $thumbnail) {
		$this->thumbnail = $thumbnail;
	}
	
	protected function getSourceFilepath() {
		return $this->thumbnail->getSourceFilepath();
	}
	
	protected function getTargetFilepath() {
		return $this->thumbnail->getFilepath();
	}
	
	protected function getTargetMaxDimension() {
		return $this->thumbnail->getMaxDimension();
	}
	
	public function act() {
		if ($this->act_internal()) {
			// Set timestamp to the source file's timestamp (this is used to check in later passes if the file already exists in the correct version).
			if (filemtime($this->getSourceFilepath()) !== filemtime($this->getTargetFilepath())) {
				touch($this->getTargetFilepath(), filemtime($this->getSourceFilepath()));
			}
			return true;
		}
		return false;
	}
	
	// Checks if a thumbnail file for the current file version has already been created
	protected function thumb_needs_update() {
		return !file_exists($this->getTargetFilepath()) || filemtime($this->getTargetFilepath()) !== filemtime($this->getSourceFilepath());
	}
	
	public abstract function act_internal();
	
	public abstract function getFileSuffix();
}
	
class thumb_pdf_engine extends thumb_engine {
	
	public function getFileSuffix() {
		return "jpg";
	}
	
	public function act_internal() {
		if ($this->thumb_needs_update()) {
			$im = new imagick($this->getSourceFilepath()."[0]");
            // the following line was in the original code. Issue #6 (https://github.com/ternite/dokuwiki-plugin-mediathumbnails/issues/6)
            // indicated there might be problems with colors, so I uncommented the line (TS, 2025-10-11)
			//$im->setImageColorspace(255); 
			$im->setResolution(300, 300);
			$im->setCompressionQuality(95); 
			$im->setImageFormat('jpeg');
			//$im->resizeImage($this->getTargetMaxDimension(),0,imagick::FILTER_LANCZOS,0.9);
			//$im->thumbnailImage($this->getTargetMaxDimension(),$this->getTargetMaxDimension(),true,false);
			$im->writeImage($this->getTargetFilepath());
			$im->clear(); 
			$im->destroy();
			
			// unfortunately, resizeImage or thumbnailImage leads to a black thumbnail in my setup, so I reopen the file and resize it now.
			$im = new imagick($this->getTargetFilepath());
			$im->thumbnailImage($this->getTargetMaxDimension(),$this->getTargetMaxDimension(),true,false);
			$im->writeImage($this->getTargetFilepath());
			$im->clear(); 
			$im->destroy();
			
			return true;
		} else {
			return true;
		}
	}
}

class thumb_img_engine extends thumb_engine {
	
	public function getFileSuffix() {
		return getFileSuffix($this->getSourceFilepath());
	}
	
	public function act_internal() {
		if ($this->thumb_needs_update()) {
			$im = new imagick( $this->getSourceFilepath() );
			$im->thumbnailImage($this->getTargetMaxDimension(),$this->getTargetMaxDimension(),true,false);
			$im->writeImage($this->getTargetFilepath());
			$im->clear(); 
			$im->destroy();
			
			return true;
		} else {
			return true;
		}
	}
}
	
class thumb_zip_engine extends thumb_engine {
	
	private array $thumb_paths;
	private $file_suffix = "";
	
	public function __construct(thumbnail $thumbnail, array $thumb_paths) {
		parent::__construct($thumbnail);
		$this->thumb_paths = $thumb_paths;
	}
	
	public function getFileSuffix() {
		return $this->file_suffix;
	}
	
	public function act_internal() {
		
		$zip = new ZipArchive;
		if ($zip->open($this->getSourceFilepath()) !== true) {
			// file is no zip or cannot be opened
			return false;
		}
		
		// The media file exists and acts as a zip file!
		
		// Check all possible paths (configured in configuration key 'thumb_paths') if there is a file available
		foreach($this->thumb_paths as $thumbnail_path) {
			$this->file_suffix = substr(strrchr($thumbnail_path,'.'),1);
	
			if ($zip->locateName($thumbnail_path) !== false) {

				if (!$this->thumb_needs_update()) {
					return true;
				}
				
				// Get the thumbnail file!
				$fp = $zip->getStream($thumbnail_path);
				if(!$fp) {
					return false;
				}
				
				$thumbnaildata = '';
				while (!feof($fp)) {
					$thumbnaildata .= fread($fp, 8192);
				}
				
				fclose($fp);
				
				// Write thumbnail file to media folder
				file_put_contents($this->getTargetFilepath(), $thumbnaildata);
				
				return true;
			}
		}
		
		return true;
	}
}