<?php
/**
 * Plugin Name: BadgeOS Open Badges Add-On
 * Plugin URI: 
 * Description: 
 * Tags: 
 * Author: 
 * Version: 1.0
 * Author URI: 
 * License: GNU AGPLv3
 * License URI: http://www.gnu.org/licenses/agpl-3.0.html
 */

class BadgeOS_OpenBadges {

	public static $enable_mail = false;
	public static $enable_popup = false;
	public static $enable_open_badges = false;

	/**
	 * Initialize the Triggers Add-On
	 */
	public static function init() {
		// If BadgeOS is unavailable, deactivate our plugin
		add_action( 'admin_notices', array( __CLASS__, 'check_requirements' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 11 );
	}

	/**
	 * Load the plugin, if we meet requirements.
	 */
	public static function load() {
		if ( self::meets_requirements() ) {
			add_action( 'badgeos_award_achievement', array( __CLASS__, 'issue_badge' ), 10, 5 );
			add_action( 'badgeos_settings', array( __CLASS__, 'settings' ) );
		}
	}

	/**
	 * Generate a custom error message and deactivates the plugin if we don't meet requirements
	 */
	public static function check_requirements() {
		if ( ! self::meets_requirements() ) {
			?>
			<div id="message" class="error">
				<p>
					<?php printf( __( 'BadgeOS Open Badges Add-On requires BadgeOS and has been <a href="%s">deactivated</a>. Please install and activate BadgeOS and then reactivate this plugin.', 'badgeos-learndash' ), admin_url( 'plugins.php' ) ); ?>
				</p>
			</div>
			<?php

			// Deactivate our plugin
			deactivate_plugins( self::$basename );
		}
	}

	/**
	 * Checks if the required plugin is installed.
	 */
	public static function meets_requirements() {
		return class_exists( 'BadgeOS' ) && function_exists( 'badgeos_get_user_earned_achievement_types' );
	}

	public static function settings() {
		error_log("checkin '".__FUNCTION__."', filter: '".current_filter()."'; args: ".print_r(func_get_args(), true));

		$badgeos_settings = get_option( 'badgeos_settings' );
		$open_badges_url = ( isset( $badgeos_settings['open_badges_url'] ) ) ? $badgeos_settings['open_badges_url'] : '';
		
		?>
		<tr valign="top">
			<th scope="row">
				<label for="open_badges_url"><?php _e( 'Open Badges Verification URL:', 'badgeos-open' ); ?></label>
			</th>
			<td>
				<input id="open_badges_url" name="badgeos_settings[open_badges_url]" type="text" value="<?php echo esc_attr( $open_badges_url ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="open_badges_url"><?php _e( 'Open Badges Verification URL:', 'badgeos-open' ); ?></label>
			</th>
			<td>
				<input id="open_badges_url" name="badgeos_settings[open_badges_url]" type="text" value="<?php echo esc_attr( $open_badges_url ); ?>" class="regular-text" />
			</td>
		</tr>
		<?php
	}

	public static function issue_badge( $user_id, $achievement_id, $this_trigger, $site_id, $args ) {
		error_log("checkin '".__FUNCTION__."', filter: '".current_filter()."'; args: ".print_r(func_get_args(), true));

		$user = get_userdata( $user_id );
		$achievement = get_post( $achievement_id );

		if ( $achievement->post_type != 'step' ) {
			if ( self::$enable_mail ) {
				self::send_email( $user, $achievement );
			}
		}
	}

	public static function send_email( $user, $achievement ) {
		error_log("checkin '".__FUNCTION__."', filter: '".current_filter()."'; args: ".print_r(func_get_args(), true));

		$subject = "Achievement Get!";

		ob_start();
		?>
		You have achieved <?php echo $achievement->post_title; ?>
		<?php
		$message = ob_get_clean();

		//wp_mail( $user->user_email, $subject, $message );
	}
}

BadgeOS_OpenBadges::init();

/*
add_action( 'badgeos_unlock_badges', function($arg1) {
	error_log("badgeos_unlock_badges");
	error_log("achievement_object: ".var_export($arg1, true));
} );

add_action( 'badgeos_render_achievement', function($arg1, $arg2) {
	error_log("badgeos_render_achievement");
	error_log("arg1: ".var_export($arg1, true));
	error_log("arg2: ".var_export($arg2, true));
} );

add_action( 'badgeos_unlock_step', function($arg1) {
	error_log("badgeos_unlock_step");
	error_log("arg1: ".var_export($arg1, true));
} );

add_action( 'badgeos_award_achievement', function($arg1 = "not received", $arg2 = "non received") {
	error_log("badgeos_award_achievement");
	error_log("user_id: ".var_export($arg1, true));
	error_log("achievement_id: ".var_export($arg2, true));
} );

add_filter( 'badgeos_earned_achievement_message', function($arg1) {
	error_log("badgeos_earned_achievement_message");
	error_log("arg1: ".var_export($arg1, true));
} );
*/