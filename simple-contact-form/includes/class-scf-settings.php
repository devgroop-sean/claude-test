<?php
/**
 * Settings access helper.
 *
 * @package SimpleContactForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the plugin's single option array.
 */
class SCF_Settings {

	const OPTION = 'scf_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'recipient'        => get_option( 'admin_email' ),
			'subject_prefix'   => '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ']',
			'success_message'  => __( 'Thanks! Your message has been sent.', 'simple-contact-form' ),
			'show_phone'       => 1,
			'show_subject'     => 1,
			'store_submissions'=> 1,
			'consent_text'     => '',
			'rate_limit'       => 5,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}
}
