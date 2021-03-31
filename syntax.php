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
		$filepath_local_file = mediaFN($mediapath_file);
		$timestamp_local_file = file_exists($filepath_local_file) ? filemtime($filepath_local_file) : false;
		
		//TODO: check for extension "fileinfo", then check for MIME type: if (mime_content_type($filepath_local_file) == "application/pdf") {
		if (substr($mediapath_file,-4) == ".pdf") {
			//$extended_filename = basename($filepath_local_file) . ".".$this->getConf('thumb_width').".jpg"; 
			$extended_filename = basename($filepath_local_file) . ".thumb.jpg"; 
			$filepath_thumbnail = dirname($filepath_local_file) . DIRECTORY_SEPARATOR . $extended_filename;
			$mediapath_thumbnail = substr($mediapath_file,0,strrpos($mediapath_file,':')) . ":" . $extended_filename;
			
			$im = new imagick( $filepath_local_file."[0]" ); 
			$im->setImageColorspace(255); 
			$im->setResolution(300, 300);
			$im->setCompressionQuality(95); 
			$im->setImageFormat('jpeg');
			//$im->resizeImage($this->getConf('thumb_width')*3,0,imagick::FILTER_LANCZOS ,1);
			$im->writeImage($filepath_thumbnail);
			$im->clear(); 
			$im->destroy();
			return array($mediapath_file,$mediapath_thumbnail);
		}
		
		$zip = new ZipArchive;
		if ($zip->open($filepath_local_file) !== TRUE) {
			// media file does not exist
			return array($mediapath_file);
		}
		
		// The media file exists and acts as a zip file!
		
		// Check all possible paths (configured in configuration key 'thumb_paths') if there is a file available
		$thumb_paths_to_investigate = $this->getConf('thumb_paths');
		
		foreach($thumb_paths_to_investigate as $thumbnail_path) {
			$thumbnail_ending = strrchr($thumbnail_path,'.');
	
			if ($zip->locateName($thumbnail_path) !== false) {
						
				// The thumbnail file exists, so prepare more information, now!
				$extended_filename = basename($filepath_local_file) . ".thumb" . $thumbnail_ending;
				$filepath_thumbnail = dirname($filepath_local_file) . DIRECTORY_SEPARATOR . $extended_filename;
				$mediapath_thumbnail = substr($mediapath_file,0,strrpos($mediapath_file,':')) . ":" . $extended_filename;

				if (file_exists($filepath_thumbnail) && filemtime($filepath_thumbnail) == $timestamp_local_file) {
					// A thumbnail file for the current file version has already been created, don't extract it again, but give the renderer all needed information!
					return array($mediapath_file, $mediapath_thumbnail);
				}
				
				// Get the thumbnail file!
				$fp = $zip->getStream($thumbnail_path);
				if(!$fp) {
					return array();
				}
				
				$thumbnaildata = '';
				while (!feof($fp)) {
					$thumbnaildata .= fread($fp, 8192);
				}
				
				fclose($fp);
				
				// Write thumbnail file to media folder
				file_put_contents($filepath_thumbnail, $thumbnaildata);
				
				// Set timestamp to the media file's timestamp (this is used to check in later passes if the file already exists in the correct version).
				touch($filepath_thumbnail, $timestamp_local_file);
				
				// Give media path to renderer
				return array($mediapath_file, $mediapath_thumbnail);
			}
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

