<?php
/**
 * Rule-based plain-language search parser (works without AI, and as the AI fallback).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Search;

use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static parser.
 */
class Nl_Parser {

	/**
	 * Words that point at a term name (phrase => fragment of the term name).
	 *
	 * @var array
	 */
	const SYNONYMS = array(
		'pool'       => 'pool',
		'swimming'   => 'pool',
		'parking'    => 'garage',
		'garage'     => 'garage',
		'air con'    => 'air conditioning',
		'aircon'     => 'air conditioning',
		'a/c'        => 'air conditioning',
		'ac'         => 'air conditioning',
		'lift'       => 'elevator',
		'elevator'   => 'elevator',
		'pets'       => 'pet',
		'pet'        => 'pet',
		'dog'        => 'pet',
		'yard'       => 'garden',
		'garden'     => 'garden',
		'balcony'    => 'balcony',
		'terrace'    => 'terrace',
		'furnished'  => 'furnished',
		'sea view'   => 'sea view',
		'ocean view' => 'sea view',
		'fireplace'  => 'fireplace',
		'gym'        => 'gym',
		'solar'      => 'solar',
		'wheelchair' => 'wheelchair',
		'accessible' => 'wheelchair',
		'flat'       => 'apartment',
		'flats'      => 'apartment',
		'apartment'  => 'apartment',
		'apartments' => 'apartment',
		'condo'      => 'condo',
		'condos'     => 'condo',
		'house'      => 'house',
		'houses'     => 'house',
		'home'       => 'house',
		'homes'      => 'house',
		'townhouse'  => 'townhouse',
		'townhome'   => 'townhouse',
		'villa'      => 'villa',
		'villas'     => 'villa',
		'land'       => 'land',
		'plot'       => 'land',
		'office'     => 'office',
		'offices'    => 'office',
		'commercial' => 'commercial',
		'shop'       => 'commercial',
		'retail'     => 'commercial',
	);

