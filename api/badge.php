<?php
/**
 * Controller name: Mozilla Open Badges Generator
 * Controller description: Generates Mozilla Open Badges compatible Assertions for the BadgeOS plugin
 * 
 * This class uses the JSON API plugin to exspose an api that other websites can query to get data from our plugin.
 * Each function in this class represents an api endpoint that can be accessed at the url
 * /api/badge/<function name>?<query parameters>
 */
class JSON_API_Badge_Controller {

	// This function is not intended as an api endpoint.
	public function log_headers() {
		$headers = apache_request_headers();

		foreach ( $headers as $header => $value ) {
			error_log("$header: $value");
		}
	}

	/* TODO: Implement server-side badge baking, instead of delegating our badge backing to backpack.openbadges.org/baker
	public function baked_badge() {
		$png = new PNG_MetaDataHandler('file.png');

		if ($png->check_chunks("tEXt", "openbadge")) {
			$newcontents = $png->add_chunks("tEXt", "openbadge", 'http://some.public.url/to.your.assertion.file');
		}
		
		file_put_contents('file.png', $newcontents);
	}
	*/

	/**
	 * Renders the public key used by this plugin so that our assertions can be verified using it.
	 */
	public function public_key() {
		error_log('-- START API CALL --');
		error_log('-> public_key');
		self::log_headers();
		// Because out public key is not a json, we print it out instead of returning it.
		print_r( BOSOBI_Settings::get( 'public_key' ) );
		error_log('-- END API CALL --');
	}

	/**
	 * Render an assertion for a single badge.
	 * This api expects a uid parameter in the url query.
	 * The uid indicates what badge we are rendering for.
	 */
	public function assertion() {
		error_log('-- START API CALL --');
		error_log('-> assertion');
		self::log_headers();
		global $json_api;

		// Get the query data.
		$uid_str = $json_api->query->uid;
		$uid = explode ( "-" , $uid_str );
		$post_id = $uid[0];
		$user_id = $uid[2];
		$assertion = array();

		// Make sure that the uid references a valid post.
		if ( isset( $post_id ) ) {
			// Was this request made with the intention of baking the image?
			$for_baking = true; //$json_api->query->bake; // TODO: Reimplement this if necessary.
			// Should we render a signed assertion? If the request is for baking, then it has to be hosted, not signed.
			$use_signed_verification = BOSOBI_Settings::get( 'assertion_type' ) === 'signed' && ! $for_baking;

			// Get the api url.
			$base_url = site_url() . '/' . get_option( 'json_api_base', 'api' );
			// Get the post for our post_id
			$submission = get_post( $post_id );
			// This salt is used to encode the user's email.
			$salt = "0ct3L";
			// Get the user's email.
			$email = BOSOBI_Shortcodes::registered_email( $user_id );
			// Get the post type of the post we are handling.
			$post_type = get_post_type( $post_id );
			
			// Check what the post_id is.
			if ( $post_type === "submission" && BOSOBI_Settings::get( 'public_evidence' ) ) {
				// If it is a submission, then get the achievement ID from it's post meta.
				$achievement_id = get_post_meta( $post_id, '_badgeos_submission_achievement_id', true );
				$assertion['evidence'] = get_permalink( $post_id );
			} else {
				// Otherwise, assume that it is an achievement.
				$achievement_id = $post_id;
				$assertion['evidence'] = get_permalink( $achievement_id );
			}

			// If we are using signed verification, then set the appropriate information.
			if ( $use_signed_verification ) {
				$verification = array(
					"type" => "signed",
					"url"  => $base_url .'/badge/public_key/', // This is the public key that will be used to verify our signed assertion.
				);
			} else {
				$verification = array(
					"type" => "hosted",
					"url"  => $base_url .'/badge/assertion/?uid=' . $uid_str,
				);
			}

			// Get the badge image.
			$image_url = wp_get_attachment_url( get_post_thumbnail_id( $achievement_id ) );

			// Put together the assertion, as per the documentation https://github.com/openbadges/openbadges-specification/blob/master/Assertion/latest.md
			$assertion = array_merge( array(
				"uid" => $uid_str,
				"recipient" => array(
					"type"     => "email",
					"hashed"   => true,
					"salt"     => $salt,
					"identity" => 'sha256$' . hash( 'sha256', $email . $salt )
				),
				"image"    => $for_baking ? $image_url : 'http://backpack.openbadges.org/baker?assertion=' . $base_url . '/badge/assertion/?uid=' . $uid_str . '&bake=1',
				"issuedOn" => strtotime( $submission->post_date ),
				"badge"    => $base_url . '/badge/badge_class/?uid=' . $achievement_id,
				"verify"   => $verification,
			), $assertion );

			// For signed assertions, the payload must be encoded as a JSON Web Signature
			// See https://github.com/openbadges/openbadges-specification/blob/master/Assertion/latest.md
			if ( $verification['type'] === 'signed' ) {
				// If the verification type is signed, then we should encode the assertion, using the phpseclib library.

				// We need to temporarily change the include path for using the phpseclib library.
				$old_include_path = set_include_path( sprintf( "%s/includes/phpseclib-0.3.9/", BadgeOS_Open_Badges_Issuer_AddOn::$directory_path ) );
				require_once( "Crypt/RSA.php" );

				// Create a new signature.
				$rsa = new Crypt_RSA();
				$rsa->setSignatureMode( CRYPT_RSA_SIGNATURE_PKCS1 );
				$rsa->setHash( 'sha256' );
				$rsa->setMGFHash( 'sha256' );
				$rsa->loadKey( BOSOBI_Settings::get( 'private_key' ), CRYPT_RSA_PRIVATE_FORMAT_PKCS1 );

				// Define the data for the signature.
				$header = array( 'alg' => "RS256" );
				$headerText = self::base64url_encode( json_encode( $header ) );
				$assertionText = self::base64url_encode( json_encode( $assertion ) );

				// Put the data together.
				$input = $headerText . '.' . $assertionText;

				// Sign the signature.
				$signature = $rsa->sign( $input );
				$signature = self::base64url_encode( $signature );

				// Set our return value to be our signed assertion.
				$assertion = $input . '.' . $signature;

				// Return the include_path to it's original value.
				set_include_path( $old_include_path );
			}
		}

		error_log('-- END API CALL --');
		return $assertion;
	}

