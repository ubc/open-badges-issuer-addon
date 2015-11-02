<?php

/**
 * This file makes it so that the badge image can be defined using a url instead of an upload.
 *
 WARNING: This file is not complete. It doesn't really do much, and it currently isn't being included by the plugin.
 */
class BOSOBI_Image_URL {

	public static function init() {
		add_filter( 'admin_post_thumbnail_html', array( __CLASS__, 'filter_thumbnail_metabox' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'post_thumbnail_html' ), 10, 3 );
	}

	/**
	 * Adds our url input box to the image metabox
	 * @filter admin_post_thumbnail_html
	 */
	public static function filter_thumbnail_metabox( $content, $post_id ) {
		// Only add a link to achievement and achievement-type posts
		if ( badgeos_is_achievement( $post_id ) || 'achievement-type' == get_post_type( $post_id ) ) {
			ob_start();
			?>
			<input type="url" placeholder="Use an image url."></input>
			<small>Note: this image will not be stored locally, if the website hosting this image removes it then the link will be broken.</small>
			<?php
			$content .= ob_get_clean();
		}

		// Return the meta box content
		return $content;
	}

	public static function has_post_thumbnail() {

	}

}

add_action( 'init', array( 'BOSOBI_Image_URL', 'init' ) );
