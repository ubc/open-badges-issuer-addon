<?php

/**
 * This class handles the initialization and rendering of the plugin's two shortcodes:
 * badgeos_backpack_push, which lets the user claim badges.
 * badgeos_backpack_registered_email, which lets the user change the email they claim with. (right?)
 */
class BOSOBI_Shortcodes {

	/**
	 * @filter init
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_scripts_and_styles' ) );

		add_shortcode( 'badgeos_backpack_push', array( __CLASS__, 'shortcode_push') );
		add_shortcode( 'badgeos_backpack_registered_email', array( __CLASS__, 'shortcode_email') );

		if ( BOSOBI_Settings::get( 'public_evidence' ) ) {
			add_filter( 'badgeos_public_submissions', array( __CLASS__, 'set_public_badge_submission' ), 999, 1 );
		}
	}

	/**
	 * Registers all the scripts and styles necessary for this plugin's shortcodes.
	 * @filter wp_enqueue_scripts
	 */
	public static function register_scripts_and_styles() {
		wp_register_script( 'mozilla-issuer-api', 'https://backpack.openbadges.org/issuer.js', array( 'badgeos-backpack' ), null );
		wp_register_script( 'badgeos-backpack', BadgeOS_Open_Badges_Issuer_AddOn::$directory_url . '/public/js/badgeos-backpack.js', array( 'jquery' ), '1.0.0', true );
		wp_register_style( 'badgeos-backpack-style', BadgeOS_Open_Badges_Issuer_AddOn::$directory_url . '/public/css/badgeos-backpack.css', null, '1.0.2' );
	}

	/**
	 * Set if badge submission evidence is public
	 */
	public static function set_public_badge_submission( $public ) {
		// For some reason, our plugin requires this setting to always return true.
		return true;
	}
	
	/**
	 * This executes the badgeos_backpack_push shortcode
	 * It renders a list of badges that the user has earned, and providing buttons to claim those badges.
	 *
	 * @since  1.0.0
	 */
	public static function shortcode_push( $atts = array() ) {
		// Check if shortcode has already been run
		if ( isset( $GLOBALS['badgeos_backpack_push'] ) ) {
			return;
		} else if ( ! is_user_logged_in() ) {
			return __( 'Please log in to push badges to Mozilla Backpack', 'bosobi' );
		}

		$user_id = get_current_user_id();

		// Allow the shortcode attribute to override out user_id if it is set.
		extract( shortcode_atts( array(
			'user_id' => $user_id,
		), $atts ) );
		
		// Copy several key variables into our javascript file.
		wp_localize_script( 'badgeos-backpack', 'badgeos', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php', 'relative' ) ),
			'json_url' => esc_url( site_url() . '/' . get_option( 'json_api_base', 'api' ) . '/badge/achievements/' ),
			'use_signed_assertions' => ( BOSOBI_Settings::get( 'assertion_type' ) === 'signed' ),
			'user_id'  => $user_id,
		) );
		
		// Enqueue the necessary js scripts.
		wp_enqueue_script( 'mozilla-issuer-api' );
		wp_enqueue_script( 'badgeos-backpack' );

		// Enqueue the necessary css styles.
		wp_enqueue_style( 'badgeos-front' );
		wp_enqueue_style( 'badgeos-backpack-style' );
		
		$badges = null; // I don't know why this line is here, left it just to be safe.

		// Render the shortcode.
		ob_start();
		?>
		<div class="badgeos_backpack_action">
			<a href="" class="badgeos_backpack_all button">
				<?php echo __( 'Send selected to Mozilla Backpack', 'bosobi' ); ?>
			</a>
		</div>
		<div id="badgeos-achievements-container"></div>
		<div class="badgeos-spinner"></div>
		<?php
	
		// Save a global to prohibit this shortcode from being run multiple times on the same page.
		$GLOBALS['badgeos_backpack_push'] = true;

		// Return the shortcode HTML.
		return ob_get_clean();
	}

	/**
	 * Achievement List with Backpack Push Short Code
	 *
	 * @since  1.0.0
	 */
	public static function shortcode_email( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			?>
			<em><?php echo __( 'Please log in to push badges to Mozilla Backpack', 'bosobi' ); ?></em>
			<?php
		}

		extract( shortcode_atts( array(
			'user_id' => get_current_user_id(),
		), $atts ) );

		return self::registered_email();
	}
	
	public function registered_email( $user_id = 0 ) {
		$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;
		$email_alt_field = BOSOBI_Settings::get( 'alt_email' );

		if ( ! empty( $email_alt_field ) ) {
			$email = get_user_meta( $user_id, $email_alt_field, true );
		}

		if ( empty( $email ) || ! is_string( $email ) ) {
			$user = get_userdata( $user_id );
			$email = $user->user_email;
		}

		return $email;
	}

}

add_action( 'init', array( 'BOSOBI_Shortcodes', 'init' ) );
