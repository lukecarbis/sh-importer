<?php

/**
 * class sideload and set featured image
**/

/** source gotten from rss importer plugin **/

if( !function_exists( 'download_url' ) ) {
	require_once(ABSPATH .'/wp-admin/includes/file.php'); 
}

if( !function_exists( 'media_handle_sideload' ) ) {
	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
	require_once(ABSPATH . "wp-admin" . '/includes/media.php');
}

if( !class_exists( 'sh_image' ) ) {

	class sh_image {

		function load_img( $item, $post_id ) {

			if( empty( $post_id ) ) {
				return;
			}

			$img_url = '';

			if( $enclosure = $item->get_enclosure() ) {
				$img_url = esc_url( $enclosure->get_link() );
			} else {
				$content = $item->get_content() == '' ? $item->get_description() : $item->get_content();
				// get the first image from content
				preg_match('/<img.+?src="(.+?)"[^}]+>/i', $content, $matches);
				$img_url = ( is_array( $matches ) && !empty( $matches ) ) ? $matches[1] : '';
			}

			if( empty( $img_url ) ) {
				return;
			}

			var_dump($img_url);
			$img_id = $this->sideload( $img_url, $post_id );
			return $img_id;
		}

		function set_img( $item, $post_id ) {

			if( empty( $post_id ) ) {
				return;
			}

			$featured_img_id = $this->load_img( $item, $post_id );
			if( !is_wp_error( $featured_img_id ) ) {
				do_action( "sh_set_featured_thumbnail", $featured_img_id, $post_id );
				$meta_id = set_post_thumbnail( $post_id, $featured_img_id );
			} else {
				var_dump( $featured_img_id );
				$meta_id = 0;
			}

			return $meta_id;

		}

		function sideload( $file, $post_id ) {

			$id = 0;

			if ( !empty( $file ) ) {
				// Set variables for storage, fix file filename for query strings.
				preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
				$file_array = array();
				$file_array['name'] = basename( $file );

				// Download file to temp location.
				$file_array['tmp_name'] = @download_url( $file );

				// If error storing temporarily, return the error.
				if ( is_wp_error( $file_array['tmp_name']) ) {
					return $file_array['tmp_name'];
				}

				// Do the validation and storage stuff.
				$id = media_handle_sideload( $file_array, $post_id, '' );

				// If error storing permanently, unlink.
				if ( is_wp_error( $id ) ) {
					@unlink( $file_array['tmp_name'] );
					return $id;
				}
			}

			return $id;

		}


	}

}