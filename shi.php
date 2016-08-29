<?php

/*
Plugin Name: News importer
Author: Shelingholmes
Author URI: http://gardeningspotlight.com
Version: 1.0
Description: Fetch and Import an item from rss feed to wordpress post
Tag: freelancer, rss to post, automated, scheduled
*/ 

if( !defined( 'ABSPATH' ) ) {
	wp_die( "You do not have sufficient permission to access to this page" );
}

/**
 * set Sydney timezone
*/
date_default_timezone_set( "Australia/Sydney" );

/**
 * import class
**/

include plugin_dir_path( __FILE__ ) . '/class/sh-image.php';
include plugin_dir_path( __FILE__ ) . '/class/sh-importer.php';

/**
 * define a directory
**/
if( !defined( 'SH_PLUGIN_DIR' ) ) {
	define( 'SH_PLUGIN_DIR' ,  plugin_dir_path( __FILE__ ) );
}

if( !defined( 'SH_PLUGIN_DIR_URI' ) ) {
	define( 'SH_PLUGIN_DIR_URI', plugin_dir_url( __FILE__ ) );
}

/**
 * instantiate options
**/
$sh_importer = new sh_importer;
$sh_options = $sh_importer->getOptions();

/**
 * let's add custom schedule to wordpress cron
**/
function sh_add_custom_schedule( $schedule ) {

	global $sh_options;

	$minutes = empty( $sh_options['custom_minutes'] ) ? 600 : (float) $sh_options['custom_minutes'] * 60;
	$schedule['sh_custom_schedule'] = array(
										'interval' => $minutes,
										'display' => __( 'Every ' . (string) ceil( $minutes/60 ) . ' minutes' )
									);
	return $schedule;

}

add_filter( 'cron_schedules', 'sh_add_custom_schedule' );

/**
 * register cron once plugin activated
**/
register_activation_hook( __FILE__, 'sh_cron_init' );

function sh_cron_init() {

	if( !wp_next_scheduled( 'sh_importer_action_on_cron' ) ) {
		wp_schedule_event( time(), 'sh_custom_schedule', 'sh_importer_action_on_cron' );
	}

}

/**
 * add importer functio to cron
**/

function sh_import_post_from_feed() {

	global $sh_importer;
	$sh_importer->run();

}

add_action( "sh_importer_action_on_cron", "sh_import_post_from_feed" );

/**
 * register deactivation hook
**/
register_deactivation_hook( __FILE__, 'sh_deactivation' );

function sh_deactivation() {

	do_action( "sh_deactivation_hook" );

}

function sh_delete_cron() {
	wp_clear_scheduled_hook( 'sh_importer_action_on_cron' );
}

add_action( "sh_deactivation_hook", "sh_delete_cron" );

/**
 * add ajax function
**/
function sh_run_in_ajax() {

	if ( ! isset( $_POST['shiRunImporter'] ) ) {
		die( "No nonce field" );
	}

	if ( ! wp_verify_nonce( $_POST['shiRunImporter'], 'shImporter' ) ) {
		die( "no nonce created" );
	}

	global $sh_importer;
	$sh_importer->run();
	die("Importer Success");

}

add_action( "wp_ajax_run_sh_importer", "sh_run_in_ajax" );

/**
 * ajax save setting
**/
function sh_save_setting_in_ajax() {

	global $sh_importer;
	$sh_importer->save_options();
	die();
}

add_action( "wp_ajax_save_sh_setting", "sh_save_setting_in_ajax" );

/**
 * Sh add bulk event
*/

function sh_add_bulk_event_ajax() {

	global $sh_importer;

	if ( ! isset( $_POST['shRunBulkImporter'] ) ) {
		die( "No nonce field" );
	}

	if ( ! wp_verify_nonce( $_POST['shRunBulkImporter'], 'shBulkEvent' ) ) {
		die( "No valid nonce created" );
	}

	$options = $sh_importer->getOptions();
	$time_interval = isset( $options['event_interval'] ) ? time() + ( intval( $options['event_interval'] ) ) * 3600 : time() + 300;

	if ( ! wp_next_scheduled( 'do_bulk_event' ) ) {
		wp_schedule_single_event( $time_interval, 'do_bulk_event' );
	} else {
		die("event already exists" );
	}

	die("event added : begin run in " . date( 'Y-m-d h:i:s', $time_interval ) );

}

add_action( "wp_ajax_add_bulk_event", "sh_add_bulk_event_ajax" );

function sh_bulk_importer() {

	global $sh_importer;

	$options = $sh_importer->getOptions();

	$file_url = $options['feed_list_file'];

	if ( empty( $file_url ) ) {
		$sh_importer->sh_log( $file_url );
		$sh_importer->sh_log( "File not found / invalid" );
		return;
	}

	if ( ! isset( $options['sh_bulk_count_' . str_replace( '.', '', basename( $file_url ) )] ) ) {
		$options['sh_bulk_count_' . str_replace( '.', '', basename( $file_url ) )] = '0';
		update_option( "shi_setting", $options );
	}

	$sh_importer->run_bulk_importer();
}

add_action( "do_bulk_event", "sh_bulk_importer" );

function sh_download_log_ajax() {

	global $sh_importer;

	if ( ! isset( $_POST['shiDownloadLog'] ) ) {
		die( "no nonce field" );
	}

	if ( ! wp_verify_nonce( $_POST['shiDownloadLog'], 'shiLog' ) ) {
		die( "no valid nonce" );
	}

	$message = $sh_importer->download_log();

	die( $message );

}

add_action( "wp_ajax_download_log", "sh_download_log_ajax" );