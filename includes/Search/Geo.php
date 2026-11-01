<?php
/**
 * Distance maths and server-side geocoding (OpenStreetMap Nominatim or Google Geocoding).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Search;

use Dev3\AiRealEstate\Core3\Core\Cache;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers.
 */
class Geo {

	const EARTH_KM = 6371.0;

	/**
	 * Great-circle distance in km.
	 *
	 * @param float $lat1 Lat 1.
	 * @param float $lng1 Lng 1.
	 * @param float $lat2 Lat 2.
	 * @param float $lng2 Lng 2.
	 * @return float
	 */
	public static function distance_km( $lat1, $lng1, $lat2, $lng2 ) {
		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );
		$a     = sin( $d_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) ** 2;
		return self::EARTH_KM * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}

	/**
	 * Bounding box around a point.
	 *
	 * @param float $lat       Lat.
	 * @param float $lng       Lng.
	 * @param float $radius_km Radius.
	 * @return array [ min_lat, max_lat, min_lng, max_lng ]
	 */
	public static function bbox( $lat, $lng, $radius_km ) {
		$d_lat = rad2deg( $radius_km / self::EARTH_KM );
		$cos   = max( 0.00001, cos( deg2rad( $lat ) ) );
		$d_lng = rad2deg( $radius_km / self::EARTH_KM / $cos );
		return array( $lat - $d_lat, $lat + $d_lat, $lng - $d_lng, $lng + $d_lng );
	}

	/**
	 * Geocodes free text. Results are cached for 30 days; failures for an hour.
	 *
	 * @param string $query Address or place.
	 * @param array  $bias  Optional [ lat, lng ] to prefer nearby results.
	 * @return array|null { lat, lng, label }
	 */
	public static function geocode( $query, array $bias = array() ) {
		$query = trim( preg_replace( '/\s+/', ' ', (string) $query ) );
		if ( '' === $query || ! Settings::get( 'geocoding_enabled', true ) ) {
			return null;
		}
		/**
		 * Short-circuits geocoding (return [ lat, lng, label ] or false to skip).
		 *
		 * @param null|array|false $pre   Result.
		 * @param string           $query Query.
		 */
		$pre = apply_filters( 'dev3re_pre_geocode', null, $query );
		if ( null !== $pre ) {
			return is_array( $pre ) ? $pre : null;
		}
		$key    = Cache::key( 'geocode', array( strtolower( $query ), $bias ? array( round( $bias[0], 1 ), round( $bias[1], 1 ) ) : array() ) );
		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return empty( $cached['lat'] ) && empty( $cached['lng'] ) ? null : $cached;
		}
		$google = 'google' === Settings::get( 'map_provider' ) ? (string) Settings::get( 'google_maps_key', '' ) : '';
		$result = $google ? self::google( $query, $google, $bias ) : self::nominatim( $query, $bias );
		Cache::set( $key, $result ? $result : array(), $result ? 30 * DAY_IN_SECONDS : HOUR_IN_SECONDS, 'geocode' );
		return $result;
	}

	/**
	 * Nominatim (OSM). Respect its usage policy: identify the site, cache, low volume.
	 *
	 * @param string $query Query.
	 * @param array  $bias  Bias point.
	 * @return array|null
	 */
	private static function nominatim( $query, array $bias ) {
		$args = array(
			'q'      => $query,
			'format' => 'jsonv2',
			'limit'  => 1,
		);
		if ( $bias ) {
			list( $min_lat, $max_lat, $min_lng, $max_lng ) = self::bbox( $bias[0], $bias[1], 50 );
			$args['viewbox']                               = implode( ',', array( $min_lng, $max_lat, $max_lng, $min_lat ) );
		}
		$res = wp_remote_get(
			add_query_arg( $args, 'https://nominatim.openstreetmap.org/search' ),
			array(
				'timeout'    => 8,
				'user-agent' => 'DEV3RealEstate/' . DEV3RE_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$rows = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $rows[0]['lat'] ) ) {
			return null;
		}
		return array(
			'lat'   => (float) $rows[0]['lat'],
			'lng'   => (float) $rows[0]['lon'],
			'label' => isset( $rows[0]['display_name'] ) ? sanitize_text_field( (string) $rows[0]['display_name'] ) : $query,
		);
	}

	/**
	 * Google Geocoding API.
	 *
	 * @param string $query Query.
	 * @param string $key   Maps key.
	 * @param array  $bias  Bias.
	 * @return array|null
	 */
	private static function google( $query, $key, array $bias ) {
		$args = array(
			'address' => $query,
			'key'     => $key,
		);
		if ( $bias ) {
			list( $min_lat, $max_lat, $min_lng, $max_lng ) = self::bbox( $bias[0], $bias[1], 50 );
			$args['bounds']                                = "{$min_lat},{$min_lng}|{$max_lat},{$max_lng}";
		}
		$res = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $args ), 'https://maps.googleapis.com/maps/api/geocode/json' ), array( 'timeout' => 8 ) );
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['results'][0]['geometry']['location'] ) ) {
			return null;
		}
		$loc = $body['results'][0]['geometry']['location'];
		return array(
			'lat'   => (float) $loc['lat'],
			'lng'   => (float) $loc['lng'],
			'label' => sanitize_text_field( (string) $body['results'][0]['formatted_address'] ),
		);
	}
}
