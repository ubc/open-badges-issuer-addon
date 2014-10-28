<?php
class BOSOBI_Settings {

	public static final $prefix = 'bosobi';
	public static final $settings_slug = self::$prefix . '-settings';
	public static final $sections_slug = self::$prefix . '-sections';
	public static final $page_slug = self::$prefix . '-page';
	
	public static function init() {	
		add_action( 'admin_init', array( __CLASS__, 'save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'init_menus' ) );
	}
	
	/**
	 * Create BadgeOS Settings menus
	 */
	public static function init_menus() {
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
	
	public static function render() {
		$badgeos_settings = get_option( 'badgeos_settings' );
		
		if ( ! current_user_can( $badgeos_settings['minimum_role'] ) ) {
			wp_die( "You do not have sufficient permissions to access this page." );
		}
		
		?>
		<div class="wrap">
        	<?php 
        		settings_errors();
        		self::json_api_controller_status();
        	?>
            <h2>Open Badges Issuer Add-on Settings</h2>
            <form method="post" action="options.php"> 
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
		$json_api_controllers = explode( ",", get_option( 'json_api_controllers' ));

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

		<p>If you are a developer and would like to support the development of this plugin issues and contributions can 
        be made to <a href="https://github.com/mhawksey/badgeos-open-badges-issuer">https://github.com/mhawksey/badgeos-open-badges-issuer</a></p>
        
        <p>This add-on has been developed by the <a href="https://alt.ac.uk">Association for Learning Technology</a></p>
        <?php
	}
	
	public static function section_general() {
		// Do Nothing
	}
	
	public static function section_override() {
		echo __('These are optional settings to set the <a href="https://github.com/mozilla/openbadges-specification/blob/master/Assertion/latest.md#issuerorganization">IssuerOrganiztion</a>. 
		By default the add-on will use the blog name and url.', 'bosobi');
	}

	/**
	 * This function provides text inputs for settings fields
	 */
	public static function field_input( $args ) {
		$field = $args['name']; // Get the field name from the $args array
		$value = get_option( $field ); // Get the value of this setting

		?>
		<input type="text" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo $value; ?>" />
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}
	
	/**
	 * This function provides text inputs for settings fields
	 */
	public static function field_textarea( $args ) {
		$field = $args['name']; // Get the field name from the $args array
		$value = get_option( $field ); // Get the value of this setting

		?>
		<textarea type="text" name="<?php echo $field; ?>" id="<?php echo $field; ?>">
			<?php echo $value; ?>
		</textarea>
		<p class="description"><?php echo $args['description']; ?></p>
		<?php
	}
	
	/**
	* This function provides slect inputs for settings fields
	*/
	public static function settings_field_input_select( $args ) {
		$field = $args['name']; // Get the field name from the $args array
		$value = get_option( $field ); // Get the value of this setting

		?>
		<select name="<?php echo $field; ?>" id="<?php echo $field; ?>">
			<option value="">
				<?php echo __( '- Primary Email Only -', 'bosobi' ); ?>
			</option>

			<?php foreach($args['choices'] as $val => $trans) { ?>
				<option value="<?php echo $val; ?>" <?php selected( $value, $val, false ) ?>>
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
	public static function settings_field_input_radio( $args ) {
		$field = $args['name']; // Get the field name from the $args array
		$value = get_option( $field ); // Get the value of this setting

		foreach( $args['choices'] as $val => $trans ) {
			$val = esc_attr( $val );

			?>
			<input id="<?php echo $field . '-' . $val; ?>" type="radio" name="<?php echo $field; ?>" value="<?php echo $val; ?>" <?php checked( $val, $value, false ); ?> />
			<label for="<?php echo $field . '-' . $val; ?>">
				<?php echo esc_html( $trans ); ?>
			</label>
			<?php
		}

		?>
		<p class="description"><?php echo $args['description']; ?></p>
	 	<?php
	}

	public static function field_slug( $slug ) {
		return sellf:$prefix . '_' . $slug;
	}
	
	public static function save() {
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'public_evidence' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'alt_email' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_name' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_url' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_description' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_image' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_email' ) );
		register_setting( 'badgeos_obi_issuer_settings', self::field_slug( 'org_revocationList' ) );


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
		
		add_settings_section(
			self::$sections_slug . '-override',
			__( 'Issuer Organiztion Override', 'bosobi' ), 
			array( __CLASS__, 'section_override'), 
			self::$page_slug
		);


		add_settings_field(
			self::field_slug( 'alt_email' ),
			__( 'Alternative Email', 'bosobi' ),
			array( __CLASS__, 'field_select' ),
			self::$page_slug,
			self::$sections_slug . '-general',
			array(
				'name' => self::field_slug( 'alt_email' ),
				'choices' => wp_get_user_contact_methods(),
				'description' => __( 'Specify an optional additional email field if you would like users to be able to collect badges using a different address', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'public_evidence' ), 
			__( 'Public evidence', 'bosobi' ), 
			array( __CLASS__, 'field_radio' ),
			self::$page_slug,
			self::$sections_slug . '-general',
			array(
				'name' => self::field_slug( 'public_evidence' ),
				'choices' => array(
					'yes' => 'Enable',
					'no' => 'Disable'
				),
				'description' => __( 'Enable or Disable public badge evidence for submissions', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_name' ),
			__( 'Name', 'bosobi' ), 
			array( __CLASS__, 'field_input' ),
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_name' ),
				'description' => __( 'The name of the issuing organization.', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_url' ),
			__( 'Url', 'bosobi' ), 
			array( __CLASS__, 'field_input' ), 
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_url' ),
				'description' => __( 'URL of the institution', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_description' ),
			__( 'Description', 'bosobi' ), 
			array( __CLASS__, 'field_textarea' ),
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_description' ),
				'description' => __( 'A short description of the institution', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_image' ),
			__( 'Image', 'bosobi' ), 
			array( __CLASS__, 'field_input' ), 
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_image' ),
				'description' => __( 'An image representing the institution', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_email' ),
			__( 'Email', 'bosobi' ), 
			array( __CLASS__, 'field_input' ), 
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_email' ),
				'description' => __( 'Contact address for someone at the organization.', 'bosobi' ),
			)
		);
		
		add_settings_field(
			self::field_slug( 'org_revocationList' ),
			__( 'Revocation List Url', 'bosobi' ), 
			array( __CLASS__, 'field_input' ), 
			self::$page_slug,
			self::$sections_slug . '-override',
			array(
				'name' => self::field_slug( 'org_revocationList' ),
				'description' => __( 'URL of the Badge Revocation List. The endpoint should be a JSON representation of an object where the keys are the uid a revoked badge assertion, and the values are the reason for revocation. This is only necessary for signed badges.', 'bosobi' ),
			)
		);
	}
}

add_action( 'init', array( 'BOSOBI_Settings', 'init' ) );
