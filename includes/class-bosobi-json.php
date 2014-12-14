<?php

/**
 * This file handles the configuration of the JSON API plugin for our usage.
 */
class BOSOBI_JSON {

	public static function init() {
		add_filter( 'json_api_controllers', array( __CLASS__, 'add_badge_controller' ) );
		add_filter( 'json_api_badge_controller_path', array( __CLASS__, 'get_badge_controller_path' ) );
	}

	/**
	 * Register controllers for custom JSON_API end points.
	 * @filter json_api_controllers
	 */
	public static function add_badge_controller( $controllers ) {
		$controllers[] = 'badge';
		return $controllers;
	}

	/**
	 * Register controllers define path custom JSON_API end points.
	 * @filter json_api_badge_controller_path
	 */
	public function get_badge_controller_path() {
		return sprintf( "%s/api/badge.php", BadgeOS_Open_Badges_Issuer_AddOn::$directory_path );
	}

}

add_action( 'init', array( 'BOSOBI_JSON', 'init' ) );
