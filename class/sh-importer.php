<?php
if ( ! function_exists( 'wp_create_category' ) ) {
	require_once( ABSPATH . '/wp-admin/includes/taxonomy.php' );
}

if ( ! class_exists( 'sh_importer' ) ) {

	class sh_importer {

		var $opt_id;
		var $opt_val;

		var $messages = '';

		function __construct() {

			add_action( 'admin_menu', array( $this, 'register_sh_importer' ) );
			add_action( 'admin_init', array( $this, 'save_options' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_sh_assets' ) );
			add_action( 'init', array( $this, 'run_importer' ) );
			add_action( 'sh_deactivation_hook', array( $this, 'unregister_sh_importer_options' ) );

		}

		/**
		 * register stylesheet
		**/
		function register_sh_assets( $hook ) {

			global $pagenow;
			if ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && ( $_GET['page'] === 'sh-feed-importer' || $_GET['page'] === 'sh-feed-importer-bulk'  ) ) {

				if ( $_GET['page'] === 'sh-feed-importer-bulk' ) {
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
		 * register sh import navigation
		**/
		function register_sh_importer() {
			$menu_args = array(
								array(
									'page-title' => __( 'Feed importer setting' ),
									'menu-title' => __( 'Feed importer' ),
									'capability' => 'administrator',
									'slug' => 'sh-feed-importer',
									'fallback' => array( $this, 'sh_feed_importer_setting' ),
									'icon' => SH_PLUGIN_DIR . '/assets/img/icon.png',
									'position' => null,
								),
								array(
									'page-title' => __( 'Bulk Importer' ),
									'menu-title' => __( 'Bulk Importer' ),
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
				if ( isset( $menu['type'] ) && $menu['type'] == 'submenu' ) {
					add_submenu_page( $menu['parent'], $menu['page-title'], $menu['menu-title'], $menu['capability'], $menu['slug'], $menu['fallback'] );
				} else {
					add_menu_page( $menu['page-title'], $menu['menu-title'], $menu['capability'], $menu['slug'], $menu['fallback'], $menu['icon'], $menu['position'] );
				}
			}
		}

		/**
		 * register setting
		**/
		function register_sh_importer_options() {

			register_setting( 'shi_options', 'shi_setting' );

		}

		/**
		 * deregister setting
		**/
		function unregister_sh_importer_options() {

			if ( function_exists( 'unregister_setting' ) ) {
				unregister_setting( 'shi_options', 'shi_setting' );
			}

		}

		/**
		 * run importer
		**/
		function run_importer() {

			global $pagenow;

			if ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] === 'sh-feed-importer' ) {
				if ( isset( $_GET['sh-act'] ) && $_GET['sh-act'] === 'run' ) {
					$this->run();
				}
			}

		}

		/**
		 * save options
		**/
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

			for ( $i = 0; $i < count( $data ); $i++ ) {
				$new_value[ $data[ $i ]['name'] ] = wp_kses( $data[ $i ]['value'], array() );
			}

			if ( count( $new_value ) > 0 ) {
				$update = update_option( 'shi_setting', $new_value );
				if ( $update ) {
					echo 'Setting saved !';
				}
			} else {
				printf( 'no data found' );
			}

			die();

		}

		/**
		 * set a message to display
		**/
		private function setMessage( $str = '' ) {
			$this->messages = $str;
		}

		/**
		 * get options
		**/
		function getOptions() {
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
		 * callback for setting page
		**/
		function sh_feed_importer_setting() {

			$options = $this->getOptions();

			?>
			<div class="admin-setting" id="sh-feed-importer">
				<div class="container">
					<?php if ( isset( $this->messages ) ) { ?>
						<p class="alert"><?php echo $this->messages; ?></p>
					<?php } ?>
					<form method="post" id="sh-save">
						<div class="fieldset">
							<input type="hidden" name="sh-data[0][name]" value="feed_status">
							<p>
								<label for="sh-data[0][value]">Activate SH Feed importer ?</label>
								<select name="sh-data[0][value]">
									<option value="true"<?php echo ( $options['feed_status'] == 'true' ? ' selected="selected"' : '' ); ?>>Active</option>
									<option value="false"<?php echo ( $options['feed_status'] == 'false' ? ' selected="selected"' : '' ); ?>>Deactive</option>
								</select>
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[1][name]" value="custom_minutes">
							<p>
								<label for="sh-data[1][value]">Custom Schedule in Minutes</label>
								<input type="text" name="sh-data[1][value]" value="<?php echo $options['custom_minutes']; ?>">
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[2][name]" value="feed_url">
							<p>
								<label for="sh-data[2][value]">Feed URL:</label>
								<input type="text" name="sh-data[2][value]" value="<?php echo $options['feed_url']; ?>">
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[3][name]" value="post_status">
							<p>
								<label for="sh-data[3][value]">Save new imported post as :</label>
								<select name="sh-data[3][value]">
									<option value="publish"<?php echo ( $options['post_status'] == 'publish' ? ' selected="selected"' : '' ); ?>>Published</option>
									<option value="draft"<?php echo ( $options['post_status'] == 'draft' ? ' selected="selected"' : '' ); ?>>Drafted</option>
									<option value="pending"<?php echo ( $options['post_status'] == 'pending' ? ' selected="selected"' : '' ); ?>>Pending</option>
								</select>
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[4][name]" value="img_body_rehost_status">
							<p>
								<label for="sh-data[4][value]">Activate Image rehost on body content:</label>
								<select name="sh-data[4][value]">
									<option value="false"<?php echo ( $options['img_body_rehost_status'] == 'false' ? ' selected="selected"' : '' ); ?>>Deactive</option>
									<option value="true"<?php echo ( $options['img_body_rehost_status'] == 'true' ? ' selected="selected"' : '' ); ?>>Active</option>
								</select>
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[5][name]" value="allowed_categories">
							<p>
								<label for="sh-data[5][value]">Allow categories in this list: ( separate by line )</label>
								<textarea style="height:200px;" name="sh-data[5][value]" class="widefat"><?php echo isset( $options['allowed_categories'] ) ? $options['allowed_categories'] : ''; ?></textarea>
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
			</div><?php

		}

		/**
		 * callback for setting page
		**/
		function sh_feed_importer_bulk() {

			$options = $this->getOptions();

			?>
			<div class="admin-setting" id="sh-feed-importer">
				<div class="container">
					<?php if ( isset( $this->messages ) ) { ?>
						<p class="alert"><?php echo $this->messages; ?></p>
					<?php } ?>
					<form method="post" id="sh-save">
						<div class="fieldset">
							<input type="hidden" name="sh-data[0][name]" value="feed_list_file">
							<p>
								<label for="sh-data[0][value]">Url List ( .txt )</label>
								<input type="text" class="file-upload" name="sh-data[0][value]" value="<?php echo isset( $options['feed_list_file'] ) ? $options['feed_list_file'] : ''; ?>">
							</p>
						</div>
						<div class="fieldset">
							<input type="hidden" name="sh-data[1][name]" value="event_interval">
							<p>
								<label for="sh-data[1][value]">Event time interval ( in hour ):</label>
								<input type="text" name="sh-data[1][value]" value="<?php echo isset( $options['event_interval'] ) ? $options['event_interval'] : ''; ?>">
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
			</div><?php

		}

		/**
		 * check for duplicate post
		**/
		function post_exists( $item ) {

			$post_exists = false;
			$link = $item->get_permalink();

			$options = $this->getOptions();

			$http_indicator = 'http://';
			if ( strpos( $link ,  'https://' ) !== false ) {
				$http_indicator = 'https://';
			}

			$separate_link_str = explode( '/', str_replace( $http_indicator , '',  $link ) );
			if ( isset( $separate_link_str[2] ) ) {
				$link_id = $separate_link_str[2];
			}

			$search_post_args = array(
									'post_type' => 'post',
									'post_status' => 'publish',
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
			} else {
				$post_exists = false;
			}

			wp_reset_postdata();

			return $post_exists;

		}

		/**
		 * post exist by url
		*/
		function post_exists_by_url( $url ) {

			$post_exists = false;
			$link = $url;

			$options = $this->getOptions();

			$http_indicator = 'http://';
			if ( strpos( $link ,  'https://' ) !== false ) {
				$http_indicator = 'https://';
			}

			$separate_link_str = explode( '/', str_replace( $http_indicator , '',  $link ) );
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
			} else {
				$post_exists = false;
			}

			wp_reset_postdata();

			return $post_exists;
		}

		/**
		 * insert posts
		**/
		function insert( $item ) {

			$options = $this->getOptions();

			if ( $this->post_exists( $item ) ) {
				return false;
			} else {

				$post_date = $item->get_date() == '' ? null : array( 'post_date' => get_date_from_gmt( $item->get_date( 'Y-m-d H:i:s' ) ) );

				$post_content = $item->get_content() == '' ? $item->get_description() : $item->get_content();
				$post_status = empty( $options['post_status'] ) ? array( 'post_status' => 'publish' ) : array( 'post_status' => $options['post_status'] );

				$postArr = array(
							'post_title' => wp_kses( $item->get_title(), array() ),
							'post_content' => $post_content,
							'post_excerpt' => wp_kses( $item->get_description(), array() ),
						 );

				if ( ! is_null( $post_date ) && is_array( $post_date ) ) {
					$postArr = array_merge( $postArr, $post_date );
				}

				// last merge
				$postArr = array_merge( $postArr, $post_status );

				$post_id = wp_insert_post( $postArr, true );

				if ( ! is_wp_error( $post_id ) ) {
					$this->adjust_and_insert_post_elements( $item->get_permalink(), $post_id, 'feed' );
				} else {
					return false;
				}
			}

		}

		/**
		 * Bulk insert via URL
		*/
		function run_bulk_importer() {

			$options = $this->getOptions();

			$url_list_file = isset( $options['feed_list_file'] ) ? $options['feed_list_file'] : null;

			if ( is_null( $url_list_file ) ) {
				return;
			}

			$url_list = explode( "\n", file_get_contents( $url_list_file ) );

			if ( ! is_array( $url_list ) ) {
				return;
			}

			$start_index = (int) $options[ 'sh_bulk_count_' . str_replace( '.', '', basename( $url_list_file ) ) ];
			$i = 0;

			foreach ( $url_list as $url ) {
				if ( $start_index == 0 || $i > $start_index  ) {

					/** off the script if time already 05:00 am **/
					if ( date( 'H' ) == 05 ) {
						// save where we go
						$options[ 'sh_bulk_count_' . basename( $url_list_file ) ] = $i;
						update_option( 'shi_setting', $options );
						// clear the event
						wp_clear_scheduled_hook( 'do_bulk_event' );
					}

					$this->insert_by_url( $url );
				}
				$i++;
			}

		}

		function insert_by_url( $url ) {

			if ( $this->post_exists_by_url( $url ) ) {
				$this->sh_log( 'Possibly duplicated url article: ' . $url );
				return false;
			}

			$domel = new DOMDocument();
			$domel->loadHTML( file_get_contents( $url ) );
			$xpath = new DOMXPath( $domel );

			$options = $this->getOptions();
			$post_status = isset( $options['post_status'] ) ? $options['post_status'] : 'publish';
			$post_title = 'untitle - unnamed';
			$post_content = 'no content';

			/**
			 * search for post_Date
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

			$postArr = array(
				'post_title' => $post_title,
				'post_status' => $post_status,
				'post_date' => $post_date,
				'post_content' => $post_content,
			);

			if ( $post_id = wp_insert_post( $postArr, true ) ) {
				$adjust = $this->adjust_and_insert_post_elements( $url, $post_id );
				if ( $adjust === true ) {
					$this->sh_log( $url . ' imported on ' . date( 'Y-m-d h:i:s' ) );
				}
			} else {
				return;
			}
		}

		/**
		 * post adjusting
		**/
		function adjust_and_insert_post_elements( $post_link, $post_id ) {

			if ( empty( $post_id ) ) {
				return;
			}

			$options = $this->getOptions();

			$postArr = array( 'ID' => $post_id );
			/**
			 * insert an identifier to prevent duplicated post
			**/
			$link = $post_link;

			$http_indicator = 'http://';
			if ( strpos( $link ,  'https://' ) !== false ) {
				$http_indicator = 'https://';
			}

			$separate_link_str = explode( '/', str_replace( $http_indicator , '',  $link ) );
			if ( isset( $separate_link_str[2] ) ) {
				$link_id = $separate_link_str[2];
			}

			$update_post_link_id = update_post_meta( $post_id, '_post_import_link_id', $link_id );

			// get html content
			$html_content = file_get_contents( $post_link );
			// set dom element
			$domel = new DomDocument();
			// load the content
			$domel->loadHTML( $html_content );
			// set xpath dom
			$xpath = new DOMXPath( $domel );
			// find the categories
			$parent_categories = $xpath->query( '//li[contains(@class, "primary")]' );
			$children_categories = $xpath->query( '//li[contains(@class,"secondary")]' );

			// initialize parent categories
			$parent_cat_slug = array();
			foreach ( $parent_categories as $p_cat ) {
				$p_cat_href = $p_cat->getElementsByTagName( 'a' );
				foreach ( $p_cat_href as $pch ) {

					/** filter by allowed categories **/
					if ( isset( $options['allowed_categories'] ) ) {
						$allowed_categories = explode( PHP_EOL, $options['allowed_categories'] );

						if ( ! in_array( (String) $pch->nodeValue, $allowed_categories ) ) {
							$this->sh_log( 'Skip : ' . $post_link . ' in category: ' . $pch->nodeValue );
							wp_delete_post( $post_id, true );
							return;
						}
					}

					$parent_cat_slug[] = $pch->nodeValue;
				}
			}

			// initialize children categories
			$children_cat_slug = array();
			foreach ( $children_categories as $c_cat ) {
				$c_cat_href = $c_cat->getElementsByTagName( 'a' );
				foreach ( $c_cat_href as $cch ) {
					if ( strtolower( str_replace( ' ' , '',  $cch->nodeValue ) ) !== 'localsport' ) {
						$children_cat_slug[] = $cch->nodeValue;
					}
				}
			}

			/**
			 * set parent categories
			**/
			$parent_cat_id = array();
			if ( count( $parent_cat_slug ) > 0 ) {
				foreach ( $parent_cat_slug as $pcs ) {
					if ( strtolower( $pcs ) == 'sport' ) {
						$parent_cat = get_cat_ID( 'sports' );
					} elseif ( strripos( $pcs , 'opinion' ) !== false ) {
						$parent_cat = get_cat_ID( 'our take' );
					} else {
						$parent_cat = get_cat_ID( $pcs );
					}

					if ( $parent_cat ) {
						$parent_cat_id[] = (int) $parent_cat;
					} else {
						if ( strtolower( $pcs ) == 'sport' ) {
							$parent_cat_id[] = wp_create_category( 'Sports' );
						} elseif ( strripos( $pcs ,  'opinion' ) ) {
							$parent_cat_id[] = wp_create_category( 'our take' );
						} else {
							$parent_cat_id[] = wp_create_category( $pcs );
						}
					}
				}
			}

			/**
			 * set children categories
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
			 * set author
			**/
			if ( isset( $author ) ) {

				$display_name = $author;
				$user_login = str_replace( ' ', '', $display_name );
				$user_login = strtolower( $user_login );

				$user_login = preg_replace( '/[^A-Za-z0-9\-]/' , '', $user_login );

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

				$postArr['post_author'] = $user_id;

			}

			// include image class
			$thumbnail = new sh_image;

			/** clear social share button **/
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
					$postArr['post_title'] = $htitle->nodeValue;
				}
			}

			$postArr['post_name'] = sanitize_title_with_dashes( $postArr['post_title'], '', 'save' );

			/**
			 * set html content
			**/
			$body = '';
			$body_content = $xpath->query( '//div[contains(@class, "news-article-body")]' );
			foreach ( $body_content as $bc ) {
				/** clear a script and advertisement **/
				$scripts = $bc->getElementsByTagName( 'script' );
				$remove_script = array();

				foreach ( $scripts as $script_item ) {
					$remove_script[] = $script_item;
				}

				foreach ( $remove_script as $script ) {
					if ( strpos( $script->getAttribute( 'src' ) , 'players' ) === false ) {
						$script->parentNode->removeChild( $script );
					}
				}

				// clear a href tags
				$url_to_replace = parse_url( $options['feed_url'] );
				$domain_to_replace = $url_to_replace['host'];

				if ( $domain_to_replace ) {
					$a_tag_html = $bc->getElementsByTagName( 'a' );
					foreach ( $a_tag_html as $a_tag ) {
						if ( strpos( $a_tag->getAttribute( 'href' ) ,  $domain_to_replace ) !== false ) {
							$feed_http_indicator = $url_to_replace['scheme'];
							if ( $a_tag->getAttribute( 'href' ) === $feed_http_indicator . '://' . $domain_to_replace ) {
								$new_href = str_replace( $domain_to_replace , '/', $a_tag->getAttribute( 'href' ) );
							} else {
								$new_href = str_replace( $domain_to_replace , '', $a_tag->getAttribute( 'href' ) );
							}
							if ( strpos( $new_href, $feed_http_indicator ) !== false ) {
								$new_href = str_replace( $feed_http_indicator . '://' , '', $new_href );
							}
							$a_tag->setAttribute( 'href', $new_href );
						}
					}
				}

				/** rehost image if exists **/

				$rehost_img_body_status = isset( $options['img_body_rehost_status'] ) && 'true' === $options['img_body_rehost_status'] ? true : false;

				if ( $rehost_img_body_status ) {
					$images_array = $bc->getElementsByTagName( 'img' );
					foreach ( $images_array as $imgs ) {
						$img_url = '';
						if ( '' !== $imgs->getAttribute( 'data-src' ) ) {
							$img_url = esc_url( $imgs->getAttribute( 'data-src' ) );
						} elseif ( '' !== $imgs->getAttribute( 'src' ) ) {
							$img_url = esc_url( $imgs->getAttribute( 'src' ) );
						}

						// set https indicator if no http request was found
						$img_http_indicator = 'http';

						if ( strpos( $img_url , $img_http_indicator ) === false ) {
							if ( substr( $img_url , 0, 2 ) == '//' ) {
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
									if ( isset( $thumbnail_image[0] ) && $thumbnail_image[0] == wp_get_attachment_url( $img_id ) ) {

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
				$postArr['post_content'] = $body;
			}

			$cat_array = array_merge( $parent_cat_id, $children_cat_id );
			if ( count( $cat_array ) > 0 ) {
				$postArr['post_category'] = array_map( 'intval' , $cat_array );
			}

			$post = wp_update_post( $postArr );

			if ( ! is_wp_error( $post ) ) {
				return true;
			} else {
				return false;
			}

		}

		function run() {

			$options = $this->getOptions();

			if ( $options['feed_status'] === 'true' ) {
				// increase time limit
				set_time_limit( 0 );
				$feed_url = empty( $options['feed_url'] ) ? 'http://www.ulladullatimes.com.au/rss.xml' : esc_url( $options['feed_url'] );

				/** explode commas into array **/
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
			} else {
				return;
			}
		}

		/**
		 * Simple Log
		*/
		function sh_log( $str ) {

			if ( ! is_string( $str ) ) {
				return;
			}

			$_log_file = SH_PLUGIN_DIR . '/log/log.log';

			if ( ! file_exists( dirname( $_log_file ) ) ) {
				mkdir( dirname( $_log_file ), 007, true );
			}

			$open_log = fopen( $_log_file, 'a' );
			$write_log = fwrite( $open_log, $str . "\r\n" );
			fclose( $open_log );
		}

		/**
		 * Download log
		*/
		function download_log() {

			$_log_file = SH_PLUGIN_DIR_URI . '/log/log.log';
			echo $_log_file;

		}

	}

}
