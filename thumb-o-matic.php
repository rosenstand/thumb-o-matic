<?php
/*
Plugin Name: thumb-o-matic
Version: 0.1
Plugin URI: http://svn.wp-plugins.org/thumb-o-matic/
Description: thumb-o-matic is a very simple thumbnail generator.
Author: Mark Rosenstand
Author URI: http://borkware.net/~mark/
*/

/*
  Copyright (C) 2005 Mark Rosenstand <mark@borkware.net>

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
*/

// Options.
add_option('images_path', get_settings('fileupload_realpath'),
	'Where to look for images.');
add_option('images_url', get_settings('fileupload_url'),
	'Where to link images.');
add_option('cache_thumbs', 'true',
	'Whether to cache thumbnails.');
add_option('thumb_cache_dir', '/thumbs',
	'Where to store cached thumbnails, relative to upload directory.');
add_option('thumb_size', '160',
	'Default thumbnail size.');

// Filter all posts.
add_filter('the_content', 'tom_parser', 4);

function tom_parser($post) {
	// Parse <thumb /> tags with tom_tag_parser().
	return preg_replace_callback('|<thumb .* />|',
		'tom_tag_parser', $post);
}

function tom_tag_parser($thumb_tags) {
	$output = '';
	foreach ($thumb_tags as $thumb_tag) {
		preg_match('|src="(.*?)"|', $thumb_tag, $matches);
		$filename = str_replace(get_settings('siteurl'), '', $matches[1]);

		// Generate a thumbnail.
		tom_create_thumbnail($filename);

		$image_url = get_option('images_url').'/'.$filename;
		$thumb_url = get_option('images_url').get_option('thumb_cache_dir').'/'.$filename;

		// Replace the fictional thumb tag.
		$img_tag = str_replace('<thumb ', '<img ', $thumb_tag);
		// Show the thumbnail instead of the original.
		$img_tag = str_replace($filename, $thumb_url, $img_tag);
		// Use our own CSS class.
		$img_tag = str_replace(' />', ' class="thumb-o-matic" />', $img_tag);

		$output .= "<a href=\"$image_url\">$img_tag</a>";
	}
	return $output;
}

function tom_create_thumbnail($filename) {
	// Get options.
	$image_path	= get_option('images_path');
	$thumb_path	= $image_path.get_option('thumb_cache_dir');
	$image		= $image_path.'/'.$filename;
	$thumbnail	= $thumb_path.'/'.$filename;
	$thumb_size	= get_option('thumb_size');

	// Check if the source image exists.
	if (file_exists($image)) {
		// Only generate a thumbnail if we don't cache.
		if (get_option('cache_thumbs') == 'false'
			// ... or there isn't already one ...
			|| !file_exists("$thumbnail")
			// ... or there is one, but the original has been modified since generation.
			|| file_exists("$thumbnail") && filemtime($image) > filemtime($thumbnail)) {

			// Get geometry from source image.
			$src_geom = getimagesize($image);

			// Check image type and initialize an image object
			// with the corresponding gd function.
			switch ($src_geom[2]) {
				case 1:
					function_exists('imagecreatefromgif')
						? $image_obj = @imagecreatefromgif($image)
						: print "Your version of gdlib doesn't support GIF images.";
					break;
				case 2:
					(function_exists('imagecreatefromjpeg'))
						? $image_obj = @imagecreatefromjpeg($image)
						: print "Your version of gdlib doesn't support JPEG images.";
					break;
				case 3:
					(function_exists('imagecreatefrompng'))
						? $image_obj = @imagecreatefrompng($image)
						: print "Your version of gdlib doesn't support PNG images.";
					break;
				default:
					print "Unsupported image type.";
					return 0;
			}

			// Keep image proportions.
			$thumb_scale = $src_geom[0] / $thumb_size;
			$dst_geom = array($thumb_size, $src_geom[1] / $thumb_scale);

			// Initialize an image object for the thumbnail.
			$thumb_obj = @imagecreatetruecolor($dst_geom[0], $dst_geom[1]);

			// Preserve alpha transparency in the thumbnail.
			imagealphablending($thumb_obj, false);
			imagesavealpha($thumb_obj, true);

			// Resample the image.
			imagecopyresampled($thumb_obj, $image_obj, 0, 0, 0, 0,
				$dst_geom[0], $dst_geom[1], $src_geom[0], $src_geom[1]);

			// Flush thumbnail to file.
			imagepng($thumb_obj, $thumbnail);

			// Free memory.
			imagedestroy($image_obj);
			imagedestroy($thumb_obj);
			return 1;
		}
		else {
			// We don't cache thumbnails or it didn't need to be (re)created.
			// However, we didn't try, so we didn't fail.
			return 1;
		}
	}
	else {
		// File doesn't exist.
		print "File doesn't exist: $image";
		return 0;
	}
}

// Add a CSS class to use.
add_action('wp_head', 'tom_header');

function tom_header() {
	print '
	<style type="text/css">
		#content img.thumb-o-matic {
			border:		0px;
			clear:		both;
			float:		right;
			margin:		5px 0 5px 15px;
		}
	</style>';
}

?>
