<?php
/**
 * Property model: field definitions, save/load, formatting.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- compact field table.

/**
 * Static helpers.
 */
class Property {

	const SQFT_PER_SQM = 10.7639104;

	/**
	 * Field definitions: key => [ type, default, extra ].
	 *
	 * @var array
	 */
	const FIELDS = array(
		'listing_type'     => array( 'enum', 'sale', array( 'sale', 'rent' ) ),
		'market'           => array( 'enum', 'available', array( 'available', 'under_offer', 'sold', 'rented' ) ),
		'price'            => array( 'money', 0 ),
		'price_period'     => array( 'enum', 'month', array( 'month', 'week', 'day', 'year' ) ),
		'price_on_request' => array( 'bool', false ),
		'price_prefix'     => array( 'text', '', 30 ),
		'price_suffix'     => array( 'text', '', 30 ),
		'beds'             => array( 'int', 0, 50 ),
		'baths'            => array( 'half', 0, 50 ),
		'garages'          => array( 'int', 0, 50 ),
		'area'             => array( 'float', 0 ),
		'area_unit'        => array( 'enum', '', array( '', 'sqft', 'sqm' ) ),
		'lot_size'         => array( 'float', 0 ),
		'year_built'       => array( 'int', 0, 2100 ),
		'floor'            => array( 'int', 0, 300 ),
		'floors'           => array( 'int', 0, 300 ),
		'energy_class'     => array( 'enum', '', array( '', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G' ) ),
		'reference'        => array( 'text', '', 40 ),
		'street'           => array( 'text', '', 190 ),
		'city'             => array( 'text', '', 120 ),
		'region'           => array( 'text', '', 120 ),
		'postcode'         => array( 'text', '', 20 ),
		'country'          => array( 'text', '', 80 ),
		'lat'              => array( 'coord', '', 90 ),
		'lng'              => array( 'coord', '', 180 ),
		'hide_address'     => array( 'bool', false ),
		'gallery'          => array( 'ids', array() ),
		'floor_plans'      => array( 'plans', array() ),
		'tour_url'         => array( 'url', '' ),
		'video_url'        => array( 'url', '' ),
		'featured'         => array( 'bool', false ),
		'agent_id'         => array( 'int', 0, PHP_INT_MAX ),
		'highlights'       => array( 'list', array(), 6 ),
		'neighbourhood'    => array( 'textarea', '', 1500 ),
		'neighbourhood_ai' => array( 'bool', false ),
		'nearby'           => array( 'list', array(), 8 ),
		'notes'            => array( 'textarea', '', 3000 ),
	);

	/**
	 * Fields an agent may not change from the front end.
	 *
	 * @var string[]
	 */
	const ADMIN_ONLY = array( 'featured', 'agent_id' );

	/**
	 * Meta key of a field.
	 *
	 * @param string $key Field.
	 * @return string
	 */
	public static function meta_key( $key ) {
		return '_dev3re_' . $key;
	}

	/**
	 * Reads one field (typed).
	 *
	 * @param int    $id  Property.
	 * @param string $key Field.
	 * @return mixed
	 */
	public static function value( $id, $key ) {
		if ( ! isset( self::FIELDS[ $key ] ) ) {
			return null;
		}
		$def = self::FIELDS[ $key ];
		$raw = get_post_meta( $id, self::meta_key( $key ), true );
		if ( '' === $raw || null === $raw || false === $raw ) {
			if ( ! metadata_exists( 'post', $id, self::meta_key( $key ) ) ) {
				return $def[1];
			}
		}
		return self::clean( $key, $raw );
	}

	/**
	 * All fields of a property.
	 *
	 * @param int $id Property.
	 * @return array
	 */
	public static function fields( $id ) {
		$out = array();
		foreach ( array_keys( self::FIELDS ) as $key ) {
			$out[ $key ] = self::value( $id, $key );
		}
		return $out;
	}

	/**
	 * Sanitizes a field value.
	 *
	 * @param string $key   Field.
	 * @param mixed  $value Raw.
	 * @return mixed
	 */
	public static function clean( $key, $value ) {
		$def = self::FIELDS[ $key ];
		switch ( $def[0] ) {
			case 'enum':
				$value = is_scalar( $value ) ? (string) $value : '';
				return in_array( $value, $def[2], true ) ? $value : $def[1];
			case 'money':
			case 'float':
				$value = is_scalar( $value ) ? preg_replace( '/[^0-9.\-]/', '', (string) $value ) : 0;
				return is_numeric( $value ) ? max( 0, round( (float) $value, 2 ) ) : 0;
			case 'half':
				$value = is_numeric( $value ) ? (float) $value : 0;
				return min( $def[2], max( 0, round( $value * 2 ) / 2 ) );
			case 'int':
				$value = is_numeric( $value ) ? (int) $value : 0;
				return min( $def[2], max( 0, $value ) );
			case 'bool':
				return in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true );
			case 'text':
				return mb_substr( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 0, $def[2] );
			case 'textarea':
				return mb_substr( sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' ), 0, $def[2] );
			case 'url':
				return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) ) : '';
			case 'coord':
				if ( ! is_numeric( $value ) ) {
					return '';
				}
				$value = (float) $value;
				return abs( $value ) <= $def[2] ? round( $value, 7 ) : '';
			case 'ids':
				$ids = array();
				foreach ( (array) $value as $v ) {
					$v = (int) $v;
					if ( $v > 0 && ! in_array( $v, $ids, true ) ) {
						$ids[] = $v;
					}
				}
				return array_slice( $ids, 0, 60 );
			case 'list':
				$list = array();
				foreach ( (array) $value as $v ) {
					$v = mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, 200 );
					if ( '' !== $v ) {
						$list[] = $v;
					}
				}
				return array_slice( $list, 0, $def[2] );
			case 'plans':
				$plans = array();
				foreach ( (array) $value as $plan ) {
					if ( ! is_array( $plan ) ) {
						continue;
					}
					$row = array(
						'title' => mb_substr( sanitize_text_field( (string) ( $plan['title'] ?? '' ) ), 0, 80 ),
						'image' => max( 0, (int) ( $plan['image'] ?? 0 ) ),
						'beds'  => min( 50, max( 0, (int) ( $plan['beds'] ?? 0 ) ) ),
						'baths' => min( 50, max( 0, round( (float) ( $plan['baths'] ?? 0 ) * 2 ) / 2 ) ),
						'area'  => max( 0, round( (float) ( $plan['area'] ?? 0 ), 2 ) ),
						'notes' => mb_substr( sanitize_textarea_field( (string) ( $plan['notes'] ?? '' ) ), 0, 400 ),
					);
					if ( '' !== $row['title'] || $row['image'] ) {
						$plans[] = $row;
					}
				}
				return array_slice( $plans, 0, 12 );
		}
		return null;
	}

	/**
	 * Creates or updates a property.
	 *
	 * @param array  $data    { id?, title, content, excerpt, status, type (int|int[]), locations (int[]), features (int[]), fields{} }.
	 * @param int    $user_id Acting user.
	 * @param string $context admin|agent.
	 * @return int|\WP_Error
	 */
	public static function save( array $data, $user_id = 0, $context = 'admin' ) {
		$id     = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$exists = $id && Post_Types::PROPERTY === get_post_type( $id );
		if ( $id && ! $exists ) {
			return new \WP_Error( 'dev3re_not_found', __( 'Property not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) );
		}
		$title = isset( $data['title'] ) ? mb_substr( sanitize_text_field( (string) $data['title'] ), 0, 200 ) : ( $exists ? get_the_title( $id ) : '' );
		if ( '' === trim( $title ) ) {
			return new \WP_Error( 'dev3re_title_required', __( 'Add a title for the property.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$post = array(
			'post_type'  => Post_Types::PROPERTY,
			'post_title' => $title,
		);
		if ( isset( $data['content'] ) ) {
			$post['post_content'] = wp_kses_post( (string) $data['content'] );
		}
		if ( isset( $data['excerpt'] ) ) {
			$post['post_excerpt'] = mb_substr( sanitize_textarea_field( (string) $data['excerpt'] ), 0, 400 );
		}
		if ( 'agent' === $context ) {
			$moderation          = (string) Settings::get( 'submission_moderation', 'pending' );
			$wants               = isset( $data['status'] ) && 'draft' === $data['status'] ? 'draft' : ( 'publish' === $moderation ? 'publish' : 'pending' );
			$current             = $exists ? get_post_status( $id ) : '';
			$post['post_status'] = ( 'publish' === $current && 'draft' !== $wants ) ? 'publish' : $wants;
		} elseif ( isset( $data['status'] ) && in_array( $data['status'], array( 'publish', 'pending', 'draft', 'private' ), true ) ) {
			$post['post_status'] = $data['status'];
		} elseif ( ! $exists ) {
			$post['post_status'] = 'draft';
		}
		if ( ! $exists ) {
			$post['post_author'] = $user_id ? (int) $user_id : get_current_user_id();
		}
		if ( $exists ) {
			$post['ID'] = $id;
			$result     = wp_update_post( wp_slash( $post ), true );
		} else {
			$result = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id     = (int) $result;
		$fields = isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array();
		foreach ( $fields as $key => $value ) {
			if ( ! isset( self::FIELDS[ $key ] ) || ( 'agent' === $context && in_array( $key, self::ADMIN_ONLY, true ) ) ) {
				continue;
			}
			update_post_meta( $id, self::meta_key( $key ), wp_slash( self::clean( $key, $value ) ) );
		}
		if ( 'agent' === $context ) {
			$agent = Agents::ensure_agent( $user_id );
			update_post_meta( $id, self::meta_key( 'agent_id' ), $agent );
		}
		foreach ( array( 'type' => Post_Types::TYPE, 'locations' => Post_Types::LOCATION, 'features' => Post_Types::FEATURE ) as $arg => $tax ) {
			if ( array_key_exists( $arg, $data ) ) {
				$ids = array_values( array_filter( array_map( 'intval', (array) $data[ $arg ] ) ) );
				$ids = array_values(
					array_filter(
						$ids,
						static function ( $tid ) use ( $tax ) {
							return (bool) term_exists( $tid, $tax );
						}
					)
				);
				wp_set_object_terms( $id, $ids, $tax );
			}
		}
		// A city without a location term gets one, so location search and archives work.
		$city = (string) self::value( $id, 'city' );
		if ( '' !== $city && ! array_key_exists( 'locations', $data ) && ! wp_get_object_terms( $id, Post_Types::LOCATION, array( 'fields' => 'ids' ) ) ) {
			$term = term_exists( $city, Post_Types::LOCATION );
			if ( ! $term ) {
				$term = wp_insert_term( $city, Post_Types::LOCATION );
			}
			if ( ! is_wp_error( $term ) && $term ) {
				wp_set_object_terms( $id, array( (int) $term['term_id'] ), Post_Types::LOCATION );
			}
		}
		$gallery = (array) self::value( $id, 'gallery' );
		if ( $gallery && ( isset( $fields['gallery'] ) || ! has_post_thumbnail( $id ) ) ) {
			set_post_thumbnail( $id, (int) $gallery[0] );
		}
		Index::sync( $id );
		/**
		 * Fires after a property is saved through the plugin.
		 *
		 * @param int    $id      Property.
		 * @param array  $data    Input.
		 * @param string $context admin|agent.
		 */
		do_action( 'dev3re_property_saved', $id, $data, $context );
		return $id;
	}

	/**
	 * Full record for the admin app / agent editor.
	 *
	 * @param int $id Property.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( $id );
		if ( ! $post || Post_Types::PROPERTY !== $post->post_type ) {
			return null;
		}
		$fields = self::fields( $id );
		$terms  = static function ( $tax ) use ( $id ) {
			$ids = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
			return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
		};
		$images = array();
		foreach ( $fields['gallery'] as $aid ) {
			$src = wp_get_attachment_image_url( $aid, 'medium' );
			if ( $src ) {
				$images[] = array(
					'id'  => (int) $aid,
					'url' => $src,
				);
			}
		}
		$plans = array();
		foreach ( $fields['floor_plans'] as $plan ) {
			$plan['image_url'] = $plan['image'] ? (string) wp_get_attachment_image_url( $plan['image'], 'medium' ) : '';
			$plans[]           = $plan;
		}
		$fields['floor_plans'] = $plans;
		return array(
			'id'        => (int) $id,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'status'    => $post->post_status,
			'author'    => (int) $post->post_author,
			'date'      => mysql2date( 'c', $post->post_date_gmt ? $post->post_date_gmt : $post->post_date ),
			'modified'  => mysql2date( 'c', $post->post_modified_gmt ),
			'url'       => (string) get_permalink( $id ),
			'type'      => $terms( Post_Types::TYPE ),
			'locations' => $terms( Post_Types::LOCATION ),
			'features'  => $terms( Post_Types::FEATURE ),
			'fields'    => $fields,
			'images'    => $images,
			'views'     => (int) get_post_meta( $id, '_dev3re_views', true ),
			'leads'     => Leads::count( array( 'property_id' => $id ) ),
		);
	}

	/**
	 * Compact data for cards, map markers and compare.
	 *
	 * @param int $id Property.
	 * @return array
	 */
	public static function card( $id ) {
		$f     = self::fields( $id );
		$types = get_the_terms( $id, Post_Types::TYPE );
		$img   = get_the_post_thumbnail_url( $id, 'medium_large' );
		if ( ! $img && $f['gallery'] ) {
			$img = wp_get_attachment_image_url( (int) $f['gallery'][0], 'medium_large' );
		}
		$feat_terms = get_the_terms( $id, Post_Types::FEATURE );
		$area_m2    = self::area_m2( $f['area'], $f['area_unit'] );
		return array(
			'id'           => (int) $id,
			'title'        => get_the_title( $id ),
			'url'          => (string) get_permalink( $id ),
			'image'        => $img ? (string) $img : '',
			'image_count'  => count( $f['gallery'] ),
			'listing_type' => $f['listing_type'],
			'market'       => $f['market'],
			'price'        => (float) $f['price'],
			'price_html'   => self::price_label( $f ),
			'price_short'  => self::price_short( $f ),
			'beds'         => (int) $f['beds'],
			'baths'        => (float) $f['baths'],
			'garages'      => (int) $f['garages'],
			'area'         => self::area_label( $area_m2 ),
			'area_m2'      => $area_m2,
			'price_per'    => ( $area_m2 > 0 && $f['price'] > 0 && ! $f['price_on_request'] ) ? self::money( $f['price'] / self::from_m2( $area_m2 ) ) . ' / ' . self::unit_label() : '',
			'year_built'   => (int) $f['year_built'],
			'type'         => $types && ! is_wp_error( $types ) ? $types[0]->name : '',
			'features'     => $feat_terms && ! is_wp_error( $feat_terms ) ? wp_list_pluck( $feat_terms, 'name' ) : array(),
			'address'      => self::address( $f, true ),
			'city'         => $f['city'],
			'featured'     => (bool) $f['featured'],
			'lat'          => '' === $f['lat'] ? null : (float) $f['lat'],
			'lng'          => '' === $f['lng'] ? null : (float) $f['lng'],
			'date'         => get_the_date( 'c', $id ),
		);
	}

	/**
	 * Area converted to square metres.
	 *
	 * @param float  $area Area.
	 * @param string $unit sqft|sqm|'' (site unit).
	 * @return float
	 */
	public static function area_m2( $area, $unit = '' ) {
		$unit = $unit ? $unit : (string) Settings::get( 'area_unit', 'sqft' );
		return 'sqft' === $unit ? round( (float) $area / self::SQFT_PER_SQM, 2 ) : round( (float) $area, 2 );
	}

	/**
	 * Square metres to the site unit.
	 *
	 * @param float $m2 Square metres.
	 * @return float
	 */
	public static function from_m2( $m2 ) {
		return 'sqft' === Settings::get( 'area_unit', 'sqft' ) ? (float) $m2 * self::SQFT_PER_SQM : (float) $m2;
	}

	/**
	 * Site area unit label.
	 *
	 * @return string
	 */
	public static function unit_label() {
		return 'sqft' === Settings::get( 'area_unit', 'sqft' ) ? 'ft²' : 'm²';
	}

	/**
	 * Area label in the site unit.
	 *
	 * @param float $m2 Square metres.
	 * @return string
	 */
	public static function area_label( $m2 ) {
		if ( $m2 <= 0 ) {
			return '';
		}
		return number_format_i18n( round( self::from_m2( $m2 ) ) ) . ' ' . self::unit_label();
	}

	/**
	 * Formats money with the site currency.
	 *
	 * @param float $amount   Amount.
	 * @param int   $decimals Decimals.
	 * @return string
	 */
	public static function money( $amount, $decimals = 0 ) {
		$symbol = (string) Settings::get( 'currency_symbol', '$' );
		$sep    = (string) Settings::get( 'thousands_sep', ',' );
		$num    = number_format( (float) $amount, $decimals, ',' === $sep ? '.' : ',', $sep );
		switch ( (string) Settings::get( 'currency_position', 'before' ) ) {
			case 'after':
				return $num . $symbol;
			case 'after_space':
				return $num . ' ' . $symbol;
			case 'before_space':
				return $symbol . ' ' . $num;
		}
		return $symbol . $num;
	}

	/**
	 * Price label ("$450,000", "$2,100 / month", "Price on request").
	 *
	 * @param array $f Fields.
	 * @return string
	 */
	public static function price_label( array $f ) {
		if ( ! empty( $f['price_on_request'] ) || empty( $f['price'] ) ) {
			return __( 'Price on request', 'dev3-ai-real-estate' );
		}
		$label = self::money( $f['price'] );
		if ( 'rent' === $f['listing_type'] ) {
			$periods = array(
				'month' => __( 'month', 'dev3-ai-real-estate' ),
				'week'  => __( 'week', 'dev3-ai-real-estate' ),
				'day'   => __( 'day', 'dev3-ai-real-estate' ),
				'year'  => __( 'year', 'dev3-ai-real-estate' ),
			);
			$label  .= ' / ' . ( $periods[ $f['price_period'] ] ?? $periods['month'] );
		}
		$prefix = isset( $f['price_prefix'] ) ? trim( (string) $f['price_prefix'] ) : '';
		$suffix = isset( $f['price_suffix'] ) ? trim( (string) $f['price_suffix'] ) : '';
		return trim( ( $prefix ? $prefix . ' ' : '' ) . $label . ( $suffix ? ' ' . $suffix : '' ) );
	}

	/**
	 * Short price for map tags ("$450k", "$1.2M").
	 *
	 * @param array $f Fields.
	 * @return string
	 */
	public static function price_short( array $f ) {
		$p = (float) $f['price'];
		if ( ! empty( $f['price_on_request'] ) || $p <= 0 ) {
			return '•';
		}
		$symbol = (string) Settings::get( 'currency_symbol', '$' );
		if ( $p >= 1000000 ) {
			$num = rtrim( rtrim( number_format( $p / 1000000, 2, '.', '' ), '0' ), '.' ) . 'M';
		} elseif ( $p >= 1000 ) {
			$num = round( $p / 1000 ) . 'k';
		} else {
			$num = (string) round( $p );
		}
		return in_array( Settings::get( 'currency_position', 'before' ), array( 'after', 'after_space' ), true ) ? $num . $symbol : $symbol . $num;
	}

	/**
	 * Address line.
	 *
	 * @param array $f      Fields.
	 * @param bool  $is_public Respect "hide exact address".
	 * @return string
	 */
	public static function address( array $f, $is_public = true ) {
		$parts = $is_public && ! empty( $f['hide_address'] ) ? array( $f['city'], $f['region'] ) : array( $f['street'], $f['city'], $f['region'], $f['postcode'], $f['country'] );
		return implode( ', ', array_filter( array_map( 'trim', array_map( 'strval', $parts ) ) ) );
	}

	/**
	 * Market status label.
	 *
	 * @param string $market Market.
	 * @return string
	 */
	public static function market_label( $market ) {
		$labels = array(
			'available'   => __( 'Available', 'dev3-ai-real-estate' ),
			'under_offer' => __( 'Under offer', 'dev3-ai-real-estate' ),
			'sold'        => __( 'Sold', 'dev3-ai-real-estate' ),
			'rented'      => __( 'Rented', 'dev3-ai-real-estate' ),
		);
		return $labels[ $market ] ?? $labels['available'];
	}

	/**
	 * Converts a virtual tour / video URL into an allow-listed embed URL.
	 *
	 * @param string $url URL.
	 * @return string Embed URL or ''.
	 */
	public static function embed_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! preg_match( '#^https://#i', $url ) ) {
			return '';
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1];
		}
		if ( preg_match( '#vimeo\.com/(?:video/)?(\d{5,12})#', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1];
		}
		$allowed = array( 'my.matterport.com', 'matterport.com', 'kuula.co', 'app.cloudpano.com', 'cloudpano.com', 'tour.giraffe360.com', 'momento360.com', 'roundme.com', 'theasys.io', 'google.com', 'maps.google.com' );
		/**
		 * Hosts allowed for virtual tour iframes.
		 *
		 * @param string[] $allowed Hosts.
		 */
		$allowed = (array) apply_filters( 'dev3re_tour_hosts', $allowed );
		if ( ! in_array( $host, $allowed, true ) ) {
			return '';
		}
		if ( in_array( $host, array( 'google.com', 'maps.google.com' ), true ) && false === strpos( $url, '/maps/embed' ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	/**
	 * Increments the view counter.
	 *
	 * @param int $id Property.
	 */
	public static function count_view( $id ) {
		update_post_meta( $id, '_dev3re_views', (int) get_post_meta( $id, '_dev3re_views', true ) + 1 );
	}
}
