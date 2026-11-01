<?php
/**
 * Plugin-specific settings (shared AI settings live in Core3\Core\Settings_Store).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate;

use Dev3\AiRealEstate\Core3\Core\Settings_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Hooked into dev3re_settings_defaults / dev3re_settings_sanitize.
 */
class Settings {

	/**
	 * Boolean keys.
	 *
	 * @var string[]
	 */
	const FLAGS = array(
		'submission_enabled',
		'agent_signup',
		'hide_sold',
		'favourites_enabled',
		'compare_enabled',
		'saved_searches_enabled',
		'mortgage_enabled',
		'schema_enabled',
		'geocoding_enabled',
		'amenities_enabled',
		'leads_autoreply',
		'leads_consent',
		'email_admin_leads',
		'email_admin_pending',
		'ai_describe',
		'ai_neighbourhood',
		'ai_nl_search',
		'delete_on_uninstall',
	);

	/**
	 * Page keys.
	 *
	 * @var string[]
	 */
	const PAGES = array( 'search', 'submit', 'dashboard', 'favourites', 'compare' );

	/**
	 * Adds plugin defaults. No translated strings here: this runs before init.
	 *
	 * @param array $defaults Shared defaults.
	 * @return array
	 */
	public static function defaults( $defaults ) {
		return array_merge(
			$defaults,
			array(
				'currency'               => 'USD',
				'currency_symbol'        => '$',
				'currency_position'      => 'before',
				'thousands_sep'          => ',',
				'area_unit'              => 'sqft',
				'per_page'               => 12,
				'map_provider'           => 'osm',
				'google_maps_key'        => '',
				'map_center_lat'         => 39.7817,
				'map_center_lng'         => -89.6501,
				'map_zoom'               => 12,
				'accent_color'           => '#0f766e',
				'page_search'            => 0,
				'page_submit'            => 0,
				'page_dashboard'         => 0,
				'page_favourites'        => 0,
				'page_compare'           => 0,
				'submission_enabled'     => true,
				'submission_moderation'  => 'pending',
				'agent_signup'           => false,
				'hide_sold'              => true,
				'favourites_enabled'     => true,
				'compare_enabled'        => true,
				'saved_searches_enabled' => true,
				'mortgage_enabled'       => true,
				'mortgage_rate'          => 6.5,
				'mortgage_term'          => 30,
				'mortgage_down'          => 20,
				'schema_enabled'         => true,
				'geocoding_enabled'      => true,
				'amenities_enabled'      => true,
				'leads_autoreply'        => true,
				'leads_consent'          => true,
				'email_admin_leads'      => true,
				'email_admin_pending'    => true,
				'notify_email'           => '',
				'ai_describe'            => true,
				'ai_neighbourhood'       => true,
				'ai_nl_search'           => true,
				'ai_tone'                => 'warm',
				'delete_on_uninstall'    => false,
			)
		);
	}

	/**
	 * Sanitizes plugin keys.
	 *
	 * @param array $out     Sanitized so far.
	 * @param array $input   Raw input.
	 * @param array $current Current.
	 * @return array
	 */
	public static function sanitize( $out, $input, $current ) {
		unset( $current );
		foreach ( self::FLAGS as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$out[ $flag ] = (bool) $input[ $flag ];
			}
		}
		$choices = array(
			'area_unit'             => array( 'sqft', 'sqm' ),
			'map_provider'          => array( 'osm', 'google' ),
			'currency_position'     => array( 'before', 'after', 'before_space', 'after_space' ),
			'submission_moderation' => array( 'pending', 'publish' ),
			'ai_tone'               => array( 'warm', 'professional', 'luxury', 'concise' ),
		);
		foreach ( $choices as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$out[ $key ] = $input[ $key ];
			}
		}
		if ( isset( $input['currency'] ) ) {
			$cur = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $input['currency'] ) );
			if ( 3 === strlen( $cur ) ) {
				$out['currency'] = $cur;
			}
		}
		if ( isset( $input['currency_symbol'] ) ) {
			$out['currency_symbol'] = mb_substr( sanitize_text_field( (string) $input['currency_symbol'] ), 0, 5 );
		}
		if ( isset( $input['thousands_sep'] ) && in_array( $input['thousands_sep'], array( ',', '.', ' ', "'" ), true ) ) {
			$out['thousands_sep'] = $input['thousands_sep'];
		}
		if ( isset( $input['google_maps_key'] ) ) {
			$out['google_maps_key'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input['google_maps_key'] );
		}
		if ( isset( $input['notify_email'] ) ) {
			$email               = sanitize_email( (string) $input['notify_email'] );
			$out['notify_email'] = is_email( $email ) ? $email : '';
		}
		if ( isset( $input['accent_color'] ) ) {
			$color = sanitize_hex_color( (string) $input['accent_color'] );
			if ( $color ) {
				$out['accent_color'] = $color;
			}
		}
		$numbers = array(
			'per_page'       => array( 1, 60, 'int' ),
			'map_zoom'       => array( 1, 19, 'int' ),
			'map_center_lat' => array( -90, 90, 'float' ),
			'map_center_lng' => array( -180, 180, 'float' ),
			'mortgage_rate'  => array( 0, 30, 'float' ),
			'mortgage_term'  => array( 1, 50, 'int' ),
			'mortgage_down'  => array( 0, 100, 'float' ),
		);
		foreach ( $numbers as $key => $range ) {
			if ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$value       = 'int' === $range[2] ? (int) $input[ $key ] : (float) $input[ $key ];
				$out[ $key ] = min( $range[1], max( $range[0], $value ) );
			}
		}
		foreach ( self::PAGES as $page ) {
			if ( isset( $input[ 'page_' . $page ] ) ) {
				$out[ 'page_' . $page ] = max( 0, (int) $input[ 'page_' . $page ] );
			}
		}
		return $out;
	}

	/**
	 * Reads one setting.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		return Settings_Store::get( $key, $fallback );
	}

	/**
	 * URL of one of the plugin pages (falls back to the home page).
	 *
	 * @param string $key search|submit|dashboard|favourites|compare.
	 * @return string
	 */
	public static function page_url( $key ) {
		$id = (int) self::get( 'page_' . $key, 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		return 'search' === $key ? (string) get_post_type_archive_link( Data\Post_Types::PROPERTY ) : home_url( '/' );
	}

	/**
	 * Address that receives admin notifications.
	 *
	 * @return string
	 */
	public static function notify_email() {
		$email = (string) self::get( 'notify_email', '' );
		return $email ? $email : (string) get_option( 'admin_email' );
	}
}
