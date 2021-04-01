<?php
/**
 * Options for the mediathumbnails plugin
 *
 * @author Thomas Schäfer <thomas.schaefer@itschert.net>
 */

$meta['thumb_max_dimension']      = array('numeric','_min' => '1');
$meta['thumb_paths']              = array('array');
$meta['link_to_media_file']       = array('onoff');
$meta['show_missing_thumb_error'] = array('onoff');
$meta['no_thumb_error_message']   = array('string');
