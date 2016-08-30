<?php
/*
 * Plugin Name: News Importer
 * Author: Shelingholmes
 * Author URI: http://gardeningspotlight.com
 * Version: 1.0
 * Description: Fetch and Import an item from rss feed to WordPress post
 * Tag: freelancer, rss to post, automated, scheduled
 */

if ( ! defined( 'ABSPATH' ) ) {
	wp_die( 'You do not have sufficient permission to access to this page' );
}

/**
 * set Sydney timezone
*/
date_default_timezone_set( 'Australia/Sydney' );

/**
 * Import class
 */
include plugin_dir_path( __FILE__ ) . '/class/sh-image.php';
include plugin_dir_path( __FILE__ ) . '/class/sh-importer.php';

/**
 * Define a directory
 */
if ( ! defined( 'SH_PLUGIN_DIR' ) ) {
	define( 'SH_PLUGIN_DIR' ,  plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SH_PLUGIN_DIR_URI' ) ) {
	define( 'SH_PLUGIN_DIR_URI', plugin_dir_url( __FILE__ ) );
}

/**
 * Instantiate options
 */
$sh_importer = new Sh_Importer;
$sh_options = $sh_importer->get_options();

/**
 * Add custom schedule to WordPress cron
 *
 * @param array $schedule
 *
 * @return array
 */
function sh_add_custom_schedule( $schedule ) {
	global $sh_options;

	$minutes = empty( $sh_options['custom_minutes'] ) ? 600 : (float) $sh_options['custom_minutes'] * 60;
	$schedule['sh_custom_schedule'] = array(
		'interval' => $minutes,
		'display' => sprintf( __( 'Every %d minutes', 'sh-importer' ), ceil( $minutes / 60 ) ),
	);
	return $schedule;
}
add_filter( 'cron_schedules', 'sh_add_custom_schedule' );

/**
 * Register cron once plugin activated
 */
register_activation_hook( __FILE__, 'sh_cron_init' );

function sh_cron_init() {
	if ( ! wp_next_scheduled( 'sh_importer_action_on_cron' ) ) {
		wp_schedule_event( time(), 'sh_custom_schedule', 'sh_importer_action_on_cron' );
	}
}

/**
 * Add importer function to cron
 */
function sh_import_post_from_feed() {
	global $sh_importer;
	$sh_importer->run();
}
add_action( 'sh_importer_action_on_cron', 'sh_import_post_from_feed' );

/**
 * Register deactivation hook
 */
register_deactivation_hook( __FILE__, 'sh_deactivation' );

function sh_deactivation() {
	do_action( 'sh_deactivation_hook' );
}

function sh_delete_cron() {
	wp_clear_scheduled_hook( 'sh_importer_action_on_cron' );
}
add_action( 'sh_deactivation_hook', 'sh_delete_cron' );

/**
 * Add ajax function
 */
function sh_run_in_ajax() {
	if ( ! isset( $_POST['shiRunImporter'] ) ) {
		die( esc_html__( 'No nonce field', 'sh-importer' ) );
	}

	if ( ! wp_verify_nonce( $_POST['shiRunImporter'], 'shImporter' ) ) {
		die( esc_html__( 'No valid nonce created', 'sh-importer' ) );
	}

	global $sh_importer;
	$sh_importer->run();
	die( esc_html__( 'Importer Success', 'sh-importer' ) );
}
add_action( 'wp_ajax_run_sh_importer', 'sh_run_in_ajax' );

/**
 * Ajax save setting
 */
function sh_save_setting_in_ajax() {
	global $sh_importer;
	$sh_importer->save_options();
	die();
}
add_action( 'wp_ajax_save_sh_setting', 'sh_save_setting_in_ajax' );

/**
 * Sh add bulk event
 */
function sh_add_bulk_event_ajax() {
	global $sh_importer;

	if ( ! isset( $_POST['shRunBulkImporter'] ) ) {
		die( esc_html__( 'No nonce field', 'sh-importer' ) );
	}

	if ( ! wp_verify_nonce( $_POST['shRunBulkImporter'], 'shBulkEvent' ) ) {
		die( esc_html__( 'No valid nonce created', 'sh-importer' ) );
	}

	$options = $sh_importer->get_options();
	$time_interval = isset( $options['event_interval'] ) ? time() + ( intval( $options['event_interval'] ) ) * 3600 : time() + 300;

	if ( ! wp_next_scheduled( 'do_bulk_event' ) ) {
		wp_schedule_single_event( $time_interval, 'do_bulk_event' );
	} else {
		die( esc_html__( 'Event already exists', 'sh-importer' ) );
	}

	die( esc_html( sprintf( __( 'Event added: begin run in %s', 'sh-importer' ), date( 'Y-m-d h:i:s', $time_interval ) ) ) );
}
add_action( 'wp_ajax_add_bulk_event', 'sh_add_bulk_event_ajax' );

function sh_bulk_importer() {
	global $sh_importer;

	$options = $sh_importer->get_options();

	$file_url = $options['feed_list_file'];

	if ( empty( $file_url ) ) {
		$sh_importer->sh_log( $file_url );
		$sh_importer->sh_log( esc_html__( 'File not found / invalid', 'sh-importer' ) );
		return;
	}

	if ( ! isset( $options[ 'sh_bulk_count_' . str_replace( '.', '', basename( $file_url ) ) ] ) ) {
		$options[ 'sh_bulk_count_' . str_replace( '.', '', basename( $file_url ) ) ] = '0';
		update_option( 'shi_setting', $options );
	}

	$sh_importer->run_bulk_importer();
}
add_action( 'do_bulk_event', 'sh_bulk_importer' );

function sh_download_log_ajax() {
	global $sh_importer;

	if ( ! isset( $_POST['shiDownloadLog'] ) ) {
		die( esc_html__( 'No nonce field', 'sh-importer' ) );
	}

	if ( ! wp_verify_nonce( $_POST['shiDownloadLog'], 'shiLog' ) ) {
		die( esc_html__( 'No valid nonce created', 'sh-importer' ) );
	}

	$message = $sh_importer->download_log();

	die( esc_html( $message ) );
}
add_action( 'wp_ajax_download_log', 'sh_download_log_ajax' );
