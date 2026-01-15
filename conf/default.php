<?php
/**
 * Default settings for the mediathumbnails plugin
 *
 * @author Thomas Schäfer <thomas.schaefer@itschert.net>
 */

$conf['thumb_max_dimension']      = '100';
$conf['thumb_paths']              = array('Thumbnails/thumbnail.png','docProps/thumbnail.jpeg'); // first entry: odt file, second entry: MS Office file
$conf['link_to_media_file']       = true;
$conf['show_missing_thumb_error'] = true;
$conf['no_thumb_error_message']   = "No thumbnail in "; // media path appended here
$conf['no_media_error_message']   = "No source media in "; // media path appended here

