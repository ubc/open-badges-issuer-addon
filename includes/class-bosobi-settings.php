<?php

/**
 * This class handles the rendering and storage of the plugin's settings.
 */
class BOSOBI_Settings {
	// A prefix to be added to make our slugs unique
	public static $prefix = 'bosobi';
	// A slug to identify all the setting sections on our page.
	public static $sections_slug = '';
	// A slug to identify the page we are adding.
	public static $page_slug = '';
	// A list of fields that we will be registering with any additional data the field needs, such as default.
	public static $fields = array();
	
	public static function init() {
		// Define the slugs
		self::$sections_slug = self::$prefix . '-sections';
		self::$page_slug = self::$prefix . '-page';

		// Initialize the fields with their defaults.
		self::init_field_defaults();

		// Add our actions.
		add_action( 'admin_init', array( __CLASS__, 'init_fields' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'init_admin_menus' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'init_network_admin_menus' ) );
	}
	
	/**
	 * Create BadgeOS Settings menus
	 * @filter admin_menu
	 */
	public static function init_admin_menus() {
		// Add the settings menu.
		add_submenu_page( 'badgeos_badgeos', 
			__( 'Open Badges Issuer Settings', 'bosobi' ), 
			__( 'Open Badges Issuer Settings', 'bosobi' ), 
			badgeos_get_manager_capability(), 
			'open-badges-issuer', 
			array( __CLASS__, 'render' )
		);
		
		// Add the log menu.
		add_submenu_page( 'badgeos_badgeos',
			__( 'Open Badges Issuer Log Entries', 'bosobi' ),
			__( 'Open Badges Issuer Log Entries', 'bosobi' ),
			badgeos_get_manager_capability(),
			'edit.php?post_type=open-badge-entry'
		);
	}

