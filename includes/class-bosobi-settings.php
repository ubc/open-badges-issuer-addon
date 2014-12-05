<?php
class BOSOBI_Settings {

	public static $prefix = 'bosobi';
	public static $settings_slug = '';
	public static $sections_slug = '';
	public static $page_slug = '';
	public static $fields = array();
	
	public static function init() {
		error_log("--> LOAD ".__CLASS__);
		
		$settings_slug = self::$prefix . '-settings';
		$sections_slug = self::$prefix . '-sections';
		$page_slug = self::$prefix . '-page';

		self::init_field_defaults();

		add_action( 'admin_init', array( __CLASS__, 'init_fields' ) );
		add_action( 'admin_menu', array( __CLASS__, 'init_admin_menus' ) );
		add_action( 'network_admin_menu', array(__CLASS__, 'init_network_admin_menus' ) );
		add_action( 'admin_post_update_bosobi_settings', array( __CLASS__, 'save_network_settings' ) );
	}
	
	/**
	 * Create BadgeOS Settings menus
	 */
	public static function init_admin_menus() {
		add_submenu_page( 'badgeos_badgeos', 
			__( 'Open Badges Issuer Settings', 'bosobi' ), 
			__( 'Open Badges Issuer Settings', 'bosobi' ), 
			badgeos_get_manager_capability(), 
			'open-badges-issuer', 
			array( __CLASS__, 'render' )
		);
		
		add_submenu_page( 'badgeos_badgeos',
			__( 'Open Badges Issuer Log Entries', 'bosobi' ),
			__( 'Open Badges Issuer Log Entries', 'bosobi' ),
			badgeos_get_manager_capability(),
			'edit.php?post_type=open-badge-entry'
		);
	}

