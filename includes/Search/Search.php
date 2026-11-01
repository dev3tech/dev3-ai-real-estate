<?php
/**
 * Property search over the index table.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Search;

use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Data\Tables;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- index table; every value is prepared or cast.

/**
 * Static search.
 */
class Search {

	const SORTS = array( 'newest', 'price_asc', 'price_desc', 'area_desc', 'featured' );

	/**
	 * Normalises raw query args (URL params, REST, saved searches, NL output).
	 *
	 * @param array $raw Raw.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$num   = static function ( $key ) use ( $raw ) {
			if ( ! isset( $raw[ $key ] ) || '' === $raw[ $key ] || ! is_numeric( preg_replace( '/[^0-9.]/', '', (string) $raw[ $key ] ) ) ) {
				return 0;
			}
			return max( 0, (float) preg_replace( '/[^0-9.]/', '', (string) $raw[ $key ] ) );
		};
		$terms = static function ( $value, $tax ) {
			$out = array();
			foreach ( is_array( $value ) ? $value : explode( ',', (string) $value ) as $v ) {
				$v = trim( (string) $v );
				if ( '' === $v ) {
					continue;
				}
				$term = is_numeric( $v ) ? get_term( (int) $v, $tax ) : get_term_by( 'slug', sanitize_title( $v ), $tax );
				if ( $term && ! is_wp_error( $term ) ) {
					$out[] = (int) $term->term_id;
				}
			}
			return array_values( array_unique( $out ) );
		};
		$args  = array(
			'listing_type' => isset( $raw['listing_type'] ) && in_array( $raw['listing_type'], array( 'sale', 'rent' ), true ) ? $raw['listing_type'] : '',
			'type'         => isset( $raw['type'] ) ? $terms( $raw['type'], Post_Types::TYPE ) : array(),
			'location'     => isset( $raw['location'] ) ? $terms( $raw['location'], Post_Types::LOCATION ) : array(),
			'features'     => isset( $raw['features'] ) ? $terms( $raw['features'], Post_Types::FEATURE ) : array(),
			'price_min'    => $num( 'price_min' ),
			'price_max'    => $num( 'price_max' ),
			'beds'         => (int) min( 20, $num( 'beds' ) ),
			'baths'        => (float) min( 20, $num( 'baths' ) ),
			'area_min'     => $num( 'area_min' ),
			'area_max'     => $num( 'area_max' ),
			'year_min'     => (int) min( 2100, $num( 'year_min' ) ),
			'q'            => isset( $raw['q'] ) ? mb_substr( sanitize_text_field( (string) $raw['q'] ), 0, 120 ) : '',
			'agent'        => isset( $raw['agent'] ) ? max( 0, (int) $raw['agent'] ) : 0,
			'featured'     => ! empty( $raw['featured'] ) && 'no' !== $raw['featured'],
			'sort'         => isset( $raw['sort'] ) && in_array( $raw['sort'], self::SORTS, true ) ? $raw['sort'] : 'newest',
			'page'         => isset( $raw['page'] ) ? max( 1, (int) $raw['page'] ) : 1,
			'per_page'     => isset( $raw['per_page'] ) ? min( 60, max( 1, (int) $raw['per_page'] ) ) : (int) Settings::get( 'per_page', 12 ),
			'bbox'         => array(),
			'near'         => array(),
			'include_sold' => ! empty( $raw['include_sold'] ),
		);
		if ( ! empty( $raw['bbox'] ) ) {
			$box = array_map( 'floatval', explode( ',', (string) $raw['bbox'] ) );
			if ( 4 === count( $box ) && $box[0] < $box[2] && abs( $box[0] ) <= 90 && abs( $box[2] ) <= 90 ) {
				$args['bbox'] = $box; // south, west, north, east.
			}
		}
		if ( isset( $raw['lat'], $raw['lng'] ) && is_numeric( $raw['lat'] ) && is_numeric( $raw['lng'] ) ) {
			$args['near'] = array(
				'lat'    => (float) $raw['lat'],
				'lng'    => (float) $raw['lng'],
				'radius' => isset( $raw['radius'] ) ? min( 500, max( 0.5, (float) $raw['radius'] ) ) : 10,
			);
		}
		if ( $args['price_max'] && $args['price_min'] > $args['price_max'] ) {
			list( $args['price_min'], $args['price_max'] ) = array( $args['price_max'], $args['price_min'] );
		}
		if ( ! empty( $raw['ids'] ) ) {
			$args['ids'] = array_slice( array_values( array_filter( array_map( 'intval', is_array( $raw['ids'] ) ? $raw['ids'] : explode( ',', (string) $raw['ids'] ) ) ) ), 0, 60 );
		}
		return $args;
	}

	/**
	 * Runs a search (published listings only).
	 *
	 * @param array $args Sanitized args.
	 * @return array { ids, total, page, pages, per_page }
	 */
	public static function run( array $args ) {
		global $wpdb;
		$table = Tables::name( 'index' );
		$where = array( "status = 'publish'" );
		if ( $args['listing_type'] ) {
			$where[] = $wpdb->prepare( 'listing_type = %s', $args['listing_type'] );
		}
		if ( ! $args['include_sold'] && Settings::get( 'hide_sold', true ) && empty( $args['ids'] ) && ! $args['agent'] ) {
			$where[] = "market NOT IN ('sold','rented')";
		}
		foreach ( array(
			'type'     => 'type_ids',
			'location' => 'location_ids',
		) as $arg => $col ) {
			if ( $args[ $arg ] ) {
				$or = array();
				foreach ( $args[ $arg ] as $tid ) {
					$or[] = $wpdb->prepare( "{$col} LIKE %s", '%,' . (int) $tid . ',%' );
				}
				$where[] = '(' . implode( ' OR ', $or ) . ')';
			}
		}
		foreach ( $args['features'] as $tid ) {
			$where[] = $wpdb->prepare( 'feature_ids LIKE %s', '%,' . (int) $tid . ',%' );
		}
		if ( $args['price_min'] ) {
			$where[] = $wpdb->prepare( 'price >= %f', $args['price_min'] );
		}
		if ( $args['price_max'] ) {
			$where[] = $wpdb->prepare( 'price <= %f AND price > 0', $args['price_max'] );
		}
		if ( $args['beds'] ) {
			$where[] = $wpdb->prepare( 'beds >= %d', $args['beds'] );
		}
		if ( $args['baths'] ) {
			$where[] = $wpdb->prepare( 'baths >= %f', $args['baths'] );
		}
		if ( $args['area_min'] ) {
			$where[] = $wpdb->prepare( 'area_m2 >= %f', Property::area_m2( $args['area_min'] ) - 0.5 );
		}
		if ( $args['area_max'] ) {
			$where[] = $wpdb->prepare( 'area_m2 <= %f', Property::area_m2( $args['area_max'] ) + 0.5 );
		}
		if ( $args['year_min'] ) {
			$where[] = $wpdb->prepare( 'year_built >= %d', $args['year_min'] );
		}
		if ( $args['agent'] ) {
			$where[] = $wpdb->prepare( 'agent_id = %d', $args['agent'] );
		}
		if ( $args['featured'] ) {
			$where[] = 'featured = 1';
		}
		if ( ! empty( $args['after_id'] ) ) {
			$where[] = $wpdb->prepare( 'post_id > %d', $args['after_id'] );
		}
		if ( ! empty( $args['ids'] ) ) {
			$where[] = 'post_id IN (' . implode( ',', array_map( 'intval', $args['ids'] ) ) . ')';
		}
		if ( '' !== $args['q'] ) {
			foreach ( array_slice( preg_split( '/\s+/', mb_strtolower( $args['q'] ) ), 0, 6 ) as $word ) {
				if ( mb_strlen( $word ) > 1 ) {
					$where[] = $wpdb->prepare( 'search_text LIKE %s', '%' . $wpdb->esc_like( $word ) . '%' );
				}
			}
		}
		if ( $args['bbox'] ) {
			list( $s, $w, $n, $e ) = $args['bbox'];
			$where[]               = $wpdb->prepare( 'lat BETWEEN %f AND %f', $s, $n );
			$where[]               = $w <= $e ? $wpdb->prepare( 'lng BETWEEN %f AND %f', $w, $e ) : $wpdb->prepare( '(lng >= %f OR lng <= %f)', $w, $e );
		}
		$distance = '';
		if ( $args['near'] ) {
			list( $min_lat, $max_lat, $min_lng, $max_lng ) = Geo::bbox( $args['near']['lat'], $args['near']['lng'], $args['near']['radius'] );
			$where[]                                       = $wpdb->prepare( 'lat BETWEEN %f AND %f AND lng BETWEEN %f AND %f', $min_lat, $max_lat, $min_lng, $max_lng );
			$distance                                      = $wpdb->prepare( ', ( 6371 * ACOS( LEAST( 1, COS( RADIANS( %f ) ) * COS( RADIANS( lat ) ) * COS( RADIANS( lng ) - RADIANS( %f ) ) + SIN( RADIANS( %f ) ) * SIN( RADIANS( lat ) ) ) ) ) AS distance', $args['near']['lat'], $args['near']['lng'], $args['near']['lat'] );
		}
		/**
		 * Extra WHERE fragments (already prepared SQL).
		 *
		 * @param string[] $where Fragments.
		 * @param array    $args  Args.
		 */
		$where     = (array) apply_filters( 'dev3re_search_where', $where, $args );
		$sql       = implode( ' AND ', $where );
		$order     = array(
			'newest'     => 'featured DESC, published_at DESC, post_id DESC',
			'price_asc'  => 'price = 0, price ASC, post_id DESC',
			'price_desc' => 'price DESC, post_id DESC',
			'area_desc'  => 'area_m2 DESC, post_id DESC',
			'featured'   => 'featured DESC, published_at DESC',
		);
		$order_sql = $order[ $args['sort'] ] ?? $order['newest'];
		if ( $args['near'] ) {
			$having = $wpdb->prepare( ' HAVING distance <= %f', $args['near']['radius'] );
			$rows   = $wpdb->get_col( "SELECT post_id {$distance} FROM {$table} WHERE {$sql}{$having} ORDER BY " . ( 'newest' === $args['sort'] ? 'distance ASC' : $order_sql ) );
			$total  = count( $rows );
			$rows   = array_slice( $rows, ( $args['page'] - 1 ) * $args['per_page'], $args['per_page'] );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$sql}" );
			$rows  = $wpdb->get_col( "SELECT post_id FROM {$table} WHERE {$sql} ORDER BY {$order_sql} LIMIT " . (int) $args['per_page'] . ' OFFSET ' . (int) ( ( $args['page'] - 1 ) * $args['per_page'] ) );
		}
		return array(
			'ids'      => array_map( 'intval', (array) $rows ),
			'total'    => $total,
			'page'     => $args['page'],
			'per_page' => $args['per_page'],
			'pages'    => (int) max( 1, ceil( $total / max( 1, $args['per_page'] ) ) ),
		);
	}

	/**
	 * Map markers for a result set (all matches, capped, not just the page).
	 *
	 * @param array $args Sanitized args.
	 * @param int   $cap  Max markers.
	 * @return array[]
	 */
	public static function markers( array $args, $cap = 300 ) {
		$args['page']     = 1;
		$args['per_page'] = $cap;
		$ids              = self::run( $args )['ids'];
		$out              = array();
		foreach ( $ids as $id ) {
			$c = Property::card( $id );
			if ( null === $c['lat'] || null === $c['lng'] ) {
				continue;
			}
			$out[] = array(
				'id'       => $c['id'],
				'lat'      => $c['lat'],
				'lng'      => $c['lng'],
				'title'    => $c['title'],
				'url'      => $c['url'],
				'price'    => $c['price_short'],
				'label'    => $c['price_html'],
				'image'    => $c['image'] ? (string) get_the_post_thumbnail_url( $id, 'medium' ) : '',
				'facts'    => trim( implode( ' · ', array_filter( array( $c['beds'] ? sprintf( /* translators: %d: bedrooms */ _n( '%d bed', '%d beds', $c['beds'], 'dev3-ai-real-estate' ), $c['beds'] ) : '', $c['baths'] ? sprintf( /* translators: %s: bathrooms */ __( '%s bath', 'dev3-ai-real-estate' ), $c['baths'] + 0 ) : '', $c['area'] ) ) ) ),
				'featured' => $c['featured'],
			);
		}
		return $out;
	}

	/**
	 * Human-readable chips for active filters.
	 *
	 * @param array $args Sanitized args.
	 * @return array[] [ { key, label } ]
	 */
	public static function describe( array $args ) {
		$chips = array();
		if ( $args['listing_type'] ) {
			$chips[] = array(
				'key'   => 'listing_type',
				'label' => 'rent' === $args['listing_type'] ? __( 'For rent', 'dev3-ai-real-estate' ) : __( 'For sale', 'dev3-ai-real-estate' ),
			);
		}
		foreach ( array(
			'type'     => Post_Types::TYPE,
			'location' => Post_Types::LOCATION,
			'features' => Post_Types::FEATURE,
		) as $key => $tax ) {
			foreach ( $args[ $key ] as $tid ) {
				$t = get_term( $tid, $tax );
				if ( $t && ! is_wp_error( $t ) ) {
					$chips[] = array(
						'key'   => $key,
						'value' => $t->slug,
						'label' => $t->name,
					);
				}
			}
		}
		if ( $args['price_min'] && $args['price_max'] ) {
			$chips[] = array(
				'key'   => 'price',
				'label' => Property::money( $args['price_min'] ) . ' – ' . Property::money( $args['price_max'] ),
			);
		} elseif ( $args['price_max'] ) {
			$chips[] = array(
				'key'   => 'price',
				/* translators: %s: price */
				'label' => sprintf( __( 'Up to %s', 'dev3-ai-real-estate' ), Property::money( $args['price_max'] ) ),
			);
		} elseif ( $args['price_min'] ) {
			$chips[] = array(
				'key'   => 'price',
				/* translators: %s: price */
				'label' => sprintf( __( 'From %s', 'dev3-ai-real-estate' ), Property::money( $args['price_min'] ) ),
			);
		}
		if ( $args['beds'] ) {
			$chips[] = array(
				'key'   => 'beds',
				/* translators: %d: bedrooms */
				'label' => sprintf( __( '%d+ beds', 'dev3-ai-real-estate' ), $args['beds'] ),
			);
		}
		if ( $args['baths'] ) {
			$chips[] = array(
				'key'   => 'baths',
				/* translators: %s: bathrooms */
				'label' => sprintf( __( '%s+ baths', 'dev3-ai-real-estate' ), $args['baths'] + 0 ),
			);
		}
		if ( $args['area_min'] ) {
			$chips[] = array(
				'key'   => 'area_min',
				/* translators: %s: area */
				'label' => sprintf( __( 'At least %s', 'dev3-ai-real-estate' ), number_format_i18n( $args['area_min'] ) . ' ' . Property::unit_label() ),
			);
		}
		if ( $args['area_max'] ) {
			$chips[] = array(
				'key'   => 'area_max',
				/* translators: %s: area */
				'label' => sprintf( __( 'Up to %s', 'dev3-ai-real-estate' ), number_format_i18n( $args['area_max'] ) . ' ' . Property::unit_label() ),
			);
		}
		if ( $args['year_min'] ) {
			$chips[] = array(
				'key'   => 'year_min',
				/* translators: %d: year */
				'label' => sprintf( __( 'Built %d or later', 'dev3-ai-real-estate' ), $args['year_min'] ),
			);
		}
		if ( '' !== $args['q'] ) {
			$chips[] = array(
				'key'   => 'q',
				'label' => '“' . $args['q'] . '”',
			);
		}
		if ( $args['bbox'] ) {
			$chips[] = array(
				'key'   => 'bbox',
				'label' => __( 'Map area', 'dev3-ai-real-estate' ),
			);
		}
		if ( $args['near'] ) {
			$chips[] = array(
				'key'   => 'near',
				/* translators: %s: distance */
				'label' => sprintf( __( 'Within %s km', 'dev3-ai-real-estate' ), $args['near']['radius'] + 0 ),
			);
		}
		return $chips;
	}

	/**
	 * Only the filter keys worth storing in a saved search / URL.
	 *
	 * @param array $args  Sanitized args.
	 * @param bool  $slugs Term slugs (URLs) instead of ids (stored searches).
	 * @return array
	 */
	public static function to_query( array $args, $slugs = true ) {
		$taxes = array(
			'type'     => Post_Types::TYPE,
			'location' => Post_Types::LOCATION,
			'features' => Post_Types::FEATURE,
		);
		$out   = array();
		foreach ( array( 'listing_type', 'type', 'location', 'features', 'price_min', 'price_max', 'beds', 'baths', 'area_min', 'area_max', 'year_min', 'q', 'sort' ) as $key ) {
			if ( ! empty( $args[ $key ] ) && ! ( 'sort' === $key && 'newest' === $args[ $key ] ) ) {
				$out[ $key ] = is_float( $args[ $key ] ) && floor( $args[ $key ] ) === $args[ $key ] ? (int) $args[ $key ] : $args[ $key ];
				if ( $slugs && isset( $taxes[ $key ] ) ) {
					$out[ $key ] = array_values(
						array_filter(
							array_map(
								static function ( $tid ) use ( $taxes, $key ) {
									$t = get_term( (int) $tid, $taxes[ $key ] );
									return $t && ! is_wp_error( $t ) ? $t->slug : '';
								},
								$out[ $key ]
							)
						)
					);
				}
			}
		}
		if ( $args['bbox'] ) {
			$out['bbox'] = implode( ',', array_map( static fn( $v ) => round( $v, 5 ), $args['bbox'] ) );
		}
		if ( $args['near'] ) {
			$out['lat']    = round( $args['near']['lat'], 5 );
			$out['lng']    = round( $args['near']['lng'], 5 );
			$out['radius'] = $args['near']['radius'];
		}
		return $out;
	}
}
