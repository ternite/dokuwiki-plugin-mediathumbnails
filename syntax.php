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
		$this->Lexer->addSpecialPattern("{{[ ]*thumbnail>.+?}}", $mode, substr(get_class($this), 7));
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
        // extract the internal reference from the syntax so it can be handled like a normal media file
        $internalreference = str_replace("thumbnail>","",$match);//substr($match, 12, -2); //strip markup

        // let dokuwiki core parse the media syntax inside the thumbnail syntax
		$image_params = Doku_Handler_Parse_Media($internalreference);
		
		$thumb = new thumbnail($image_params['src'],$this);

        // if source file does not exist, return an array with the second element being null
        if (!$thumb->getSourceFileExists()) {
            return array('missing_src_file',$image_params['src'],null);
        }

        // create thumbnail if missing
        $thumb->create_if_missing();

		if ($thumb->creation_has_failed()) {
            // thumbnail creation failed, return an array with the second element being null
            return array('missing_thumb_file',$thumb->getMediapath(),null);
        }

        // use the thumbnail's mediapath and the image reference's parameters for rendering
        $thumbnail_params = $image_params;
        $thumbnail_params['src'] = $thumb->getMediapath();

		return array($thumb->getSourceFilepath(),$thumb->getMediapath(),$thumbnail_params);
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
		list ($errortype, $errorpath, $image_params) = $data;
		
        if ($mode == 'xhtml' || $mode == 'odt') {

            // check if media source file exists
			if ($errortype === 'missing_src_file') {
				if ($this->getConf('show_missing_thumb_error')) {
					$renderer->doc .= trim($this->getConf('no_media_error_message')) . " " . $errorpath;
					return true;
				} else {
					return false;
				}
			}
			
			// check if a thumbnail file was found
			if ($errortype === 'missing_thumb_file') {
				if ($this->getConf('show_missing_thumb_error')) {
					$renderer->doc .= trim($this->getConf('no_thumb_error_message')) . " " . $errorpath;
					return true;
				} else {
					return false;
				}
			}
            
            $capped_width = $image_params['width'] > $this->getConf('thumb_max_dimension') ? $this->getConf('thumb_max_dimension') : $image_params['width'];
            $capped_height = $image_params['height'] > $this->getConf('thumb_max_dimension') ? $this->getConf('thumb_max_dimension') : $image_params['height'];
            
            $renderer->internalmedia(
                $image_params['src'],
                $image_params['title'],
                $image_params['align'],
                $capped_width,
                $capped_height,
                $image_params['cache'],
                $image_params['linking'],
                false
            );

            return true;
			
        }

        return false;
    }
}