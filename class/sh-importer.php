<?php
if ( ! function_exists( 'wp_create_category' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/taxonomy.php' );
}

if ( class_exists( 'sh_importer' ) ) {
	return;
}

/**
 * Class Sh_Importer
 */
class Sh_Importer {
	var $opt_id;
	var $opt_val;
	var $messages = '';

	/**
	 * Sh_Importer constructor.
	 */
	function __construct() {
		add_action( 'admin_menu', array( $this, 'register_sh_importer' ) );
		add_action( 'admin_init', array( $this, 'save_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_sh_assets' ) );
		add_action( 'init', array( $this, 'run_importer' ) );
		add_action( 'sh_deactivation_hook', array( $this, 'unregister_sh_importer_options' ) );
	}

	/**
	 * Register stylesheets
	 */
	function register_sh_assets() {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && ( 'sh-feed-importer' === $_GET['page'] || 'sh-feed-importer-bulk' === $_GET['page'] ) ) {

			if ( 'sh-feed-importer-bulk' === $_GET['page'] ) {
				wp_enqueue_style( array( 'thickbox', 'media-upload' ) );
				wp_enqueue_script( array( 'thickbox', 'media-upload' ) );
			}

			wp_register_script( 'sh-custom', SH_PLUGIN_DIR_URI . '/js/sh-custom.js', array( 'jquery' ), '1.0', false );
			wp_register_style( 'sh-css', SH_PLUGIN_DIR_URI . '/css/sh-css.css', false );

			wp_enqueue_style( 'sh-css' );
			wp_enqueue_script( 'sh-custom' );
		}
	}

	/**
	 * Register sh import menu items
	 */
	function register_sh_importer() {
		$menu_args = array(
			array(
				'page-title' => __( 'Feed importer setting', 'sh-importer' ),
				'menu-title' => __( 'Feed importer', 'sh-importer' ),
				'capability' => 'administrator',
				'slug' => 'sh-feed-importer',
				'fallback' => array( $this, 'sh_feed_importer_setting' ),
				'icon' => SH_PLUGIN_DIR . '/assets/img/icon.png',
				'position' => null,
			),
			array(
				'page-title' => __( 'Bulk Importer', 'sh-importer' ),
				'menu-title' => __( 'Bulk Importer', 'sh-importer' ),
				'type'		 => 'submenu',
				'parent'	 => 'sh-feed-importer',
				'capability' => 'administrator',
				'slug'		 => 'sh-feed-importer-bulk',
				'fallback'	 => array( $this, 'sh_feed_importer_bulk' ),
				'icon'		 => '',
				'position'	 => null,
			),
		);

		foreach ( $menu_args as $menu ) {
			if ( isset( $menu['type'] ) && 'submenu' === $menu['type'] ) {
				add_submenu_page( $menu['parent'], $menu['page-title'], $menu['menu-title'], $menu['capability'], $menu['slug'], $menu['fallback'] );
			} else {
				add_menu_page( $menu['page-title'], $menu['menu-title'], $menu['capability'], $menu['slug'], $menu['fallback'], $menu['icon'], $menu['position'] );
			}
		}
	}

	/**
	 * Register setting
	 */
	function register_sh_importer_options() {
		register_setting( 'shi_options', 'shi_setting' );
	}

	/**
	 * Deregister setting
	 */
	function unregister_sh_importer_options() {
		if ( function_exists( 'unregister_setting' ) ) {
			unregister_setting( 'shi_options', 'shi_setting' );
		}
	}

	/**
	 * Run importer
	 */
	function run_importer() {
		global $pagenow;

		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'sh-feed-importer' === $_GET['page'] ) {
			if ( isset( $_GET['sh-act'] ) && 'run' === $_GET['sh-act'] ) {
				$this->run();
			}
		}
	}

	/**
	 * Save options
	 */
	function save_options() {
		if ( ! isset( $_POST['shiSetting'] ) ) {
			return;
		}

		if ( isset( $_POST['shiSetting'] ) && ! wp_verify_nonce( $_POST['shiSetting'], 'shiSaveSetting' ) ) {
			return;
		}

		$data = empty( $_POST['sh-data'] ) ? array() : $_POST['sh-data'];
		$new_value = array();

		if ( count( $data ) <= 0 ) {
			return;
		}

		$old_value = get_option( 'shi_setting' );

		if ( is_array( $old_value ) ) {
			$new_value = $old_value;
		}

		$data_count = count( $data );
		for ( $i = 0; $i < $data_count; $i++ ) {
			$new_value[ $data[ $i ]['name'] ] = wp_kses( $data[ $i ]['value'], array() );
		}

		if ( count( $new_value ) > 0 ) {
			$update = update_option( 'shi_setting', $new_value );
			if ( $update ) {
				esc_html_e( 'Setting saved !', 'sh-importer' );
			}
		} else {
			esc_html_e( 'No data found', 'sh-importer' );
		}

		die();
	}

	/**
	 * Get options
	 *
	 * @return array
	 */
	function get_options() {
		$options = get_option( 'shi_setting' );
		if ( ! is_array( $options ) || ( is_array( $options ) && count( $options ) < 0 ) ) {
			$default_options = array(
								'feed_status' => 'true',
								'custom_minutes' => 10,
								'post_status' => 'publish',
								'feed_url' => 'http://www.ulladullatimes.com.au/rss.xml',
								'img_body_rehost_status' => 'true',
							);
			return $default_options;
		} else {
			return $options;
		}
	}

	/**
	 * Callback for setting page
	 */
	function sh_feed_importer_setting() {
		$options = $this->get_options();
		?>
		<div class="admin-setting" id="sh-feed-importer">
			<div class="container">
				<?php if ( isset( $this->messages ) ) { ?>
					<p class="alert"><?php echo wp_kses_post( $this->messages ); ?></p>
				<?php } ?>
				<form method="post" id="sh-save">
					<div class="fieldset">
						<input type="hidden" name="sh-data[0][name]" value="feed_status">
						<p>
							<label for="sh-data[0][value]">Activate SH Feed importer ?</label>
							<select name="sh-data[0][value]" id="sh-data[0][value]">
								<option value="true" <?php selected( $options['feed_status'], 'true' ); ?>>Active</option>
								<option value="false" <?php selected( $options['feed_status'], 'false' ); ?>>Inactive</option>
							</select>
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[1][name]" value="custom_minutes">
						<p>
							<label for="sh-data[1][value]">Custom Schedule in Minutes</label>
							<input type="text" name="sh-data[1][value]" id="sh-data[1][value]" value="<?php echo esc_attr( $options['custom_minutes'] ); ?>">
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[2][name]" value="feed_url">
						<p>
							<label for="sh-data[2][value]">Feed URL:</label>
							<input type="text" name="sh-data[2][value]" id="sh-data[2][value]" value="<?php echo esc_attr( $options['feed_url'] ); ?>">
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[3][name]" value="post_status">
						<p>
							<label for="sh-data[3][value]">Save new imported post as :</label>
							<select name="sh-data[3][value]" id="sh-data[3][value]">
								<option value="publish" <?php selected( $options['post_status'], 'publish' ); ?>>Published</option>
								<option value="draft" <?php selected( $options['post_status'], 'draft' ); ?>>Drafted</option>
								<option value="pending" <?php selected( $options['post_status'], 'pending' ); ?>>Pending</option>
							</select>
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[4][name]" value="img_body_rehost_status">
						<p>
							<label for="sh-data[4][value]">Activate Image rehost on body content:</label>
							<select name="sh-data[4][value]" id="sh-data[4][value]">
								<option value="false" <?php selected( $options['img_body_rehost_status'], 'false' ); ?>>Inactive</option>
								<option value="true" <?php selected( $options['img_body_rehost_status'], 'true' ); ?>>Active</option>
							</select>
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[5][name]" value="allowed_categories">
						<p>
							<label for="sh-data[5][value]">Allow categories in this list: ( separate by line )</label>
							<textarea style="height:200px;" name="sh-data[5][value]" id="sh-data[5][value]" class="widefat">
								<?php echo esc_html( isset( $options['allowed_categories'] ) ? $options['allowed_categories'] : '' ); ?>
							</textarea>
						</p>
					</div>
					<div class="fieldset">
						<?php wp_nonce_field( 'shiSaveSetting', 'shiSetting' ); ?>
						<input type="hidden" name="action" value="save_sh_setting">
						<input type="submit" value="Saves">
					</div>
				</form>
			</div>
			<div class="run-importer">
				<form method="post" id="run">
					<?php wp_nonce_field( 'shImporter', 'shiRunImporter' ); ?>
					<input type="hidden" name="action" value="run_sh_importer">
					<input type="submit" value="Run importer now">
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Callback for bulk importer page
	 */
	function sh_feed_importer_bulk() {
		$options = $this->get_options();
		?>
		<div class="admin-setting" id="sh-feed-importer">
			<div class="container">
				<?php if ( isset( $this->messages ) ) { ?>
					<p class="alert"><?php echo wp_kses_post( $this->messages ); ?></p>
				<?php } ?>
				<form method="post" id="sh-save">
					<div class="fieldset">
						<input type="hidden" name="sh-data[0][name]" value="feed_list_file">
						<p>
							<label for="sh-data[0][value]">Url List ( .txt )</label>
							<input type="text" class="file-upload" name="sh-data[0][value]" id="sh-data[0][value]" value="<?php echo esc_attr( isset( $options['feed_list_file'] ) ? $options['feed_list_file'] : '' ); ?>">
						</p>
					</div>
					<div class="fieldset">
						<input type="hidden" name="sh-data[1][name]" value="event_interval">
						<p>
							<label for="sh-data[1][value]">Event time interval ( in hour ):</label>
							<input type="text" name="sh-data[1][value]" id="sh-data[1][value]" value="<?php echo esc_attr( isset( $options['event_interval'] ) ? $options['event_interval'] : '' ); ?>">
						</p>
					</div>
					<div class="fieldset">
						<?php wp_nonce_field( 'shiSaveSetting', 'shiSetting' ); ?>
						<input type="hidden" name="action" value="save_sh_setting">
						<input type="submit" value="Save">
					</div>
				</form>
				<form method="post" id="sh-add-task">
					<div class="fieldset">
						<?php wp_nonce_field( 'shBulkEvent', 'shRunBulkImporter' ); ?>
						<input type="hidden" name="action" value="add_bulk_event">
						<input type="submit" class="button button-submit" value="Add Task to Background">
					</div>
				</form>
				<form method="post" id="sh-download-log">
					<div class="fieldset">
						<?php wp_nonce_field( 'shiLog', 'shiDownloadLog' ); ?>
						<input type="hidden" name="action" value="download_log">
						<input type="submit" class="button button-submit" value="Download Log">
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Check for duplicate post
	 *
	 * @param \SimplePie_Item $item
	 *
	 * @return bool
	 */
	function post_exists( $item ) {
		return $this->post_exists_by_url( $item->get_permalink() );
	}

	/**
	 * Post exist by url
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	function post_exists_by_url( $url ) {
		$post_exists = false;

		$http_indicator = 'http://';
		if ( strpos( $url, 'https://' ) !== false ) {
			$http_indicator = 'https://';
		}

		$link_id = '';
		$separate_link_str = explode( '/', str_replace( $http_indicator, '',  $url ) );
		if ( isset( $separate_link_str[2] ) ) {
			$link_id = $separate_link_str[2];
		}

		$search_post_args = array(
			'post_type' => 'post',
			'post_status' => array( 'publish', 'future' ),
			'meta_query' => array(
				array(
					'key' => '_post_import_link_id',
					'value' => (string) $link_id,
					'compare' => '=',
				),
			),
		);

		$find_post = new WP_Query( $search_post_args );

		if ( $find_post->have_posts() ) {
			$post_exists = true;
		}

		wp_reset_postdata();

		return $post_exists;
	}

	/**
	 * Insert posts
	 *
	 * @param \SimplePie_Item $item
	 *
	 * @return bool
	 */
	function insert( $item ) {
		$options = $this->get_options();

		if ( ! $this->post_exists( $item ) ) {
			return false;
		}

		$post_date = null;
		if ( '' !== $item->get_date() ) {
			$post_date = array( 'post_date' => get_date_from_gmt( $item->get_date( 'Y-m-d H:i:s' ) ) );
		}

		$post_content = $item->get_description();
		if ( '' !== $item->get_content() ) {
			$post_content = $item->get_content();
		}

		$post_status = array( 'post_status' => 'publish' );
		if ( ! empty( $options['post_status'] ) ) {
			$post_status = array( 'post_status' => $options['post_status'] );
		}

		$post_array = array(
			'post_title'   => wp_kses_post( $item->get_title() ),
			'post_content' => $post_content,
			'post_excerpt' => wp_kses_post( $item->get_description() ),
		 );

		if ( ! is_null( $post_date ) && is_array( $post_date ) ) {
			$post_array = array_merge( $post_array, $post_date );
		}

		$post_array = array_merge( $post_array, $post_status );

		$post_id = wp_insert_post( $post_array, true );

		if ( ! is_wp_error( $post_id ) ) {
			return $this->adjust_and_insert_post_elements( $item->get_permalink(), $post_id );
		} else {
			return false;
		}
	}

	/**
	 * Bulk insert via URL
	*/
	function run_bulk_importer() {
		$options = $this->get_options();

		if ( ! isset( $options['feed_list_file'] ) ) {
			return null;
		}

		$url_list_file = $options['feed_list_file'];
		$url_list      = explode( "\n", file_get_contents( $url_list_file ) );

		if ( ! is_array( $url_list ) ) {
			return;
		}

		$start_index = (int) $options[ 'sh_bulk_count_' . str_replace( '.', '', basename( $url_list_file ) ) ];

		foreach ( $url_list as $key => $url ) {
			if ( 0 === $start_index || $key > $start_index  ) {

				// Off the script if time already 05:00 am
				if ( '05' === date( 'H' ) ) {
					// Save where we go
					$options[ 'sh_bulk_count_' . basename( $url_list_file ) ] = $key;
					update_option( 'shi_setting', $options );
					// Clear the event
					wp_clear_scheduled_hook( 'do_bulk_event' );
				}
				$this->insert_by_url( $url );
			}
		}
	}

	/**
	 * @param string $url
	 *
	 * @return bool|int|\WP_Error
	 */
	function insert_by_url( $url ) {
		if ( $this->post_exists_by_url( $url ) ) {
			$this->sh_log( esc_html( sprintf( __( 'Possibly duplicated url article: %s', 'sh-importer' ), $url ) ) );
			return false;
		}

		$domel = new DOMDocument();
		$domel->loadHTML( file_get_contents( $url ) );
		$xpath = new DOMXPath( $domel );

		$options = $this->get_options();
		$post_status = isset( $options['post_status'] ) ? $options['post_status'] : 'publish';
		$post_title = esc_html__( 'Untitled', 'sh-importer' );
		$post_content = esc_html__( 'No content', 'sh-importer' );

		/**
		 * Search for post_date
		 */
		$news_byline = $xpath->query( '//div[contains(@class, "news-article-byline")]' );
		foreach ( $news_byline as $nbl ) {
			$time_el = $nbl->getElementsByTagName( 'time' );
			foreach ( $time_el as $time ) {
				$date_time = strtotime( $time->getAttribute( 'datetime' ) );
			}
		}

		if ( isset( $date_time ) ) {
			$post_date = date( 'Y-m-d h:i:s', $date_time );
		} else {
			$post_date = date( 'Y-m-d h:i:s' );
		}

		$post_array = array(
			'post_title' => $post_title,
			'post_status' => $post_status,
			'post_date' => $post_date,
			'post_content' => $post_content,
		);

		$post_id = wp_insert_post( $post_array, true );
		if ( ! is_wp_error( $post_id ) ) {
			$adjust = $this->adjust_and_insert_post_elements( $url, $post_id );
			if ( true === $adjust ) {
				$this->sh_log( $url . ' imported on ' . date( 'Y-m-d h:i:s' ) );
			}
		}

		return $post_id;
	}

	/**
	 * Post adjusting
	 *
	 * @param string $post_link
	 * @param int $post_id
	 *
	 * @return bool
	 */
	function adjust_and_insert_post_elements( $post_link, $post_id ) {
		if ( empty( $post_id ) ) {
			return false;
		}

		$options = $this->get_options();

		$post_array = array( 'ID' => $post_id );

		/**
		 * Insert an identifier to prevent duplicated post
		 */
		$link = $post_link;

		$http_indicator = 'http://';
		if ( strpos( $link, 'https://' ) !== false ) {
			$http_indicator = 'https://';
		}

		$link_id = null;
		$separate_link_str = explode( '/', str_replace( $http_indicator, '',  $link ) );
		if ( isset( $separate_link_str[2] ) ) {
			$link_id = $separate_link_str[2];
		}

		update_post_meta( $post_id, '_post_import_link_id', $link_id );

		// Get html content
		$html_content = file_get_contents( $post_link );
		// Set dom element
		$domel = new DomDocument();
		// Load the content
		$domel->loadHTML( $html_content );
		// Set xpath dom
		$xpath = new DOMXPath( $domel );
		// Find the categories
		$parent_categories = $xpath->query( '//li[contains(@class, "primary")]' );
		$children_categories = $xpath->query( '//li[contains(@class,"secondary")]' );

		// Initialize parent categories
		$parent_cat_slug = array();
		foreach ( $parent_categories as $p_cat ) {
			$p_cat_href = $p_cat->getElementsByTagName( 'a' );
			foreach ( $p_cat_href as $pch ) {
				// Filter by allowed categories
				if ( isset( $options['allowed_categories'] ) ) {
					$allowed_categories = explode( PHP_EOL, $options['allowed_categories'] );

					if ( ! in_array( (String) $pch->nodeValue, $allowed_categories, true ) ) {
						$this->sh_log( esc_html( sprintf( 'Skip: %s in category: %s', $post_link, $pch->nodeValue ) ) );
						wp_delete_post( $post_id, true );
						return;
					}
				}

				$parent_cat_slug[] = $pch->nodeValue;
			}
		}

		// Initialize children categories
		$children_cat_slug = array();
		foreach ( $children_categories as $c_cat ) {
			$c_cat_href = $c_cat->getElementsByTagName( 'a' );
			foreach ( $c_cat_href as $cch ) {
				if ( 'localsport' !== strtolower( str_replace( ' ', '',  $cch->nodeValue ) ) ) {
					$children_cat_slug[] = $cch->nodeValue;
				}
			}
		}

		/**
		 * Set parent categories
		**/
		$parent_cat_id = array();
		if ( count( $parent_cat_slug ) > 0 ) {
			foreach ( $parent_cat_slug as $pcs ) {
				if ( 'sport' === strtolower( $pcs ) ) {
					$parent_cat = get_cat_ID( 'sports' );
				} elseif ( strripos( $pcs, 'opinion' ) !== false ) {
					$parent_cat = get_cat_ID( 'our take' );
				} else {
					$parent_cat = get_cat_ID( $pcs );
				}

				if ( $parent_cat ) {
					$parent_cat_id[] = (int) $parent_cat;
				} else {
					if ( 'sport' === strtolower( $pcs ) ) {
						$parent_cat_id[] = wp_create_category( 'Sports' );
					} elseif ( strripos( $pcs, 'opinion' ) ) {
						$parent_cat_id[] = wp_create_category( 'our take' );
					} else {
						$parent_cat_id[] = wp_create_category( $pcs );
					}
				}
			}
		}

		/**
		 * Set child categories
		**/
		$children_cat_id = array();
		if ( count( $children_cat_slug ) > 0 ) {
			$i = 0;
			foreach ( $children_cat_slug as $cts ) {
				$i++;
				$children_cat = get_cat_ID( $cts );
				if ( $children_cat ) {
					$children_cat_id[] = (int) $children_cat;
				} else {
					if ( count( $parent_cat_id ) > 0 && isset( $parent_cat_id[0] ) ) {
						$children_cat_id[] = wp_create_category( $cts, $parent_cat_id[0] );
					} else {
						$children_cat_id[] = wp_create_category( $cts );
					}
				}
			}
		}

		$author_el = $xpath->query( '//span[contains(@class, "story-header__author-byline")]' );

		foreach ( $author_el as $authors ) {
			$author = $authors->nodeValue;
		}

		/**
		 * Set author
		**/
		if ( isset( $author ) ) {
			$display_name = $author;
			$user_login   = str_replace( ' ', '', $display_name );
			$user_login   = strtolower( $user_login );
			$user_login   = preg_replace( '/[^A-Za-z0-9\-]/', '', $user_login );

			$user_id = username_exists( $user_login );

			if ( ! $user_id ) {
				$random_password = wp_generate_password( 12, false );
				$user_id = wp_insert_user( array(
						'user_login'	=> $user_login,
						'user_pass'		=> $random_password,
						'display_name'	=> $display_name,
						'first_name'	=> $display_name,
					)
				);
			}

			$post_array['post_author'] = $user_id;
		}

		// Include image class
		$thumbnail = new Sh_Image;

		// Clear social share button
		$social_share = $xpath->query( '//div[contains(@class, "social-sharing")]' );
		$ss_array = array();

		foreach ( $social_share as $soshare ) {
			$ss_array[] = $soshare;
		}

		foreach ( $ss_array as $remove_share ) {
			$remove_share->parentNode->removeChild( $remove_share );
		}

		/**
		 * Header
		*/
		$header_content = $xpath->query( '//header[contains(@class, "news-article-title clear")]' );
		foreach ( $header_content as $hc ) {
			$header_title = $hc->getElementsByTagName( 'h1' );
			foreach ( $header_title as $htitle ) {
				$post_array['post_title'] = $htitle->nodeValue;
			}
		}

		$post_array['post_name'] = sanitize_title_with_dashes( $post_array['post_title'], '', 'save' );

		/**
		 * Set html content
		**/
		$body = '';
		$body_content = $xpath->query( '//div[contains(@class, "news-article-body")]' );
		foreach ( $body_content as $bc ) {
			// Clear a script and advertisement
			$scripts = $bc->getElementsByTagName( 'script' );
			$remove_script = array();

			foreach ( $scripts as $script_item ) {
				$remove_script[] = $script_item;
			}

			foreach ( $remove_script as $script ) {
				if ( strpos( $script->getAttribute( 'src' ), 'players' ) === false ) {
					$script->parentNode->removeChild( $script );
				}
			}

			// Clear a href tags
			$url_to_replace = parse_url( $options['feed_url'] );
			$domain_to_replace = $url_to_replace['host'];

			if ( $domain_to_replace ) {
				$a_tag_html = $bc->getElementsByTagName( 'a' );
				foreach ( $a_tag_html as $a_tag ) {
					if ( strpos( $a_tag->getAttribute( 'href' ),  $domain_to_replace ) !== false ) {
						$feed_http_indicator = $url_to_replace['scheme'];
						if ( $a_tag->getAttribute( 'href' ) === $feed_http_indicator . '://' . $domain_to_replace ) {
							$new_href = str_replace( $domain_to_replace, '/', $a_tag->getAttribute( 'href' ) );
						} else {
							$new_href = str_replace( $domain_to_replace, '', $a_tag->getAttribute( 'href' ) );
						}
						if ( strpos( $new_href, $feed_http_indicator ) !== false ) {
							$new_href = str_replace( $feed_http_indicator . '://', '', $new_href );
						}
						$a_tag->setAttribute( 'href', $new_href );
					}
				}
			}

			// Rehost image if exists
			$rehost_img_body_status = false;
			if ( isset( $options['img_body_rehost_status'] ) && 'true' === $options['img_body_rehost_status'] ) {
				$rehost_img_body_status = true;
			}

			if ( $rehost_img_body_status ) {
				$images_array = $bc->getElementsByTagName( 'img' );
				foreach ( $images_array as $imgs ) {
					$img_url = '';
					if ( '' !== $imgs->getAttribute( 'data-src' ) ) {
						$img_url = esc_url( $imgs->getAttribute( 'data-src' ) );
					} elseif ( '' !== $imgs->getAttribute( 'src' ) ) {
						$img_url = esc_url( $imgs->getAttribute( 'src' ) );
					}

					// Set https indicator if no http request was found
					$img_http_indicator = 'http';

					if ( false === strpos( $img_url, $img_http_indicator ) ) {
						if ( '//' === substr( $img_url, 0, 2 ) ) {
							$img_url = esc_url( $img_http_indicator . ':' . $img_url );
						} else {
							$img_url = esc_url( $img_http_indicator . '://' . $img_url );
						}
					}

					if ( ! empty( $img_url ) ) {
						$img_id = $thumbnail->sideload( $img_url, $post_id );
						if ( ! is_wp_error( $img_id ) ) {
							if ( ! has_post_thumbnail( $post_id ) ) {
								set_post_thumbnail( $post_id, $img_id );
							}

							$thumbnail_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );

							$imgs->setAttribute( 'src', wp_get_attachment_url( $img_id ) );
							$imgs->setAttribute( 'data-src', wp_get_attachment_url( $img_id ) );

							/**
							 * Gallery special case
							*/
							$gallery_photo = $xpath->evaluate( 'boolean(//div[contains(@class,"photogallery-carousel")])' );
							if ( ! $gallery_photo ) {
								if ( isset( $thumbnail_image[0] ) && wp_get_attachment_url( $img_id ) === $thumbnail_image[0] ) {
									$imgs->setAttribute( 'style', 'display:none' );
									$imgs->setAttribute( 'src', '' );
									$imgs->setAttribute( 'data-src', '' );
								}
							}
						}
					}
				}
			}

			$newDoc = new DOMDocument();
			$cloned = $bc->cloneNode( true );
			$newDoc->appendChild( $newDoc->importNode( $cloned, true ) );
			$body = $newDoc->saveHTML();
		}

		if ( ! empty( $body ) ) {
			$post_array['post_content'] = $body;
		}

		$cat_array = array_merge( $parent_cat_id, $children_cat_id );
		if ( count( $cat_array ) > 0 ) {
			$post_array['post_category'] = array_map( 'intval', $cat_array );
		}

		$post = wp_update_post( $post_array );

		if ( is_wp_error( $post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Run
	 */
	function run() {
		$options = $this->get_options();

		if ( 'true' !== $options['feed_status'] ) {
			return;
		}

		// Increase time limit
		set_time_limit( 0 );
		$feed_url = empty( $options['feed_url'] ) ? 'http://www.ulladullatimes.com.au/rss.xml' : esc_url( $options['feed_url'] );

		// Explode commas into array
		if ( strripos( $feed_url,  ',' ) ) {
			$feed_url = str_replace( ' ', '', $feed_url );
			$feed_url = explode( ',', $feed_url );
			$feed_url = array_filter( $feed_url );
		}

		if ( is_array( $feed_url ) ) {
			foreach ( $feed_url as $url ) {
				$feed = fetch_feed( $url );
				foreach ( $feed as $item ) {
					$this->insert( $item );
				}
			}
		} else {
			$feed = fetch_feed( $feed_url );
			foreach ( $feed->get_items() as $item ) {
				$this->insert( $item );
			}
		}
	}

	/**
	 * Simple Log
	 *
	 * @param string $str
	 */
	function sh_log( $str ) {
		if ( ! is_string( $str ) ) {
			return;
		}

		$_log_file = SH_PLUGIN_DIR . '/log/log.log';

		if ( ! file_exists( dirname( $_log_file ) ) ) {
			mkdir( dirname( $_log_file ), 007, true );
		}

		$open_log  = fopen( $_log_file, 'a' );
		$write_log = fwrite( $open_log, $str . "\r\n" );
		fclose( $open_log );
	}

	/**
	 * Download log
	 */
	function download_log() {
		$_log_file = SH_PLUGIN_DIR_URI . '/log/log.log';
		return $_log_file;
	}
}