	/**
	 * Encoding used for signed assertions.
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
	
	/**
	 * Return the BadgeClass JSON, as per documentation,
	 * https://github.com/openbadges/openbadges-specification/blob/master/Assertion/latest.md
	 */
	public function badge_class() {
		error_log('-- START API CALL --');
		error_log('-> badge_class');
		self::log_headers();
		global $json_api;

		// Retrieve query data.
		$post_id = $json_api->query->uid;

		if ( isset( $post_id ) ) {
			// Get the base url for our API
			$base_url = site_url() . '/' . get_option( 'json_api_base', 'api' );

			// Get the badge usig query data.
			$badge = get_post( $post_id );

			// Define the BadgeClass data that will be returned.
			$class = array(
				"name"        => $badge->post_title,
  				"description" => ( $badge->post_content ) ? html_entity_decode( strip_tags( $badge->post_content ), ENT_QUOTES, 'UTF-8' ) : "",
  				"image"       => wp_get_attachment_url( get_post_thumbnail_id( $post_id )),
  				"criteria"    => get_permalink( $post_id ),
  				"issuer"      => $base_url . '/badge/issuer/'
  			);
			
			// Add any tags our wordpress post might have as tags for the badge.
			$tags = wp_get_post_tags( array( 'fields' => 'names' ) );
			if ( ! empty( $tags ) ) {
				$class['tags'] = $tags;
			}

			error_log('-- END API CALL --');
  			return $class;
		}

		error_log('-- END API CALL --');
	}

	/**
	 * Return the IssuerOrganization JSON, as per documentation,
	 * https://github.com/openbadges/openbadges-specification/blob/master/Assertion/latest.md
	 */
	public function issuer() {
		error_log('-- START API CALL --');
		error_log('-> issuer');
		self::log_headers();

		// List the optional fields for the json.
		$issuerFields = array( 'description', 'image', 'email' );

		// Define the required fields for the json.
		$issuer = array( // These fields are required.
			"name" => BOSOBI_Settings::get( 'org_name' ),
			"url"  => BOSOBI_Settings::get( 'org_url' )
		);
		
		// Loop through the optional fields.
		foreach ( $issuerFields as $field ) {
			$val = BOSOBI_Settings::get( 'org_' . $field );

			// Check if the field is defined.
			if ( ! empty( $val ) ) {
				// If so, add it to our resulting json.
				$issuer[ $field ] = $val;	
			}
		}

		// Check if the assertion is signed.
		if ( BOSOBI_Settings::get( 'assertion_type' ) === 'signed' ) {
			// If so, check if the url for our revocation list is define.
			$val = BOSOBI_Settings::get( 'org_revocationList' );
			if ( ! empty( $val ) ) {
				// If so, add it to our resulting json.
				$issuer['revocationList'] = $val;	
			}
		}
		
		error_log('-- END API CALL --');
		return $issuer;
	}
	