	/**
	 * Parses text into raw search args (feed to Search::sanitize).
	 *
	 * @param string $text Visitor text.
	 * @return array
	 */
	public static function parse( $text ) {
		$text = mb_strtolower( trim( (string) $text ) );
		$args = array();
		if ( '' === $text ) {
			return $args;
		}
		$used = array();

		// Sale / rent.
		if ( preg_match( '/\b(for rent|to rent|rent|rental|rentals|renting|to let|lease|per month|a month|\/mo|monthly)\b/u', $text, $m ) ) {
			$args['listing_type'] = 'rent';
			$used[]               = $m[0];
		} elseif ( preg_match( '/\b(for sale|to buy|buy|buying|purchase)\b/u', $text, $m ) ) {
			$args['listing_type'] = 'sale';
			$used[]               = $m[0];
		}

		// Bedrooms / bathrooms.
		if ( preg_match( '/(\d{1,2})\s*\+?\s*(?:-|\s)?(?:bed|beds|bedroom|bedrooms|br|bd|bdr)\b/u', $text, $m ) ) {
			$args['beds'] = (int) $m[1];
			$used[]       = $m[0];
		} elseif ( preg_match( '/\b(one|two|three|four|five|six)\s*(?:-|\s)?(?:bed|beds|bedroom|bedrooms)\b/u', $text, $m ) ) {
			$words        = array( 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6 ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			$args['beds'] = $words[ $m[1] ];
			$used[]       = $m[0];
		} elseif ( preg_match( '/\bstudio\b/u', $text, $m ) ) {
			$used[] = $m[0];
		}
		if ( preg_match( '/(\d{1,2}(?:\.5)?)\s*\+?\s*(?:-|\s)?(?:bath|baths|bathroom|bathrooms|ba)\b/u', $text, $m ) ) {
			$args['baths'] = (float) $m[1];
			$used[]        = $m[0];
		}

		// Living area, e.g. 120 m2 or 1,500 sq ft.
		if ( preg_match( '/(\d[\d,.]*)\s*(sq\.?\s*ft|sqft|ft2|ft²|square feet|m2|m²|sqm|sq\.?\s*m|square met(?:er|re)s?)/u', $text, $m ) ) {
			$value            = (float) str_replace( ',', '', $m[1] );
			$is_ft            = (bool) preg_match( '/ft|feet/u', $m[2] );
			$m2               = $is_ft ? $value / Property::SQFT_PER_SQM : $value;
			$args['area_min'] = round( Property::from_m2( $m2 ) );
			$used[]           = $m[0];
		}

		// Year built.
		if ( preg_match( '/\b(?:built|newer than)\s*(?:after|since|in)?\s*((?:19|20)\d{2})\b/u', $text, $m ) ) {
			$args['year_min'] = (int) $m[1];
			$used[]           = $m[0];
		} elseif ( preg_match( '/\b(new build|new-build|newly built|new construction)\b/u', $text, $m ) ) {
			$args['year_min'] = (int) gmdate( 'Y' ) - 5;
			$used[]           = $m[0];
		}

		// Price.
		$amount = '((?:[$€£¥₹]\s*)?\d[\d,.]*\s*(?:k|m|mil|million|thousand)?)';
		if ( preg_match( '/\bbetween\s+' . $amount . '\s+(?:and|-|to)\s+' . $amount . '/u', $text, $m ) || preg_match( '/' . $amount . '\s*(?:-|to)\s*' . $amount . '(?!\s*(?:bed|bath|sq|m2|m²|ft))/u', $text, $m ) ) {
			$a = self::amount( $m[1] );
			$b = self::amount( $m[2] );
			if ( $a && $b && max( $a, $b ) >= 100 ) {
				$args['price_min'] = min( $a, $b );
				$args['price_max'] = max( $a, $b );
				$used[]            = $m[0];
			}
		}
		if ( empty( $args['price_max'] ) && preg_match( '/\b(?:under|below|less than|max(?:imum)?|up to|no more than|budget(?: of)?|within|<)\s*' . $amount . '/u', $text, $m ) ) {
			$args['price_max'] = self::amount( $m[1] );
			$used[]            = $m[0];
		}
		if ( empty( $args['price_min'] ) && preg_match( '/\b(?:over|above|more than|at least|min(?:imum)?|from|>)\s*' . $amount . '/u', $text, $m ) ) {
			$value = self::amount( $m[1] );
			if ( $value >= 100 ) {
				$args['price_min'] = $value;
				$used[]            = $m[0];
			}
		}

		// Sorting.
		if ( preg_match( '/\b(cheapest|lowest price|least expensive)\b/u', $text, $m ) ) {
			$args['sort'] = 'price_asc';
			$used[]       = $m[0];
		} elseif ( preg_match( '/\b(biggest|largest|most space)\b/u', $text, $m ) ) {
			$args['sort'] = 'area_desc';
			$used[]       = $m[0];
		}

		// Terms: types, features, locations.
		$args['type']     = self::match_terms( $text, Post_Types::TYPE, $used );
		$args['features'] = self::match_terms( $text, Post_Types::FEATURE, $used );
		$args['location'] = self::match_terms( $text, Post_Types::LOCATION, $used, false );

		$args = array_filter( $args );
		if ( ! $args ) {
			$args['q'] = self::leftover( $text, $used );
		}
		return array_filter( $args );
	}

	/**
	 * Parses an amount ("$450k", "1.2m", "2,000").
	 *
	 * @param string $raw Raw.
	 * @return float
	 */
	public static function amount( $raw ) {
		$raw = mb_strtolower( trim( (string) $raw ) );
		$mul = 1;
		if ( preg_match( '/(m|mil|million)$/u', $raw ) ) {
			$mul = 1000000;
		} elseif ( preg_match( '/(k|thousand)$/u', $raw ) ) {
			$mul = 1000;
		}
		$num = preg_replace( '/[^\d.,]/u', '', $raw );
		$sep = (string) Settings::get( 'thousands_sep', ',' );
		if ( '.' === $sep && 1 === $mul ) {
			$num = str_replace( '.', '', $num );
			$num = str_replace( ',', '.', $num );
		} else {
			$num = str_replace( ',', '', $num );
		}
		return is_numeric( $num ) ? round( (float) $num * $mul, 2 ) : 0.0;
	}

	/**
	 * Term ids whose names (or synonyms) appear in the text.
	 *
	 * @param string $text     Text.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $used     Matched phrases (appended).
	 * @param bool   $synonyms Use the synonym table.
	 * @return int[]
	 */
	private static function match_terms( $text, $taxonomy, array &$used, $synonyms = true ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}
		$ids = array();
		foreach ( $terms as $term ) {
			$name = mb_strtolower( $term->name );
			$alts = array_unique( array( $name, rtrim( $name, 's' ), $name . 's' ) );
			foreach ( $alts as $alt ) {
				if ( mb_strlen( $alt ) > 2 && preg_match( '/\b' . preg_quote( $alt, '/' ) . '\b/u', $text ) ) {
					$ids[]  = (int) $term->term_id;
					$used[] = $alt;
					break;
				}
			}
		}
		if ( $synonyms ) {
			foreach ( self::SYNONYMS as $phrase => $fragment ) {
				if ( ! preg_match( '/(?<![\w\/])' . preg_quote( $phrase, '/' ) . '(?![\w\/])/u', $text ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( false !== mb_strpos( mb_strtolower( $term->name ), $fragment ) && ! in_array( (int) $term->term_id, $ids, true ) ) {
						$ids[]  = (int) $term->term_id;
						$used[] = $phrase;
					}
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Words left over after the recognised phrases (used as a keyword search).
	 *
	 * @param string $text Text.
	 * @param array  $used Phrases.
	 * @return string
	 */
	private static function leftover( $text, array $used ) {
		foreach ( $used as $phrase ) {
			$text = str_replace( $phrase, ' ', $text );
		}
		$stop  = array( 'a', 'an', 'the', 'in', 'on', 'at', 'with', 'and', 'or', 'for', 'near', 'to', 'of', 'i', 'want', 'looking', 'need', 'me', 'my', 'show', 'find', 'some', 'any', 'that', 'has', 'have', 'is', 'are', 'property', 'properties' );
		$words = array_filter(
			preg_split( '/[^\p{L}\p{N}]+/u', $text ),
			static function ( $w ) use ( $stop ) {
				return mb_strlen( $w ) > 1 && ! in_array( $w, $stop, true );
			}
		);
		return implode( ' ', array_slice( $words, 0, 6 ) );
	}
}
