<?php
/**
 * Neighbourhood highlights from the address, grounded in OpenStreetMap amenities.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Ai;

use Dev3\AiRealEstate\Core3\Api\Prompts;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Search\Amenities;
use Dev3\AiRealEstate\Search\Geo;

defined( 'ABSPATH' ) || exit;

/**
 * Static service.
 */
class Neighbourhood {

	/**
	 * Builds a draft (nothing saved).
	 *
	 * @param array  $fields  Property fields as edited (street, city, region, postcode, country, lat, lng).
	 * @param string $context admin|submission.
	 * @param bool   $use_ai  False = facts-only text.
	 * @return array|\WP_Error { draft{ summary, highlights }, amenities, lat, lng, ai, model?, tokens? }
	 */
	public static function generate( array $fields, $context = 'admin', $use_ai = true ) {
		$f = array();
		foreach ( array( 'street', 'city', 'region', 'postcode', 'country', 'lat', 'lng' ) as $key ) {
			$f[ $key ] = Property::clean( $key, $fields[ $key ] ?? '' );
		}
		$address = implode( ', ', array_filter( array( $f['street'], $f['city'], $f['region'], $f['postcode'], $f['country'] ) ) );
		if ( '' === $address && '' === $f['lat'] ) {
			return new \WP_Error( 'dev3re_need_address', __( 'Add the address or drop the map pin first.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$lat = $f['lat'];
		$lng = $f['lng'];
		if ( '' === $lat || '' === $lng ) {
			$geo = Geo::geocode( $address );
			if ( $geo ) {
				$lat = $geo['lat'];
				$lng = $geo['lng'];
			}
		}
		$amenities = ( '' !== $lat && '' !== $lng ) ? Amenities::near( (float) $lat, (float) $lng ) : null;
		$amenities = is_array( $amenities ) ? $amenities : array();
		$area      = $f['city'] ? $f['city'] : $f['region'];
		$base      = array(
			'amenities' => $amenities,
			'lat'       => '' === $lat ? null : (float) $lat,
			'lng'       => '' === $lng ? null : (float) $lng,
			'nearby'    => self::nearby_lines( $amenities ),
		);
		if ( ! $use_ai ) {
			$text = Amenities::fallback_text( $amenities, $area );
			if ( '' === $text['summary'] ) {
				return new \WP_Error( 'dev3re_no_amenities', __( 'No nearby places were found on OpenStreetMap for this location. Write the highlights yourself or try DEV3 AI.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) );
			}
			return array_merge(
				$base,
				array(
					'draft' => array(
						'summary'    => $text['summary'],
						'highlights' => implode( "\n", $text['highlights'] ),
					),
					'ai'    => false,
				)
			);
		}
		$res = Prompts::run(
			Prompts_Library::NEIGHBOURHOOD,
			array(
				'address'   => mb_substr( $address, 0, 300 ),
				'area'      => $area ? $area : 'unknown',
				'amenities' => self::amenity_text( $amenities ),
				'language'  => Prompts_Library::language(),
			),
			$context,
			array( 'cache' => WEEK_IN_SECONDS )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = is_array( $res['data'] ?? null ) ? $res['data'] : array();
		return array_merge(
			$base,
			array(
				'draft'  => array(
					'summary'    => mb_substr( sanitize_textarea_field( (string) ( $data['summary'] ?? '' ) ), 0, 600 ),
					'highlights' => implode( "\n", Property::clean( 'highlights', (array) ( $data['highlights'] ?? array() ) ) ),
				),
				'ai'     => true,
				'model'  => $res['model'],
				'tokens' => (int) ( $res['usage']['total'] ?? 0 ),
				'cached' => ! empty( $res['cached'] ),
			)
		);
	}

	/**
	 * Amenities as prompt text.
	 *
	 * @param array $amenities Amenities.
	 * @return string
	 */
	public static function amenity_text( array $amenities ) {
		if ( ! $amenities ) {
			return '(no data)';
		}
		$lines = array();
		foreach ( $amenities as $row ) {
			$names = array();
			foreach ( $row['names'] as $n ) {
				$names[] = $n['name'] . ( null !== $n['distance_m'] ? ' (' . $n['distance_m'] . ' m)' : '' );
			}
			$lines[] = $row['label'] . ': ' . $row['count'] . ( $names ? '; ' . implode( ', ', $names ) : '' );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Short "Category · nearest" lines stored with the property (shown as facts, not AI).
	 *
	 * @param array $amenities Amenities.
	 * @return string[]
	 */
	public static function nearby_lines( array $amenities ) {
		$out = array();
		foreach ( $amenities as $row ) {
			$n = $row['names'][0] ?? null;
			if ( $n && null !== $n['distance_m'] ) {
				$out[] = $row['label'] . ' · ' . $n['name'] . ' · ' . $n['distance_m'] . ' m';
			} elseif ( null !== $row['nearest_m'] ) {
				$out[] = $row['label'] . ' · ' . $row['nearest_m'] . ' m';
			}
		}
		return array_slice( $out, 0, 8 );
	}
}
