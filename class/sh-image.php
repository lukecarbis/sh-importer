<?php

if ( ! function_exists( 'download_url' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/file.php' );
}

if ( ! function_exists( 'media_handle_sideload' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );
}

if ( class_exists( 'Sh_Image' ) ) {
	return;
}

/**
 * Class Sh_Image
 *
 * Source gotten from rss importer plugin
 */
class Sh_Image {
	/**
	 * @param \SimplePie_Item $item
	 * @param int $post_id
	 *
	 * @return int|void
	 */
	function load_img( $item, $post_id ) {
		if ( empty( $post_id ) ) {
			return null;
		}

		$enclosure = $item->get_enclosure();
		if ( $enclosure ) {
			$img_url = esc_url( $enclosure->get_link() );
		} else {
			$content = $item->get_content() === '' ? $item->get_description() : $item->get_content();
			// Get the first image from content
			preg_match( '/<img.+?src="(.+?)"[^}]+>/i', $content, $matches );
			$img_url = ( is_array( $matches ) && ! empty( $matches ) ) ? $matches[1] : '';
		}

		if ( empty( $img_url ) ) {
			return null;
		}

		$img_id = $this->sideload( $img_url, $post_id );
		return $img_id;
	}

	/**
	 * @param \SimplePie_Item $item
	 * @param int $post_id
	 *
	 * @return int|void
	 */
	function set_img( $item, $post_id ) {
		if ( empty( $post_id ) ) {
			return null;
		}

		$featured_img_id = $this->load_img( $item, $post_id );
		if ( ! is_wp_error( $featured_img_id ) ) {
			do_action( 'sh_set_featured_thumbnail', $featured_img_id, $post_id );
			$meta_id = set_post_thumbnail( $post_id, $featured_img_id );
		} else {
			$meta_id = 0;
		}

		return $meta_id;
	}

	/**
	 * @param string $file
	 * @param int $post_id
	 *
	 * @return int|mixed|object
	 */
	function sideload( $file, $post_id ) {
		$id = 0;

		if ( ! empty( $file ) ) {
			// Set variables for storage, fix file filename for query strings.
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			$file_array = array();
			$file_array['name'] = basename( $file );

			// Download file to temp location.
			$file_array['tmp_name'] = download_url( $file );

			// If error storing temporarily, return the error.
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				return $file_array['tmp_name'];
			}

			// Do the validation and storage stuff.
			$id = media_handle_sideload( $file_array, $post_id, '' );

			// If error storing permanently, unlink.
			if ( is_wp_error( $id ) ) {
				unlink( $file_array['tmp_name'] );
				return $id;
			}
		}

		return $id;
	}
}
