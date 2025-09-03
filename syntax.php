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

require('thumbnail.php');

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
		$documenttype_command = substr($match, 12, -2); //strip markup
        
        // if the command is using a pipe separator, the mediapath file must be extracted. As of now, the text behind the pipe will be ignored!
        $params = explode("|", $documenttype_command);
        
        $mediapath_file = $params[0];
        
        $caption = $mediapath_file;
        
        if (sizeof($params) > 1) {
            $caption = $params[1];
        }
		
		$thumb = new thumbnail($mediapath_file,$this);
		if ($thumb->create()) {
			return array($mediapath_file,$thumb->getMediapath(),$caption);
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
		list ($mediapath_file, $mediapath_thumbnail, $caption) = $data;
		
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
			
			$i          = array();
			
			$i['title'] = $mediapath_file;
			$i['style'] = "max-width:".$this->getConf('thumb_max_dimension')."px;max-height:".$this->getConf('thumb_max_dimension')."px";
			
			$iatt = buildAttributes($i);
			
			$renderer->doc .= 	($this->getConf('link_to_media_file') ? '<a href="/lib/exe/fetch.php?media=' . $mediapath_file . '">' : '') .
								'<img src="'.$src.'" title="'.$caption.'" '.$iatt.' />' .
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