<?php
/**
 * @wordpress-plugin
 * Plugin Name:       BadgeOS Open Badges Issuer Add-On (UBC fork)
 * Description:       This is a BadgeOS add-on which allows you to host Mozilla Open Badges compatible assertions and allow users to push awarded badges directly to their Mozilla  Backpack
 * Version:           1.1.2
 * Author:            mhawksey, CTLT, Devindra Payment
 * Text Domain:       bosobi
 * License:           GNU AGPLv3
 * License URI:       http://www.gnu.org/licenses/agpl-3.0.html
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/mhawksey/open-badges-issuer-addon
 */

class BadgeOS_Open_Badges_Issuer_AddOn {
	public static $basename = '';
	public static $directory_path = '';
	public static $directory_url = '';

	public static $dependencies = array(
		'BadgeOS' => 'http://wordpress.org/plugins/badgeos/',
		'JSON_API' => 'http://wordpress.org/plugins/json-api/'
	);
	
	public static function init() {
		self::$basename       = plugin_basename( __FILE__ );
		self::$directory_path = plugin_dir_path( __FILE__ );
		self::$directory_url  = plugins_url( dirname( self::$basename ) );

		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );

		add_action( 'admin_notices', array( __CLASS__, 'check_requirements' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 11 );
		
		
		/*
		 * Fixes by mhawksey, imported by jlam@credil.org
		 * See https://github.com/mhawksey/open-badges-issuer-addon/issues/3#issuecomment-112331043
		 * 
		 * cyclingzealot changed the function name from set_public_badge_submission 
		 *   to get_public_badge_submission
		 */
		add_filter('badgeos_public_submissions', array('BadgeOS_Open_Badges_Issuer_AddOn', 'get_public_badge_submission'), 999, 1);
	}
	
	
	/**
	 * Set if badge submission evidence is public
	 * 
	 * cyclingzealot changed the function name from set_public_badge_submission 
	 * to get_public_badge_submission
	 *
	 * @since  1.0.0
	 * @param  boolean $public submission display
	 * @author mhawksey, cyclingzealot - jlam@credil.org
	 * @return boolen               submission display
	 */
	public static function get_public_badge_submission($public){
		return get_option('badgeos_obi_issuer_public_evidence');
	}

	/**
	 * Load the plugin, if we meet requirements.
	 * @filter plugins_loaded
	 */
	public static function load() {
		if ( self::meets_requirements() ) {
			load_plugin_textdomain( 'bosobi', false, dirname( self::$basename ) . '/languages' );

			require_once( sprintf( "%s/includes/class-bosobi-json.php", self::$directory_path ) );
			require_once( sprintf( "%s/includes/class-bosobi-settings.php", self::$directory_path ) );
			require_once( sprintf( "%s/includes/class-bosobi-logging.php", self::$directory_path ) );

			if ( ! is_admin() ) {
				require_once( sprintf( "%s/public/class-bosobi-shortcodes.php", self::$directory_path ) );
			}
		}
	}

	/**
	 * Activation hook for the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// If BadgeOS is available, run our activation functions
		if ( self::meets_requirements() ) {
			$json_api_controllers = explode( ",", get_option( 'json_api_controllers' ) );

			if ( ! in_array( 'badge', $json_api_controllers ) ) {
				$json_api_controllers[] = 'badge';
				JSON_API::save_option( 'json_api_controllers', implode( ',', $json_api_controllers ) );
			}
		}
	}

	/**
	 * Generate a custom error message and deactivates the plugin if we don't meet requirements
	 * @filter admin_notices
	 */
	public static function check_requirements() {
		if ( ! self::meets_requirements() ) {
			?>
			<div id="message" class="error">
				<?php
				foreach ( self::$dependencies as $class => $url ) { 
					if ( ! class_exists( $class ) ) {
						$dependency = sprintf('<a href="%s">%s</a>', $url, $class);
						?>
						<p>
							<?php printf( __( 'Open Badges Issuer requires %s and has been <a href="%s">deactivated</a>. Please install and activate %s and then reactivate this plugin.', 'bosobi' ),  $dependency, admin_url( 'plugins.php' ), $dependency ); ?>
						</p>
						<?php
					}
				}
				?>
			</div>
			<?php

			// Deactivate our plugin
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}

	/**
	 * Checks if the required plugin is installed.
	 */
	public static function meets_requirements() {
		$return = true;

		foreach ( self::$dependencies as $class => $url ) {
			if ( ! class_exists( $class ) ) {
				$return = false;
				break;
			}
		}

		return $return;
	}

	/**
	 * Checks if this plugin has been network activated.
	 */
	public static function is_network_activated() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		
		return is_plugin_active_for_network( self::$basename );
	}
}

BadgeOS_Open_Badges_Issuer_AddOn::init();
