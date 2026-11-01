<?php
/**
 * JSON-LD: RealEstateListing on property pages, RealEstateAgent on agent/agency pages.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static schema builder.
 */
class Schema_Markup {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_schema' ), 30 );
	}

	/**
	 * Prints JSON-LD on single pages.
	 */
	public static function print_schema() {
		if ( ! Settings::get( 'schema_enabled', true ) || ! is_singular( array( Post_Types::PROPERTY, Post_Types::AGENT, Post_Types::AGENCY ) ) ) {
			return;
		}
		$id   = (int) get_queried_object_id();
		$data = Post_Types::PROPERTY === get_post_type( $id ) ? self::property( $id ) : self::agent( $id );
		if ( $data ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded.
		}
	}

	/**
	 * Schema.org type of the "about" entity for a property type name.
	 *
	 * @param string $type Type name.
	 * @return string
	 */
	public static function residence_type( $type ) {
		$type = mb_strtolower( (string) $type );
		$map  = array(
			'apartment'  => 'Apartment',
			'flat'       => 'Apartment',
			'condo'      => 'Apartment',
			'studio'     => 'Apartment',
			'house'      => 'SingleFamilyResidence',
			'villa'      => 'SingleFamilyResidence',
			'townhouse'  => 'House',
			'bungalow'   => 'SingleFamilyResidence',
			'land'       => 'Place',
			'plot'       => 'Place',
			'commercial' => 'Place',
			'office'     => 'Place',
		);
		foreach ( $map as $needle => $schema ) {
			if ( false !== mb_strpos( $type, $needle ) ) {
				return $schema;
			}
		}
		return 'Accommodation';
	}

	/**
	 * RealEstateListing.
	 *
	 * @param int $id Property.
	 * @return array|null
	 */
	public static function property( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		$f      = Property::fields( $id );
		$c      = Property::card( $id );
		$images = array();
		foreach ( array_slice( $f['gallery'] ? $f['gallery'] : array( (int) get_post_thumbnail_id( $id ) ), 0, 10 ) as $aid ) {
			$src = $aid ? wp_get_attachment_image_url( $aid, 'large' ) : '';
			if ( $src ) {
				$images[] = $src;
			}
		}
		$about = array(
			'@type' => self::residence_type( $c['type'] ),
			'name'  => get_the_title( $id ),
		);
		$addr  = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $f['hide_address'] ? '' : $f['street'],
				'addressLocality' => $f['city'],
				'addressRegion'   => $f['region'],
				'postalCode'      => $f['hide_address'] ? '' : $f['postcode'],
				'addressCountry'  => $f['country'],
			)
		);
		if ( count( $addr ) > 1 ) {
			$about['address'] = $addr;
		}
		if ( '' !== $f['lat'] && '' !== $f['lng'] && ! $f['hide_address'] ) {
			$about['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $f['lat'],
				'longitude' => (float) $f['lng'],
			);
		}
		if ( 'Place' !== $about['@type'] ) {
			if ( $f['beds'] ) {
				$about['numberOfBedrooms'] = (int) $f['beds'];
				$about['numberOfRooms']    = (int) $f['beds'] + ( $f['baths'] ? (int) ceil( $f['baths'] ) : 0 );
			}
			if ( $f['baths'] ) {
				$about['numberOfBathroomsTotal'] = (float) $f['baths'] + 0;
			}
			if ( $f['year_built'] ) {
				$about['yearBuilt'] = (int) $f['year_built'];
			}
		}
		if ( $c['area_m2'] > 0 ) {
			$sqft               = 'sqft' === Settings::get( 'area_unit', 'sqft' );
			$about['floorSize'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => round( Property::from_m2( $c['area_m2'] ) ),
				'unitCode' => $sqft ? 'FTK' : 'MTK',
				'unitText' => Property::unit_label(),
			);
		}
		if ( $c['features'] ) {
			$about['amenityFeature'] = array();
			foreach ( $c['features'] as $name ) {
				$about['amenityFeature'][] = array(
					'@type' => 'LocationFeatureSpecification',
					'name'  => $name,
					'value' => true,
				);
			}
		}
		$offer = array(
			'@type'            => 'Offer',
			'businessFunction' => 'rent' === $f['listing_type'] ? 'http://purl.org/goodrelations/v1#LeaseOut' : 'http://purl.org/goodrelations/v1#Sell',
			'availability'     => in_array( $f['market'], array( 'sold', 'rented' ), true ) ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
			'url'              => (string) get_permalink( $id ),
		);
		if ( $f['price'] > 0 && ! $f['price_on_request'] ) {
			$offer['price']         = (float) $f['price'];
			$offer['priceCurrency'] = (string) Settings::get( 'currency', 'USD' );
			if ( 'rent' === $f['listing_type'] ) {
				$units                       = array(
					'month' => 'MON',
					'week'  => 'WEE',
					'day'   => 'DAY',
					'year'  => 'ANN',
				);
				$offer['priceSpecification'] = array(
					'@type'         => 'UnitPriceSpecification',
					'price'         => (float) $f['price'],
					'priceCurrency' => (string) Settings::get( 'currency', 'USD' ),
					'unitCode'      => $units[ $f['price_period'] ] ?? 'MON',
				);
			}
		}
		$agent_id = (int) $f['agent_id'];
		if ( $agent_id && Post_Types::AGENT === get_post_type( $agent_id ) ) {
			$offer['offeredBy'] = self::agent_ref( $agent_id );
		}
		$data = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'RealEstateListing',
			'@id'          => (string) get_permalink( $id ) . '#listing',
			'name'         => get_the_title( $id ),
			'url'          => (string) get_permalink( $id ),
			'description'  => wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 60, '…' ) ),
			'datePosted'   => get_the_date( 'c', $id ),
			'dateModified' => get_the_modified_date( 'c', $id ),
			'image'        => $images,
			'offers'       => $offer,
			'about'        => $about,
		);
		if ( $f['reference'] ) {
			$data['identifier'] = $f['reference'];
		}
		$data = array_filter(
			$data,
			static function ( $v ) {
				return '' !== $v && array() !== $v;
			}
		);
		/**
		 * Filters the RealEstateListing JSON-LD.
		 *
		 * @param array $data Data.
		 * @param int   $id   Property.
		 */
		return apply_filters( 'dev3re_schema', $data, $id );
	}

	/**
	 * Small RealEstateAgent reference.
	 *
	 * @param int $agent_id Agent.
	 * @return array
	 */
	private static function agent_ref( $agent_id ) {
		$a   = Agents::get( $agent_id );
		$out = array(
			'@type' => 'RealEstateAgent',
			'name'  => $a['name'],
			'url'   => $a['url'],
		);
		if ( $a['fields']['phone'] ) {
			$out['telephone'] = $a['fields']['phone'];
		}
		return $out;
	}

	/**
	 * RealEstateAgent page (agent or agency).
	 *
	 * @param int $id Post.
	 * @return array|null
	 */
	public static function agent( $id ) {
		$a = Agents::get( $id );
		if ( ! $a || 'publish' !== $a['status'] ) {
			return null;
		}
		$data = array_filter(
			array(
				'@context'  => 'https://schema.org',
				'@type'     => 'RealEstateAgent',
				'name'      => $a['name'],
				'url'       => $a['url'],
				'image'     => $a['photo'] ? (string) wp_get_attachment_image_url( $a['photo'], 'medium' ) : '',
				'telephone' => $a['fields']['phone'] ?? '',
				'email'     => $a['fields']['email'] ?? '',
				'address'   => ! empty( $a['fields']['address'] ) ? $a['fields']['address'] : '',
				'sameAs'    => array_values( array_filter( array( $a['fields']['website'] ?? '', $a['fields']['facebook'] ?? '', $a['fields']['instagram'] ?? '', $a['fields']['linkedin'] ?? '', $a['fields']['x'] ?? '' ) ) ),
			)
		);
		if ( 'agent' === $a['kind'] && ! empty( $a['agency_id'] ) ) {
			$data['parentOrganization'] = array(
				'@type' => 'RealEstateAgent',
				'name'  => $a['agency'],
				'url'   => (string) get_permalink( $a['agency_id'] ),
			);
		}
		return apply_filters( 'dev3re_schema', $data, $id );
	}
}
