<?php
/**
 * Nearby amenities from OpenStreetMap (Overpass API), used to ground neighbourhood highlights.
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
class Amenities {

	const RADIUS = 1000;

	/**
	 * Category => [ label, OSM tag tests ].
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'schools' => __( 'Schools', 'dev3-ai-real-estate' ),
			'transit' => __( 'Public transport', 'dev3-ai-real-estate' ),
			'parks'   => __( 'Parks & playgrounds', 'dev3-ai-real-estate' ),
			'shops'   => __( 'Supermarkets & shops', 'dev3-ai-real-estate' ),
			'health'  => __( 'Healthcare', 'dev3-ai-real-estate' ),
			'food'    => __( 'Restaurants & cafés', 'dev3-ai-real-estate' ),
			'fitness' => __( 'Gyms & sports', 'dev3-ai-real-estate' ),
		);
	}

	/**
	 * Category of an OSM element (or '').
	 *
	 * @param array $tags Tags.
	 * @return string
	 */
	public static function classify( array $tags ) {
		$amenity = $tags['amenity'] ?? '';
		if ( in_array( $amenity, array( 'school', 'kindergarten', 'college', 'university' ), true ) ) {
			return 'schools';
		}
		if ( ( isset( $tags['public_transport'] ) && 'station' === $tags['public_transport'] ) || in_array( $tags['railway'] ?? '', array( 'station', 'halt', 'tram_stop' ), true ) || 'bus_stop' === ( $tags['highway'] ?? '' ) ) {
			return 'transit';
		}
		if ( in_array( $tags['leisure'] ?? '', array( 'park', 'playground', 'garden', 'nature_reserve' ), true ) ) {
			return 'parks';
		}
		if ( in_array( $tags['leisure'] ?? '', array( 'fitness_centre', 'sports_centre', 'swimming_pool' ), true ) ) {
			return 'fitness';
		}
		if ( in_array( $tags['shop'] ?? '', array( 'supermarket', 'convenience', 'mall', 'bakery', 'greengrocer' ), true ) ) {
			return 'shops';
		}
		if ( in_array( $amenity, array( 'hospital', 'clinic', 'pharmacy', 'doctors', 'dentist' ), true ) ) {
			return 'health';
		}
		if ( in_array( $amenity, array( 'restaurant', 'cafe', 'bar', 'pub', 'fast_food' ), true ) ) {
			return 'food';
		}
		return '';
	}

	/**
	 * Overpass QL for the area around a point.
	 *
	 * @param float $lat Lat.
	 * @param float $lng Lng.
	 * @return string
	 */
	public static function query( $lat, $lng ) {
		$a = sprintf( '(around:%d,%.6F,%.6F)', self::RADIUS, $lat, $lng );
		return '[out:json][timeout:20];('
			. 'nwr["amenity"~"^(school|kindergarten|college|university|hospital|clinic|pharmacy|doctors|dentist|restaurant|cafe|bar|pub|fast_food)$"]' . $a . ';'
			. 'nwr["public_transport"="station"]' . $a . ';'
			. 'nwr["railway"~"^(station|halt|tram_stop)$"]' . $a . ';'
			. 'node["highway"="bus_stop"]' . $a . ';'
			. 'nwr["leisure"~"^(park|playground|garden|nature_reserve|fitness_centre|sports_centre|swimming_pool)$"]' . $a . ';'
			. 'nwr["shop"~"^(supermarket|convenience|mall|bakery|greengrocer)$"]' . $a . ';'
			. ');out center tags 400;';
	}

	/**
	 * Summarises Overpass elements around a point.
	 *
	 * @param array $elements Elements.
	 * @param float $lat      Lat.
	 * @param float $lng      Lng.
	 * @return array { category => { label, count, names[], nearest_m } }
	 */
	public static function summarise( array $elements, $lat, $lng ) {
		$labels = self::categories();
		$out    = array();
		foreach ( $elements as $el ) {
			$tags = isset( $el['tags'] ) && is_array( $el['tags'] ) ? $el['tags'] : array();
			$cat  = self::classify( $tags );
			if ( '' === $cat ) {
				continue;
			}
			$elat = isset( $el['lat'] ) ? (float) $el['lat'] : ( isset( $el['center']['lat'] ) ? (float) $el['center']['lat'] : null );
			$elng = isset( $el['lon'] ) ? (float) $el['lon'] : ( isset( $el['center']['lon'] ) ? (float) $el['center']['lon'] : null );
			$dist = null === $elat ? null : (int) round( Geo::distance_km( $lat, $lng, $elat, $elng ) * 1000 );
			if ( ! isset( $out[ $cat ] ) ) {
				$out[ $cat ] = array(
					'label'     => $labels[ $cat ],
					'count'     => 0,
					'places'    => array(),
					'nearest_m' => null,
				);
			}
			++$out[ $cat ]['count'];
			if ( null !== $dist && ( null === $out[ $cat ]['nearest_m'] || $dist < $out[ $cat ]['nearest_m'] ) ) {
				$out[ $cat ]['nearest_m'] = $dist;
			}
			$name = isset( $tags['name'] ) ? mb_substr( sanitize_text_field( (string) $tags['name'] ), 0, 80 ) : '';
			if ( '' !== $name ) {
				$out[ $cat ]['places'][ $name ] = null === $dist ? 99999 : min( $dist, $out[ $cat ]['places'][ $name ] ?? 99999 );
			}
		}
		foreach ( $out as $cat => $row ) {
			asort( $row['places'] );
			$names = array();
			foreach ( array_slice( $row['places'], 0, 3, true ) as $name => $dist ) {
				$names[] = array(
					'name'       => (string) $name,
					'distance_m' => 99999 === $dist ? null : (int) $dist,
				);
			}
			$out[ $cat ]['names'] = $names;
			unset( $out[ $cat ]['places'] );
		}
		$order = array_keys( $labels );
		uksort(
			$out,
			static function ( $a, $b ) use ( $order ) {
				return array_search( $a, $order, true ) - array_search( $b, $order, true );
			}
		);
		return $out;
	}

	/**
	 * Nearby amenities (cached 30 days; failures cached 1 hour).
	 *
	 * @param float $lat Lat.
	 * @param float $lng Lng.
	 * @return array|null
	 */
	public static function near( $lat, $lng ) {
		if ( ! Settings::get( 'amenities_enabled', true ) || ( 0.0 === (float) $lat && 0.0 === (float) $lng ) ) {
			return null;
		}
		$key    = Cache::key( 'amenities', array( round( (float) $lat, 4 ), round( (float) $lng, 4 ) ) );
		$cached = Cache::get( $key );
		if ( is_array( $cached ) ) {
			return isset( $cached['none'] ) ? null : $cached;
		}
		/**
		 * Overpass endpoint.
		 *
		 * @param string $url URL.
		 */
		$url = (string) apply_filters( 'dev3re_overpass_url', 'https://overpass-api.de/api/interpreter' );
		$res = wp_remote_post(
			$url,
			array(
				'timeout'    => 25,
				'user-agent' => 'DEV3RealEstate/' . DEV3RE_VERSION . '; ' . home_url( '/' ),
				'body'       => array( 'data' => self::query( (float) $lat, (float) $lng ) ),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			Cache::set( $key, array( 'none' => 1 ), HOUR_IN_SECONDS, 'amenities' );
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$data = self::summarise( isset( $body['elements'] ) && is_array( $body['elements'] ) ? $body['elements'] : array(), (float) $lat, (float) $lng );
		Cache::set( $key, $data, 30 * DAY_IN_SECONDS, 'amenities' );
		return $data;
	}

	/**
	 * Plain summary without AI ("Within about 1 km: 3 schools (nearest Lincoln Elementary, 350 m) …").
	 *
	 * @param array  $data Amenities.
	 * @param string $area Neighbourhood / city name.
	 * @return array { summary, highlights[] }
	 */
	public static function fallback_text( array $data, $area = '' ) {
		$highlights = array();
		foreach ( $data as $row ) {
			$line = sprintf(
				/* translators: 1: count, 2: category */
				__( '%1$d × %2$s within 1 km', 'dev3-ai-real-estate' ),
				$row['count'],
				mb_strtolower( $row['label'] )
			);
			if ( ! empty( $row['names'][0]['name'] ) ) {
				$near  = $row['names'][0];
				$line .= ' — ' . ( null !== $near['distance_m'] ? sprintf(
					/* translators: 1: place name, 2: metres */
					__( 'nearest: %1$s (%2$d m)', 'dev3-ai-real-estate' ),
					$near['name'],
					$near['distance_m']
				) : $near['name'] );
			}
			$highlights[] = $line;
		}
		$summary = '';
		if ( $highlights ) {
			$summary = '' !== $area
				/* translators: %s: area name */
				? sprintf( __( 'Everyday essentials are close by in %s. This is what OpenStreetMap lists within about 1 km of the property.', 'dev3-ai-real-estate' ), $area )
				: __( 'Everyday essentials are close by. This is what OpenStreetMap lists within about 1 km of the property.', 'dev3-ai-real-estate' );
		}
		return array(
			'summary'    => $summary,
			'highlights' => array_slice( $highlights, 0, 6 ),
		);
	}
}