	/**
	 * Create BadgeOS Settings menus for the Network Administrator
	 * @filter network_admin_menu
	 */
	static function init_network_admin_menus() {
		add_submenu_page( 'settings.php',
			__( 'Open Badges Issuer', 'bosobi' ),
			__( 'Open Badges Issuer', 'bosobi' ),
			'manage_options',
			'open-badges-issuer',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * This should be used to wrap any reference to the field that is external to this class.
	 */
	public static function field_slug( $slug ) {
		return self::$prefix . '_' . $slug;
	}

	/**
	 * Retrieve a field from the plugin's settings.
	 * This function implements defaults and cascading settings from the Network Admin.
	 */
	public static function get( $slug, $use_default = true ) {
		if ( is_network_admin() ) {
			$return = get_site_option( self::field_slug( $slug ) );
		} else {
			$return = get_option( self::field_slug( $slug ) );
		}

		if ( $use_default && empty( $return ) ) {
			$return = self::get_default( $slug );
		}

		return $return;
	}

	/**
	 * Retrieve the default for one of this plugin's settings.
	 */
	public static function get_default( $slug ) {
		if ( is_multisite() && ! is_network_admin() ) {
			$return = get_site_option( self::field_slug( $slug ) );
		}
		
		if ( empty( $return ) && ! empty( self::$fields[ $slug ]['default'] ) ) {
			$return = self::$fields[ $slug ]['default'];
		}

		if ( empty( $return ) ) {
			$return = null;
		}

		return $return;
	}

	/**
	 * Save the given data as settings for the entire Network.
	 */
	public static function save_network_settings( $data ) {
		foreach ( self::$fields as $slug => $field ) {
			$slug = self::field_slug( $slug );

			if ( ! empty( $data[ $slug ] ) ) {
				update_site_option( $slug, $data[ $slug ] );
			} else {
				delete_site_option( $slug );
			}
		}
	}
	
	/**
	 * Render the settings page.
	 */
	public static function render() {
		// Get the options for BadgeOS, our parent plugin.
		$badgeos_settings = get_option( 'badgeos_settings' );
		
		// Make sure that the user has the minimum permission to handle BadgeOS
		if ( ! current_user_can( $badgeos_settings['minimum_role'] ) ) {
			// If no, tell them so.
			wp_die( "You do not have sufficient permissions to access this page." );
		}

		// Check if this is a network admin page.
		if ( is_network_admin() ) {
			// If so, then check if the POST is not empty.
			if ( ! empty( $_POST ) ) {
				// In this case that means the form has already been submitted, so save the data that the user submitted.
				self::save_network_settings( $_POST );
			}
		} else {
			// If not, then set the action field so that the saving of our data will be handled by options.php
			$action = 'action="options.php"';
		}
		
		// Render the settings page.
		?>
		<div class="wrap">
			<?php 
				// Output any errors that might exist.
				settings_errors();
				// Output the status of the JSON API.
				self::json_api_controller_status();
			?>
			<h2>Open Badges Issuer Add-on Settings</h2>
			<form method="post" <?php echo $action; ?>> 
				<?php
					// Prep nonce and other info for our page.
					settings_fields( self::$page_slug );
					// Render each section.
					do_settings_sections( self::$page_slug );
					// Render a submit button.
					submit_button();
				?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * This function will render an error if the JSON API hasn't been initialized properly.
	 */
	public static function json_api_controller_status() {
		// Get the list of JSON API Controllers
		$json_api_controllers = explode( ",", get_option( 'json_api_controllers' ) );

		// Make sure that our controller has been registered.
		if ( ! in_array( 'badge', $json_api_controllers ) ) {
			// If not, render a warning.
			?>
			<div id="message" class="error">
				<p>
					<?php printf( __( 'Open Badges Issuer requires the JSON API Mozilla Open Badges Generator to be active. Please <a href="">activate in JSON API settings</a>', 'bosobi' ), admin_url( 'options-general.php?page=json-api' ) ); ?>
				</p>
			</div>
			<?php
		}
	}
	
	/**
	 * Renders an explanation of the plugin and the settings page.
	 */
	public static function section_about() {
		?>
		<p>This plugin extends BadgeOS to allow you to host and issue Open Badges compatible assertions. This means 
		users can directly add BadgeOS awarded badges to their Mozilla Backpack. To enable users to send create a new page and 
		include the shortcode <code>[badgeos_backpack_push]</code>.</p> 

		<p>If you are a developer and would like to support the development of this plugin; issues and contributions can 
		be made to the <a href="https://github.com/mhawksey/badgeos-open-badges-issuer">GitHub Repository</a>.</p>
		
		<p>This add-on has been developed by the <a href="https://alt.ac.uk">Association for Learning Technology</a>.</p>
		<?php
	}
	
	/**
	 * Renders an explanation for the general settings section.
	 */
	public static function section_general() {
		// Do Nothing
	}
	
	/**
	 * Renders an explanation for the Issuer Organization settings section.
	 */
	public static function section_override() {
		if ( ! is_multisite() || is_network_admin() ) {
			echo __('These are optional settings to set the <a href="https://github.com/mozilla/openbadges-specification/blob/master/Assertion/latest.md#issuerorganization">IssuerOrganiztion</a>. 
			By default the add-on will use the blog name and url.', 'bosobi');			
		} else {
			echo __('These are optional settings to set the <a href="https://github.com/mozilla/openbadges-specification/blob/master/Assertion/latest.md#issuerorganization">IssuerOrganiztion</a>. 
			By default the add-on will use the configuration set by your Network Administrator.', 'bosobi');			
		}
	}
	
	/**
	 * Renders an explanation for the keys settings section.
	 */
	public static function section_keys() {
		echo __("If 'signed' assertions are enabled, these keys are used to encode and verify the assertion.<br><em>Modify these with care, if you change the keys all previously issued badges will no longer be valid!</em>", 'bosobi');			
	}

	/**
	 * This function renders a text input using the given data.
	 */
	public static function field_input( $args ) {
		$slug = $args['slug']; // Get the field name from the $args array
		$value = self::get( $slug, false ); // Get the value of this setting
		$default = self::get_default( $slug );
		$slug = self::field_slug( $slug );

		$placeholder = empty( $default ) ? '' :  ' placeholder="' . $default . '"';

		?>
		<input type="text" name="<?php echo $slug; ?>" id="<?php echo $slug; ?>" class="regular-text" value="<?php echo $value; ?>"<?php echo $placeholder; ?> />
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}
	
	/**
	 * This function renders a textarea input using the given data.
	 */
	public static function field_textarea( $args ) {
		$slug = $args['slug']; // Get the field name from the $args array
		$value = self::get( $slug, false ); // Get the value of this setting
		$default = self::get_default( $slug );
		$slug = self::field_slug( $slug );

		$placeholder = empty( $default ) ? '' :  ' placeholder="' . $default . '"';

		?>
		<textarea type="text" name="<?php echo $slug; ?>" id="<?php echo $slug; ?>" class="large-text"<?php echo $placeholder; ?>><?php echo $value; ?></textarea>
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}
	
	/**
	 * This function renders a select input using the given data.
	 */
	public static function field_select( $args ) {
		$slug = $args['slug']; // Get the field name from the $args array
		$value = self::get( $slug ); // Get the value of this setting
		$slug = self::field_slug( $slug );

		?>
		<select name="<?php echo $slug; ?>" id="<?php echo $slug; ?>">
			<option value="">
				<?php echo __( '- Primary Email Only -', 'bosobi' ); ?>
			</option>

			<?php foreach( $args['choices'] as $val => $trans ) { ?>
				<option value="<?php echo $val; ?>" <?php selected( $value, $val ) ?>>
					<?php echo $trans; ?>
				</option>';
			<?php } ?>
		</select>
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}
	
	/**
	 * This function renders a radio button list using the given data.
	 */
	public static function field_radio( $args ) {
		$slug = $args['slug']; // Get the field name from the $args array
		$value = self::get( $slug, false ); // Get the value of this setting
		$default = self::get_default( $slug );
		$slug = self::field_slug( $slug );

		if ( ! empty( $default ) ) {
			if ( BadgeOS_Open_Badges_Issuer_AddOn::is_network_activated() && ! is_network_admin() ) {
				$args['choices'][''] = "Use Network Setting (" . $args['choices'][ $default ] . ")";
			} elseif ( empty( $value ) ) {
				$args['choices'][''] = "Default (" . $args['choices'][ $default ] . ")";
			}
		}

		foreach( $args['choices'] as $val => $trans ) {
			$val = esc_attr( $val );

			?>
			<input id="<?php echo $slug . '-' . $val; ?>" type="radio" name="<?php echo $slug; ?>" value="<?php echo $val; ?>" <?php checked( $value, $val ); ?> />
			<label for="<?php echo $slug . '-' . $val; ?>">
				<?php echo esc_html( $trans ); ?>
			</label>
			<?php
		}

		?>
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}

	/**
	 * This initializes each field. Namely it registers the fact that the field exists, so that other code knows about it.
	 * This function also allows the definition of defaults.
	 */
	public static function init_field_defaults() {

		// Check to see if our private key is defined.
		$key = self::get( 'private_key' );
		if ( empty( $key ) ) {
			// If no, then we need to generate one.
			// TODO: check that openssl.cnf is installed.

			// Create a variable to hold the key.
			$private_key = null;
			// Create the private/public key pair.
			$response = openssl_pkey_new();
			// Extract the private key into our variable.
			openssl_pkey_export( $response, $private_key );

			// Extract the public key.
			$public_key = openssl_pkey_get_details( $response );
			$public_key = $public_key["key"];

			// Save our private and public keys to the database.
			update_site_option( self::field_slug( 'private_key' ), $private_key );
			update_site_option( self::field_slug( 'public_key' ), $public_key );
		}

		// Define a list of all fields, with their defaults if applicable.
		self::$fields = array(
			'assertion_type' => array(
				'default' => 'hosted',
			),
			'allow_override' => array(
				'default' => ( BadgeOS_Open_Badges_Issuer_AddOn::is_network_activated() ? 'off' : 'on' ),
			),
			'alt_email' => array(),
			'public_evidence' => array(),
			'org_name' => array(
				'default' => get_bloginfo( 'name', 'display' ),
			),
			'org_url' => array(
				// TODO: Add validation so that the url is properly formed.
				'default' => site_url(),
			),
			'org_description' => array(),
			'org_image' => array(
				// TODO: Add validation so that the url is properly formed.
			),
			'org_email' => array(),
			'org_revocationList' => array(),
			'public_key' => array(),
			'private_key' => array(),
		);
	}
	
	/**
	 * Define the fields and settings on our page using the Settings API.
	 * @filter 'admin_init'
	 */
	public static function init_fields() {
		// Add the about section which explains the plugin.
		add_settings_section(
			self::$sections_slug . '-about', 
			__( 'About', 'bosobi' ), 
			array( __CLASS__, 'section_about' ), 
			self::$page_slug
		);
		
		// Add the general section for some general settings.
		add_settings_section(
			self::$sections_slug . '-general', 
			__( 'General Settings', 'bosobi' ), 
			array( __CLASS__, 'section_general' ), 
			self::$page_slug
		);

		// If the user has permission, then add the Issuer Organization and Public / Private Keys sections.
		if ( ! is_multisite() || is_network_admin() || self::get( 'allow_override' ) === "on" ) {
			add_settings_section(
				self::$sections_slug . '-override',
				__( 'Issuer Organiztion Override', 'bosobi' ), 
				array( __CLASS__, 'section_override'), 
				self::$page_slug
			);

			add_settings_section(
				self::$sections_slug . '-keys',
				__( 'Public / Private Keys', 'bosobi' ), 
				array( __CLASS__, 'section_keys'), 
				self::$page_slug
			);

			self::init_section_fields( 'general', array(
				'assertion_type' => array(
					'title' => "Assertion Type",
					'type' => 'radio',
					'choices' => array(
						'signed' => 'Signed',
						'hosted' => 'Hosted',
					),
					'description' => __( 'Hosted requires your website URL to be stable. Third parties will access an assertion generated by this plugin to verify the authenticity of your badges. Signed assertions are validated using a public/private key.', 'bosobi' ),
				),
			) );
		}
		
		// Only a Network Admin can restrict sub-sites from overriding settings.
		if ( is_network_admin() ) {
			self::init_section_fields( 'general', array(
				'allow_override' => array(
					'title' => "Allow Override",
					'type' => 'radio',
					'choices' => array(
						'on' => 'Enable',
						'off' => 'Disable',
					),
					'description' => __( 'Allows sub-sites to override the settings on this page.', 'bosobi' ),
				),
			) );
		}

		self::init_section_fields( 'general', array(
			'alt_email' => array(
				'title' => "Alternative Email",
				'type' => 'select',
				'choices' => wp_get_user_contact_methods(),
				'description' => __( 'Specify an optional additional email field if you would like users to be able to collect badges using a different address.', 'bosobi' ),
			),
			'public_evidence' => array(
				'title' => "Public Evidence",
				'type' => 'radio',
				'choices' => array(
					'yes' => 'Enable',
					'no' => 'Disable',
				),
				'description' => __( 'Enable or Disable public badge evidence for submissions.', 'bosobi' ),
			),
		) );

		// Don't define the fields for the override and keys sections unless the user has permission to edit them.
		if ( ! is_multisite() || is_network_admin() || self::get( 'allow_override' ) === "on" ) {
			self::init_section_fields( 'override', array(
				'org_name' => array(
					'title' => "Name",
					'type' => 'input',
					'description' => __( 'The name of the issuing organization.', 'bosobi' ),
				),
				'org_url' => array(
					'title' => "URL",
					'type' => 'input',
					'description' => __( 'URL of the institution.', 'bosobi' ),
				),
				'org_description' => array(
					'title' => "Description",
					'type' => 'textarea',
					'description' => __( 'A short description of the institution.', 'bosobi' ),
				),
				'org_image' => array(
					'title' => "Image URL",
					'type' => 'input',
					'description' => __( 'An image representing the institution.', 'bosobi' ),
				),
				'org_email' => array(
					'title' => "Email",
					'type' => 'input',
					'description' => __( 'Contact address for someone at the organization.', 'bosobi' ),
				),
				'org_revocationList' => array(
					'title' => "Revocation List Url",
					'type' => 'input',
					'description' => __( 'URL of the Badge Revocation List. The endpoint should be a JSON representation of an object where the keys are the uid of a revoked badge assertion, and the values are the reason for revocation. This is only necessary for signed badges.', 'bosobi' ),
				),
			) );
			
			self::init_section_fields( 'keys', array(
				'public_key' => array(
					'title' => "Public Key",
					'type' => 'textarea',
					'description' => __( 'Any public key.', 'bosobi' ),
				),
				'private_key' => array(
					'title' => "Private Key",
					'type' => 'textarea',
					'description' => __( 'The private key that corresponds to your public key.', 'bosobi' ),
				),
			) );
		}
	}

	/**
	 * This function defines specific fields using the given data.
	 * See the init_fields function for usage.
	 */
	public static function init_section_fields( $section, $fields ) {
		// Loop through the provided list of fields.
		foreach ( $fields as $slug => $field ) {
			// Copy the field's slug into it's data array.
			$field['slug'] = $slug;

			// Extract a field fields.
			$title = $field['title'];
			$type = $field['type'];
			$slug = self::field_slug( $slug );

			// Remove the title and type from the field's data array.
			unset( $field['title'] );
			unset( $field['type'] );

			// Register this field as a setting, so that Wordpress doesn't discard it when the user tries to set it.
			register_setting( self::$page_slug, $slug );
			
			// Finally define the field using the Settings API
			add_settings_field(
				$slug, // Slug
				__( $title, 'bosobi' ), // Field title
				array( __CLASS__, 'field_' . $type ), // Rendering callback
				self::$page_slug, // Page it appears on.
				self::$sections_slug . '-' . $section, // Section it appears in.
				$field // Data to pass to the rendering callback
			);
		}
	}
}

add_action( 'init', array( 'BOSOBI_Settings', 'init' ) );
