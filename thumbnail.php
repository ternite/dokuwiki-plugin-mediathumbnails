<?php
/**
 * DokuWiki Plugin mediathumbnails (thumbnail class)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Thomas Schäfer <thomas.schaefer@itschert.net>
 */
 
require('thumb_engines.php');

function getFileSuffix(string $file) {
	return substr(strrchr($file,'.'),1);
}

class thumbnail {
	
	private $source_filepath;
	private $source_mediapath;
	private ?thumb_engine $thumb_engine = null;
	private int $max_dimension;
	
	private static $formats;
	private static ?bool $pdf_support = null;
	private static ?bool $image_support = null;
	private static ?bool $no_ghostscript_support = null;
	private static ?bool $no_imagick_pdf_readwrite = null;
	
	private static function testDependencies() {
		
		self::$image_support = false;
		self::$pdf_support = false;
		self::$no_ghostscript_support = false;
		self::$no_imagick_pdf_readwrite = false;
		
		if (class_exists ("Imagick")) {
			// determine file formats supported by ImageMagick
			self::$formats = \Imagick::queryformats();
			
			if (count(self::$formats) > 0) {
				self::$image_support = true;
				if (in_array("PDF", self::$formats)) {
					// Check if GhostScript will answer!
					try {
						// blank.pdf is an empty reference PDF file to test if GhostScript will react upon loading the file into ImageMagick
						$im = new imagick(realpath("lib/plugins/mediathumbnails/blank.pdf")."[0]");
						$im->clear(); 
						$im->destroy();
						self::$pdf_support = true;
					} catch (ImagickException $e) {
						if (strpos($e,"PDFDelegateFailed") !== false) {
							self::$no_ghostscript_support = true;
						}
						if (strpos($e,"security policy") !== false) {
							self::$no_imagick_pdf_readwrite = true;
						}
						self::$pdf_support = false;
					}
					
				}
			}
			
		}
	}
	public static function supportsPDF() {
		if (self::$pdf_support === null) {
			self::testDependencies();
		}
		return self::$pdf_support;
	}
	public static function supportsImages() {
		if (self::$image_support === null) {
			self::testDependencies();
		}
		return self::$image_support;
	}
	public static function ghostScriptFailed() {
		if (self::$no_ghostscript_support === null) {
			self::testDependencies();
		}
		return self::$no_ghostscript_support;
	}
	public static function imagickPDFpolicyFailed() {
		if (self::$no_imagick_pdf_readwrite === null) {
			self::testDependencies();
		}
		return self::$no_imagick_pdf_readwrite;
	}
	
	public function __construct(string $source_filepath, DokuWiki_Syntax_Plugin $plugin, bool $ismediapath = true) {
		
		if ($ismediapath) {
			$this->source_mediapath = $source_filepath;
			$this->source_filepath = mediaFN($source_filepath);
		} else {
			$this->source_mediapath = false;
			$this->source_filepath = $source_filepath;
		}
		
		$this->max_dimension = $plugin->getConf('thumb_max_dimension');
		
		// Now attach the correct thumb_engine for the file type of the source file
		//TODO: check for extension "fileinfo", then check for MIME type: if (mime_content_type($filepath_local_file) == "application/pdf") {
		$sourceFileSuffix = getFileSuffix($this->source_filepath);
		if ($sourceFileSuffix == "pdf") {
			// file suffix is pdf, so assume it's a PDF file
			if (self::supportsPDF()) {
				$this->thumb_engine = new thumb_pdf_engine($this);
			} else {
				if (self::ghostScriptFailed()) {
					dbg("plugin mediathumbnails: PDF files are supported, but not on this system.\nMost likely, ImageMagick and its PHP extension imagick are installed properly, but GhostScript is not.\nPlease refer to the plugin documentation for a description of the dependencies.");
				} else if(self::imagickPDFpolicyFailed()) {
					dbg("plugin mediathumbnails: PDF files are supported, but not on this system.\nMost likely, ImageMagick is configured so that PDF conversion is not allowed due to security policies.\nPlease refer to the plugin documentation for a description of the dependencies.");
				} else {
					dbg("plugin mediathumbnails: PDF files are supported, but not on this system.\nMost likely, ImageMagick or its PHP extension imagick are not installed properly.\nPlease refer to the plugin documentation for a description of the dependencies.");
				}
			}
		} else if (self::supportsImages() && in_array(strtoupper($sourceFileSuffix), self::$formats)) {
			// file suffix is in support list of ImageMagick
			$this->thumb_engine = new thumb_img_engine($this);
		} else if (!self::supportsImages()) {
			dbg("plugin mediathumbnails: Image files are supported, but not on this system.\nPlease refer to the plugin documentation for a description of the dependencies.");
		} else {
			// last resort: check if the source file is a ZIP file and look for thumbnails, therein
			$this->thumb_engine = new thumb_zip_engine($this,$plugin->getConf('thumb_paths'));
		}
	}
	
	public function getMaxDimension() {
		return $this->max_dimension;
	}
	
	public function create() {
		if (!$this->thumb_engine) {
			return false;
		}
		
		return $this->thumb_engine->act();
	}
	
	public function getSourceFilepath() {
		return $this->source_filepath;
	}
	
	protected function getFilename() {
		
		return basename($this->source_filepath) . ".thumb".$this->max_dimension.".".$this->thumb_engine->getFileSuffix();
	}
	
	public function getFilepath() {
		return dirname($this->source_filepath) . DIRECTORY_SEPARATOR . $this->getFilename();
	}
	
	public function getMediapath() {
		if ($this->source_mediapath !== false) {
			return substr($this->source_mediapath,0,strrpos($this->source_mediapath,':')) . ":" . $this->getFilename();
		} else {
			return false;
		}
	}
	
	public function getTimestamp() {
		return file_exists($this->getFilepath()) ? filemtime($this->getFilepath()) : false;
	}
}