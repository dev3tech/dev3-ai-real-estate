<?php
/**
 * Natural-language search: "3 bed house under 500k with a garden in Springfield" → filters.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Ai;

use Dev3\AiRealEstate\Core3\Api\Prompts;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Search\Nl_Parser;
use Dev3\AiRealEstate\Search\Search;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static service.
 */
class Nl_Search {

	/**
	 * Interprets the text (AI with rule-based fallback).
	 *
	 * @param string $text    Visitor text.
	 * @param string $context storefront|admin.
	 * @return array { query (raw args), chips, ai (bool), cached }
	 */
	public static function interpret( $text, $context = 'storefront' ) {
		$text = mb_substr( trim( sanitize_text_field( (string) $text ) ), 0, 200 );
		if ( '' === $text ) {
			return array(
				'query' => array(),
				'chips' => array(),
				'ai'    => false,
			);
		}
		$raw    = null;
		$ai     = false;
		$cached = false;
		if ( Settings::get( 'ai_nl_search', true ) ) {
			$res = Prompts::run(
				Prompts_Library::NL_SEARCH,
				array(
					'query'     => $text,
					'currency'  => (string) Settings::get( 'currency', 'USD' ),
					'unit'      => Property::unit_label(),
					'types'     => self::vocab( Post_Types::TYPE, 60 ),
					'features'  => self::vocab( Post_Types::FEATURE, 120 ),
					'locations' => self::vocab( Post_Types::LOCATION, 200 ),
				),
				$context,
				array( 'cache' => DAY_IN_SECONDS )
			);
			if ( ! is_wp_error( $res ) && is_array( $res['data'] ?? null ) ) {
				$raw    = self::from_ai( $res['data'] );
				$ai     = true;
				$cached = ! empty( $res['cached'] );
			}
		}
		if ( null === $raw ) {
			$raw = Nl_Parser::parse( $text );
		}
		$args = Search::sanitize( $raw );
		return array(
			'query'  => Search::to_query( $args ),
			'chips'  => Search::describe( $args ),
			'ai'     => $ai,
			'cached' => $cached,
		);
	}

	/**
	 * Comma-separated term names.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $cap      Max names.
	 * @return string
	 */
	private static function vocab( $taxonomy, $cap ) {
		$names = wp_list_pluck( Post_Types::terms( $taxonomy ), 'name' );
		return $names ? implode( ', ', array_slice( $names, 0, $cap ) ) : '(none)';
	}

	/**
	 * Validates model output against the vocabulary and maps names to ids.
	 *
	 * @param array $data Model output.
	 * @return array Raw search args.
	 */
	public static function from_ai( array $data ) {
		$map  = static function ( $names, $taxonomy ) {
			$index = array();
			foreach ( Post_Types::terms( $taxonomy ) as $t ) {
				$index[ mb_strtolower( $t['name'] ) ] = $t['id'];
			}
			$ids = array();
			foreach ( (array) $names as $n ) {
				$key = mb_strtolower( trim( is_scalar( $n ) ? (string) $n : '' ) );
				if ( isset( $index[ $key ] ) ) {
					$ids[] = $index[ $key ];
				}
			}
			return array_values( array_unique( $ids ) );
		};
		$num  = static function ( $key ) use ( $data ) {
			return isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) && $data[ $key ] > 0 ? (float) $data[ $key ] : 0;
		};
		$args = array(
			'listing_type' => in_array( $data['listing_type'] ?? '', array( 'sale', 'rent' ), true ) ? $data['listing_type'] : '',
			'type'         => $map( $data['types'] ?? array(), Post_Types::TYPE ),
			'features'     => $map( $data['features'] ?? array(), Post_Types::FEATURE ),
			'location'     => $map( $data['locations'] ?? array(), Post_Types::LOCATION ),
			'price_min'    => $num( 'price_min' ),
			'price_max'    => $num( 'price_max' ),
			'beds'         => (int) $num( 'beds' ),
			'baths'        => $num( 'baths' ),
			'area_min'     => $num( 'area_min' ),
			'area_max'     => $num( 'area_max' ),
			'year_min'     => (int) $num( 'year_min' ),
			'q'            => mb_substr( sanitize_text_field( (string) ( $data['keywords'] ?? '' ) ), 0, 40 ),
			'sort'         => in_array( $data['sort'] ?? '', Search::SORTS, true ) ? $data['sort'] : '',
		);
		// Keywords that only repeat a matched filter would hide results: drop them.
		if ( '' !== $args['q'] && ( $args['type'] || $args['location'] || $args['features'] ) ) {
			$args['q'] = '';
		}
		return array_filter( $args );
	}
}