	/**
	 * Returns a HTML for all achievements that have been earned by a given user.
	 * This is used to render the backpack_push shortcode.
	 */
	public function achievements() {
		error_log('-- START API CALL --');
		error_log('-> achievements');
		self::log_headers();
		global $blog_id, $json_api;
		
		// Get the list of BadgeOS post types.
		$type = badgeos_get_achievement_types_slugs();

		// Drop steps from our list of "all" achievements
		$step_key = array_search( 'step', $type );
		if ( $step_key ) {
			unset( $type[ $step_key ] );
		}
		
		$type[] = 'submission';
		$user_id = get_current_user_id();
		
		// Get the current user if one wasn't specified
		if ( ! $user_id ) {
			if ( $json_api->query->user_id ) {
				$user_id = $json_api->query->user_id;
			} else {
				return array( "message" => "No user_id" ); 	
			}
		}

		// Get submissions
		$args = array(
			'post_type'      =>	'submission',
			'posts_per_page' => -1,
			'author'         => $user_id,
			'post_status'    => 'publish',
			'fields'         => 'ids'
		);

		$sub_arg = $args;
		$submissions = get_posts( $args );
		$hidden = badgeos_get_hidden_achievement_ids( $type );
	
		// Initialize our output and counters
		$achievements = array();
		$achievement_count = 0;
		
		// Grab our earned badges (used to filter the query)
		$earned_ids = badgeos_get_user_earned_achievement_ids( $user_id, $type );
		$earned_ids = array_map( 'intval', $earned_ids );

		// Query Achievements
		$args = array(
			'post_type'      =>	$type,
			'posts_per_page' =>	-1,
			'post_status'    => 'publish'
		);

		$args['post__in'] = array_merge( array( 0 ), $earned_ids);
		
		$exclude = array();
		// exclude badges which are submissions
		if ( ! empty( $submissions ) ) {
			foreach ( $submissions as $sub_id ) {
				$exclude[] = absint( get_post_meta( $sub_id, '_badgeos_submission_achievement_id', true ) );
				$args['post__in'][] = $sub_id;
			}

			$args['post__in'] = array_diff( $args['post__in'], $exclude );
		}
		
		// Loop Achievements
		$achievement_posts = new WP_Query( $args );

		$query_count += $achievement_posts->found_posts;
		$base_url = site_url() . '/' . get_option( 'json_api_base', 'api' ) . '/badge/assertion/?uid=';
		$pushed_items = get_user_meta( absint( $user_id ), '_badgeos_backpack_pushed' );
		$pushed_badges = empty( $pushed_items ) ? (array) $pushed_items : array();
		
		while ( $achievement_posts->have_posts() ) {
			$achievement_posts->the_post();
			$achievement_id = get_the_ID();

			if ( ! in_array( $achievement_id , $hidden ) ) {
				$uid = $achievement_id . "-" . get_post_time('U', true) . "-" . $user_id;
				$button_text = ( ! in_array( $base_url . $uid, $pushed_badges ) ) ? __( 'Send to Mozilla Backpack', 'badgeos_obi_issuer' ) : __( 'Resend to Mozilla Backpack', 'badgeos_obi_issuer' ); 
				$download_url = 'http://backpack.openbadges.org/baker?assertion=' . $base_url . $uid . '&bake=1';
				
				ob_start();

				if ( get_post_type() === 'submission' ) {
					echo badgeos_render_achievement( get_post_meta( get_the_ID(), '_badgeos_submission_achievement_id', true ) );
				} else {
					echo badgeos_render_achievement( $achievement_id );
				}

				?>
				<div class="badgeos_backpack_action">
					<a href="<?php echo $download_url; ?>" class="button button-download">Download</a>
					<a href="" class="badgeos_backpack button" data-uid="<?php echo $base_url . $uid; ?>"><?php echo $button_text; ?></a>
					<input type="checkbox" value="<?php echo $base_url . $uid; ?>" name="badgeos_backpack_issues[]" />
				</div>
				<?php

				$badge_html = ob_get_clean();

				$achievements[] = array(
					"uid"  => $base_url . $uid,
					"type" => get_post_type( $achievement_id ),
					"data" => $badge_html
				);
				
				$achievement_count++;
			}
		}
		
		error_log('-- END API CALL --');
		return array(
			"achievements" => $achievements,
			"count"        => $achievement_count
		);
	}
}

?>