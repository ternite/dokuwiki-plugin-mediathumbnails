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
		$thumbnailpath = "Thumbnails/thumbnail.png";
		
		$mediapath = substr($match, 12, -2); //strip markup
		$filepath = mediaFN($mediapath);
		//$filepath = str_replace('\\', DIRECTORY_SEPARATOR, $filepath);
		
		$zip = new ZipArchive;
		
		if ($zip->open($filepath) !== TRUE) {
			// odt file does not exist
			return array();
		}
		
		if ($zip->locateName($thumbnailpath) !== false) {
			// thumbnail file exists
			$fp = $zip->getStream($thumbnailpath);
			if(!$fp) {
				return array();
			}
			
			$thumbnaildata = '';
			while (!feof($fp)) {
				$thumbnaildata .= fread($fp, 8192);
			}
			
			fclose($fp);
			
			// write thumbnail file to media folder
			$filedir = dirname($filepath);
			$filename = basename($filepath);
			$extended_filename = substr($filename,0,strrpos($filename,'.')).".thumbnail".strrchr($thumbnailpath,'.');
			
			$filepath_thumbnail = $filedir . DIRECTORY_SEPARATOR . $extended_filename;
			file_put_contents($filepath_thumbnail, $thumbnaildata);
			
			// give media path to renderer
			$mediapath_thumbnail = substr($mediapath,0,strrpos($mediapath,':')) . ":" . $extended_filename;
			return array($mediapath_thumbnail);
		}

		return array();
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
		$mediapath_thumbnail = $data[0];
		
        if ($mode == 'xhtml') {
			
			$src = ml($mediapath_thumbnail,array());
			
			$i             = array();
			$i['width']    = '100px';
			//$i['height']   = $h;
			//$i['border']   = 0;
			//$i['alt']      = $this->_meta($img,'title');
			$i['class']    = 'tn';
			$iatt = buildAttributes($i);
			
			$renderer->doc .= "{{".$mediapath_thumbnail."?100}}". '<img src="'.$src.'" '.$iatt.' />';
            return true;
			
        } elseif ($mode == 'odt') {
			
			// TODO: yet to implement
			$renderer->cdata("");
			return true;
			
		}

        return false;
    }
}

