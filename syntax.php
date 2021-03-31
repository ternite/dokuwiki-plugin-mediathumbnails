<?php
/**
 * DokuWiki Plugin mediathumbnails (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Thomas Schäfer <thomas.schaefer@itschert.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_mediathumbnails extends DokuWiki_Syntax_Plugin {
	
	/**
     * @return string Syntax mode type
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType()
    {
        return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort()
    {
        return 1;
    }
	
    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode)
    {
		$this->Lexer->addSpecialPattern("{{thumbnail>.+?}}", $mode, substr(get_class($this), 7));
	}

    /**
     * Handle matches of the mediathumbnails syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
		// Locate the given media file and check if it can be opened as zip
		$mediapath_file = substr($match, 12, -2); //strip markup
		
		$thumb = new thumbnail($mediapath_file,$this);
		if ($thumb->create()) {
			return array($mediapath_file,$thumb->getMediapath());
		}
		
		return array($mediapath_file);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
		list ($mediapath_file, $mediapath_thumbnail) = $data;

        if ($mode == 'xhtml') {
			
			// check if a thumbnail file was found
			if (!$mediapath_thumbnail) {
				if ($this->getConf('show_missing_thumb_error')) {
					$renderer->doc .= trim($this->getConf('no_thumb_error_message')) . " " . $mediapath_file;
					return true;
				} else {
					return false;
				}
			}
				
			$src = ml($mediapath_thumbnail,array());
			
			$i             = array();
			$i['width']    = $this->getConf('thumb_width');
			//$i['height']   = '';
			$i['title']      = $mediapath_file;
			$i['class']    = 'tn';
			$iatt = buildAttributes($i);
			
			$renderer->doc .= 	($this->getConf('link_to_media_file') ? '<a href="/lib/exe/fetch.php?media=' . $mediapath_file . '">' : '') .
								'<img src="'.$src.'" '.$iatt.' />' .
								($this->getConf('link_to_media_file') ? '</a>' : '');
            return true;
			
        } elseif ($mode == 'odt') {
			
			// TODO: yet to implement
			$renderer->cdata("");
			return true;
			
		}

        return false;
    }
}

class thumbnail {
	
	private $source_filepath;
	private $source_mediapath;
	private ?thumb_engine $thumb_engine = null;
	
	public function __construct(string $source_filepath, DokuWiki_Syntax_Plugin $plugin, bool $ismediapath = true) {
		
		if ($ismediapath) {
			$this->source_mediapath = $source_filepath;
			$this->source_filepath = mediaFN($source_filepath);
		} else {
			$this->source_mediapath = false;
			$this->source_filepath = $source_filepath;
		}
		
		// Now attach the correct thumb_engine for the file type of the source file
		//TODO: check for extension "fileinfo", then check for MIME type: if (mime_content_type($filepath_local_file) == "application/pdf") {
		if (substr($this->source_filepath,-4) == ".pdf") {
			$this->thumb_engine = new thumb_pdf_engine($this,$plugin->getConf('thumb_width'));
		} else {
			$this->thumb_engine = new thumb_zip_engine($this,$plugin->getConf('thumb_width'),$plugin->getConf('thumb_paths'));
		}
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
		
		return basename($this->source_filepath) . ".thumb.".$this->thumb_engine->getFileSuffix();
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

abstract class thumb_engine {
	
	private ?thumbnail $thumbnail = null;
	private int $width;
	
	public function __construct(thumbnail $thumbnail, int $width) {
		$this->thumbnail = $thumbnail;
		$this->width = $width;
	}
	
	protected function getSourceFilepath() {
		return $this->thumbnail->getSourceFilepath();
	}
	
	protected function getTargetFilepath() {
		return $this->thumbnail->getFilepath();
	}
	
	protected function getTargetWidth() {
		return $this->width;
	}
	
	public function act() {
		if ($this->act_internal()) {
			// Set timestamp to the source file's timestamp (this is used to check in later passes if the file already exists in the correct version).
			touch($this->getTargetFilepath(), filemtime($this->getSourceFilepath()));
			return true;
		}
		return false;
	}
	
	public abstract function act_internal();
	
	public abstract function getFileSuffix();
}
	
class thumb_pdf_engine extends thumb_engine {
	
	public function getFileSuffix() {
		return "jpg";
	}
	
	public function act_internal() {
		$im = new imagick( $this->getSourceFilepath()."[0]" ); 
		$im->setImageColorspace(255); 
		$im->setResolution(300, 300);
		$im->setCompressionQuality(95); 
		$im->setImageFormat('jpeg');
		//$im->resizeImage(substr($this->getConf('thumb_width'),-2),0,imagick::FILTER_LANCZOS,0.9);
		$im->writeImage($this->getTargetFilepath());
		$im->clear(); 
		$im->destroy();
		
		return true;
	}
}
	
class thumb_zip_engine extends thumb_engine {
	
	private array $thumb_paths;
	private $file_suffix = "";
	
	public function __construct(thumbnail $thumbnail, int $width, array $thumb_paths) {
		parent::__construct($thumbnail,$width);
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
		$timestamp_local_file = filemtime($this->getSourceFilepath());
		
		// The media file exists and acts as a zip file!
		
		// Check all possible paths (configured in configuration key 'thumb_paths') if there is a file available
		foreach($this->thumb_paths as $thumbnail_path) {
			$this->file_suffix = substr(strrchr($thumbnail_path,'.'),1);
	
			if ($zip->locateName($thumbnail_path) !== false) {

				if (file_exists($this->getTargetFilepath()) && filemtime($this->getTargetFilepath()) == $timestamp_local_file) {
					// A thumbnail file for the current file version has already been created, just report that the file is in place by returning true:
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