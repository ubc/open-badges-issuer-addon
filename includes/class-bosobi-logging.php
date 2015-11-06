<?php
/**
 * Modified
 * Logging Functionality
 *
 * @package BadgeOS
 * @subpackage Logging
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */
class BOSOBI_Logging {
	
	public static function init() {
		add_filter( 'bosobi_post_log_entry', array( __CLASS__, 'badgeos_post_log_entry' ), 10, 2 );
		self::create_log_post_type();

		add_action( 'wp_ajax_open_badges_recorder', array( __CLASS__, 'ajax_request_recorder' ) );
	}

	/**
	 * Handle ajax request to record sending of badges to backpack.
	 */
	public static function ajax_request_recorder() {
		// Setup our AJAX query vars
		$successes = ( isset( $_REQUEST['successes'] ) ? $_REQUEST['successes'] : false );
		$errors    = ( isset( $_REQUEST['errors'] )    ? $_REQUEST['errors']    : false );
		$user_id   = ( isset( $_REQUEST['user_id'] )   ? $_REQUEST['user_id']   : get_current_user_id() );
		
		if ( ! empty( $successes ) ) {
			foreach ( $successes as $success => $uid ) {
				add_user_meta( $user_id, '_badgeos_backpack_pushed', $uid, false );
				self::post_log_entry( $uid, $user_id, 'success' );
			}
		}
		
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				$uid = $error['assertion'];
				self::post_log_entry( $uid, $user_id, 'failed', json_encode( $error ) );
			}
		}
		
		wp_send_json_success( array(
			'successes'   => get_user_meta( $user_id, '_badgeos_backpack_pushed' ),
			'resend_text' => __( 'Resend to Mozilla Backpack', 'bosobi' ),
		) );
	}
	
	/**
	 * Register open badges logging post type
	 *
	 * @since 1.0.0
	 */
	public static function create_log_post_type() {	
		// Register Log Entries CPT
		register_post_type( 'open-badge-entry', array(
			'labels'             => array(
				'name'               => __( 'Log Entries', 'badgeos' ),
				'singular_name'      => __( 'Log Entry', 'badgeos' ),
				'add_new'            => __( 'Add New', 'badgeos' ),
				'add_new_item'       => __( 'Add New Log Entry', 'badgeos' ),
				'edit_item'          => __( 'Edit Log Entry', 'badgeos' ),
				'new_item'           => __( 'New Log Entry', 'badgeos' ),
				'all_items'          => __( 'Log Entries', 'badgeos' ),
				'view_item'          => __( 'View Log Entries', 'badgeos' ),
				'search_items'       => __( 'Search Log Entries', 'badgeos' ),
				'not_found'          => __( 'No Log Entries found', 'badgeos' ),
				'not_found_in_trash' => __( 'No Log Entries found in Trash', 'badgeos' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Open Badges Issuer Log Entries', 'bosobi' )
			),
			'public'             => false,
			'publicly_queryable' => false,
			'taxonomies'         => array( 'post_tag' ),
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'open-badge-log' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'comments' )
		) );
	}
	
	/**
	 * Posts a log entry when a user unlocks any achievement post
	 *
	 * @since  1.0.0
	 * @param  integer $object_id  The post id of the activity we're logging
	 * @param  integer $user_id    The user ID
	 * @param  string  $action     The action word to be used for the generated title
	 * @param  string  $title      An optional default title for the log post
	 * @return integer             The post ID of the newly created log entry
	 */
	public static function post_log_entry( $object_id, $user_id = 0, $action = 'success', $content = '', $title = '' ) {
		// Get the current user if no ID specified
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
	
		// Setup our args to easily pass through a filter
		$args = array(
			'user_id'   => $user_id,
			'action'    => $action,
			'object_id' => $object_id,
			'title'     => $title,
			'content' 	=> $content
		);
	
		// Write log entry via filter so it can be modified by third-parties
		$log_post_id = apply_filters( 'bosobi_post_log_entry', 0, $args );
	
		// Available action for other processes
		//do_action( 'badgeos_obi_create_log_entry', $log_post_id, $object_id, $user_id, $action );
	
		return $log_post_id;
	}
	
	/**
	 * Filter to create a badgeos-log-entry post
	 *
	 * @since  1.2.0
	 * @param  integer $log_post_id The ID of the log entry (default: 0)
	 * @param  array   $args        Available args to use for writing our new post
	 * @return integer              The updated log entry ID
	 */
	public static function badgeos_post_log_entry( $log_post_id, $args ) {
		$parsed = parse_url( $args['object_id'] );
		parse_str( $parsed['query'], $query );
		
		// If we weren't explicitly given a title, let's build one
		if ( empty( $args['title'] ) ) {
			$user          = get_userdata( $args['user_id'] );
			$achievement   = $query['uid'];
			$status        = $args['action'];
			$args['title'] = ! empty( $title ) ? $title : apply_filters( 'bosobi_log_entry_title', "{$user->user_login} {$args['action']} sending {$achievement} ", $args );
		}
	
		// Insert our entry as a 'badgeos-log-entry' post
		$log_post_id = wp_insert_post( array(
			'post_title'   => $args['title'],
			'post_content' => $args['content'],
			'post_author'  => absint( $args['user_id'] ),
			'post_type'    => 'open-badge-entry',
			'tags_input'   => $args['action'],
			'post_status'  => 'publish',
		) );
	
		// Return the ID of the newly created post
		return $log_post_id;
	}
}

add_action( 'init', array( 'BOSOBI_Logging', 'init' ) );