	static function init_network_admin_menus() {
		error_log("---> LOADING NETWORK ADMIN PAGE");
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

	public static function get_default( $slug ) {
		if ( is_multisite() && ! is_network_admin() ) {
			$return = get_site_option( self::field_slug( $slug ) );
		}
		
		if ( empty( $return ) && ! empty( self::$fields[ $slug ]['default'] ) ) {
			$return = self::$fields[ $slug ]['default'];
		}

		return $return;
	}

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
	
	public static function render() {
		$badgeos_settings = get_option( 'badgeos_settings' );
		
		if ( ! current_user_can( $badgeos_settings['minimum_role'] ) ) {
			wp_die( "You do not have sufficient permissions to access this page." );
		}

		if ( is_network_admin() ) {
			if ( ! empty( $_POST ) ) {
				self::save_network_settings( $_POST );
			}
			//$action = admin_url('admin-post.php?action=update_bosobi_settings');
		} else {
			$action = 'action="options.php"';
		}
		
		?>
		<div class="wrap">
			<?php 
				settings_errors();
				self::json_api_controller_status();
			?>
			<h2>Open Badges Issuer Add-on Settings</h2>
			<form method="post" <?php echo $action; ?>> 
				<?php 
					settings_fields( self::$settings_slug );
					do_settings_fields( self::$settings_slug );
					do_settings_sections( self::$sections_slug );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}
	
	public static function json_api_controller_status() {
		$json_api_controllers = explode( ",", get_option( 'json_api_controllers' ) );

		if ( ! in_array( 'badge', $json_api_controllers) ) {
			?>
			<div id="message" class="error">
				<p>
					<?php printf( __( 'Open Badges Issuer requires the JSON API Mozilla Open Badges Generator to be active. Please <a href="">activate in JSON API settings</a>', 'bosobi' ), admin_url( 'options-general.php?page=json-api' ) ); ?>
				</p>
			</div>
			<?php
		}
	}
	
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
	
	public static function section_general() {
		// Do Nothing
	}
	
	public static function section_override() {
		if ( ! is_multisite() || is_network_admin() ) {
			echo __('These are optional settings to set the <a href="https://github.com/mozilla/openbadges-specification/blob/master/Assertion/latest.md#issuerorganization">IssuerOrganiztion</a>. 
			By default the add-on will use the blog name and url.', 'bosobi');			
		} else {
			echo __('These are optional settings to set the <a href="https://github.com/mozilla/openbadges-specification/blob/master/Assertion/latest.md#issuerorganization">IssuerOrganiztion</a>. 
			By default the add-on will use the configuration set by your Network Administrator.', 'bosobi');			
		}
	}
	
	public static function section_keys() {
		echo __("If 'signed' assertions are enabled, these keys are used to encode and verify the assertion.", 'bosobi');			
	}

	/**
	 * This function provides text inputs for settings fields
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
	 * This function provides text inputs for settings fields
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
	* This function provides slect inputs for settings fields
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
	* This function provides text inputs for settings fields
	*/
	public static function field_radio( $args ) {
		$slug = $args['slug']; // Get the field name from the $args array
		$value = self::get( $slug, false ); // Get the value of this setting
		$default = self::get_default( $slug );
		$slug = self::field_slug( $slug );

		if ( is_multisite() && ! is_network_admin() && ! empty( $default ) ) {
			$args['choices']['default'] = "Use Network Setting (" . $args['choices'][ $default ] . ")";

			if ( empty( $value ) ) {
				$value = 'default';
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

	public static function init_field_defaults() {
		$key = self::get( 'private_key' );
		if ( empty( $key ) ) {
			// TODO: check that openssl.cnf is installed.
			$private_key = null;
			$response = openssl_pkey_new();
			openssl_pkey_export( $response, $private_key );

			$public_key = openssl_pkey_get_details( $response );
			$public_key = $public_key["key"];

			if ( is_multisite() ) {
				update_site_option( self::field_slug( 'private_key' ), $private_key );
				update_site_option( self::field_slug( 'public_key' ), $public_key );
			} else {
				update_option( self::field_slug( 'private_key' ), $private_key );
				update_option( self::field_slug( 'public_key' ), $public_key );
			}
		}

		self::$fields = array(
			'assertion_type' => array(
				'default' => 'signed',
			),
			'allow_override' => array(
				'default' => 'on',
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
	
	public static function init_fields() {
		add_settings_section(
			self::$sections_slug . '-about', 
			__( 'About', 'bosobi' ), 
			array( __CLASS__, 'section_about' ), 
			self::$page_slug
		);
		
		add_settings_section(
			self::$sections_slug . '-general', 
			__( 'General Settings', 'bosobi' ), 
			array( __CLASS__, 'section_general' ), 
			self::$page_slug
		);

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
						'hosted' => 'Hosted'
					),
					'description' => __( 'Hosted requires your website URL to be stable. Third parties will access an assertion generated by this plugin to verify the authenticity of your badges. Signed assertions are validated using a public/private key.', 'bosobi' ),
				),
			) );
		}
		
		if ( is_network_admin() ) {
			self::init_section_fields( 'general', array(
				'allow_override' => array(
					'title' => "Allow Override",
					'type' => 'radio',
					'choices' => array(
						'on' => 'Enable',
						'off' => 'Disable'
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
					'no' => 'Disable'
				),
				'description' => __( 'Enable or Disable public badge evidence for submissions.', 'bosobi' ),
			),
		) );

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

	public static function init_section_fields( $section, $fields ) {
		foreach ( $fields as $slug => $field ) {
			$field['slug'] = $slug;
			$title = $field['title'];
			$type = $field['type'];
			$slug = self::field_slug( $slug );

			unset( $field['title'] );
			unset( $field['type'] );

			register_setting( 'badgeos_obi_issuer_settings', $slug );
			
			add_settings_field(
				$slug, // Slug
				__( $title, 'bosobi' ), // Field title
				array( __CLASS__, 'field_' . $type ), // Rendering callback
				self::$page_slug, // Page
				self::$sections_slug . '-' . $section, // Section
				$field // Data
			);
		}
	}
}

add_action( 'init', array( 'BOSOBI_Settings', 'init' ) );
