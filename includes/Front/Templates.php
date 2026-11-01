<?php
/**
 * Front-end markup: icons, property cards, grids, search page, agents, favourites, compare, mortgage.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Front;

use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Search\Nl_Parser;
use Dev3\AiRealEstate\Search\Search;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- helpers return escaped markup.

/**
 * Static renderers.
 */
class Templates {

	/**
	 * Inline SVG icon.
	 *
	 * @param string $name Name.
	 * @param int    $size Size.
	 * @return string
	 */
	public static function icon( $name, $size = 18 ) {
		$paths = array(
			'bed'      => 'M3 18v-6a2 2 0 012-2h14a2 2 0 012 2v6M3 18h18M3 18v2M21 18v2M6 10V7a1 1 0 011-1h4a1 1 0 011 1v3M12 10V7a1 1 0 011-1h4a1 1 0 011 1v3',
			'bath'     => 'M4 12h16v3a5 5 0 01-5 5H9a5 5 0 01-5-5v-3zM6 12V5a2 2 0 014 0M7 20l-1 2M17 20l1 2',
			'area'     => 'M3 9V3h6M21 9V3h-6M3 15v6h6M21 15v6h-6',
			'car'      => 'M5 17h14M6 17v2M18 17v2M3 13l2-6a2 2 0 012-1h10a2 2 0 012 1l2 6v4H3v-4zM7 13h.01M17 13h.01',
			'pin'      => 'M12 21s-7-6.3-7-11.5A7 7 0 0119 9.5C19 14.7 12 21 12 21zm0-9a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
			'heart'    => 'M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1-1a5.5 5.5 0 00-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 000-7.8z',
			'compare'  => 'M8 3H5a2 2 0 00-2 2v14a2 2 0 002 2h3M16 3h3a2 2 0 012 2v14a2 2 0 01-2 2h-3M12 2v20',
			'share'    => 'M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v14',
			'print'    => 'M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z',
			'search'   => 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3',
			'map'      => 'M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4zM8 2v16M16 6v16',
			'list'     => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
			'grid'     => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
			'filter'   => 'M4 6h16M7 12h10M10 18h4',
			'camera'   => 'M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2zM12 17a4 4 0 100-8 4 4 0 000 8z',
			'phone'    => 'M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3.1 19.5 19.5 0 01-6-6A19.8 19.8 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.4 1.8.7 2.7a2 2 0 01-.5 2.1L8 9.8a16 16 0 006 6l1.3-1.3a2 2 0 012.1-.4c.9.3 1.8.6 2.7.7a2 2 0 011.7 2z',
			'mail'     => 'M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zM22 6l-10 7L2 6',
			'chat'     => 'M21 11.5a8.4 8.4 0 01-9 8.4 8.5 8.5 0 01-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 1121 11.5z',
			'calendar' => 'M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2zM16 2v4M8 2v4M3 10h18',
			'check'    => 'M20 6L9 17l-5-5',
			'x'        => 'M18 6L6 18M6 6l12 12',
			'left'     => 'M15 18l-6-6 6-6',
			'right'    => 'M9 18l6-6-6-6',
			'sparkles' => 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z',
			'home'     => 'M3 10.5L12 3l9 7.5V21a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1z',
			'plan'     => 'M3 3h18v18H3zM3 12h8M11 3v13M15 12h6M15 12v9',
			'tour'     => 'M12 22a10 10 0 100-20 10 10 0 000 20zM2 12h20M12 2a15 15 0 010 20',
			'play'     => 'M5 3l14 9-14 9V3z',
			'user'     => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
			'building' => 'M4 21V4a1 1 0 011-1h9a1 1 0 011 1v17M15 9h4a1 1 0 011 1v11M3 21h18M8 7h3M8 11h3M8 15h3',
			'bell'     => 'M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0',
			'calc'     => 'M5 2h14a1 1 0 011 1v18a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1zM8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h8',
			'globe'    => 'M12 22a10 10 0 100-20 10 10 0 000 20zM2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z',
			'upload'   => 'M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12',
			'trash'    => 'M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6',
			'edit'     => 'M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z',
			'eye'      => 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM12 15a3 3 0 100-6 3 3 0 000 6z',
			'layers'   => 'M12 2l10 5-10 5L2 7l10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
		);
		$d     = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['home'];
		return '<svg class="dev3re-icon" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $d ) . '"/></svg>';
	}

	/**
	 * "For sale" / "For rent" label.
	 *
	 * @param string $type sale|rent.
	 * @return string
	 */
	public static function deal_label( $type ) {
		return 'rent' === $type ? __( 'For rent', 'dev3-ai-real-estate' ) : __( 'For sale', 'dev3-ai-real-estate' );
	}

	/**
	 * Favourite + compare buttons.
	 *
	 * @param int    $id      Property.
	 * @param string $variant card|single.
	 * @return string
	 */
	public static function action_buttons( $id, $variant = 'card' ) {
		$html = '';
		if ( Settings::get( 'favourites_enabled', true ) ) {
			$html .= '<button type="button" class="dev3re-act dev3re-act--fav" data-dev3re-fav="' . (int) $id . '" aria-pressed="false" aria-label="' . esc_attr__( 'Save to favourites', 'dev3-ai-real-estate' ) . '" title="' . esc_attr__( 'Save to favourites', 'dev3-ai-real-estate' ) . '">' . self::icon( 'heart' ) . ( 'single' === $variant ? '<span>' . esc_html__( 'Save', 'dev3-ai-real-estate' ) . '</span>' : '' ) . '</button>';
		}
		if ( Settings::get( 'compare_enabled', true ) ) {
			$html .= '<button type="button" class="dev3re-act dev3re-act--cmp" data-dev3re-compare="' . (int) $id . '" aria-pressed="false" aria-label="' . esc_attr__( 'Add to compare', 'dev3-ai-real-estate' ) . '" title="' . esc_attr__( 'Add to compare', 'dev3-ai-real-estate' ) . '">' . self::icon( 'compare' ) . ( 'single' === $variant ? '<span>' . esc_html__( 'Compare', 'dev3-ai-real-estate' ) . '</span>' : '' ) . '</button>';
		}
		return $html;
	}

	/**
	 * Key facts list (beds, baths, area, parking).
	 *
	 * @param array  $c     Card data.
	 * @param string $list_class List class.
	 * @return string
	 */
	public static function facts( array $c, $list_class = 'dev3re-facts' ) {
		$items = array();
		if ( $c['beds'] ) {
			/* translators: %d: bedrooms */
			$items[] = array( 'bed', (string) $c['beds'], _n( 'bed', 'beds', $c['beds'], 'dev3-ai-real-estate' ), sprintf( _n( '%d bedroom', '%d bedrooms', $c['beds'], 'dev3-ai-real-estate' ), $c['beds'] ) );
		}
		if ( $c['baths'] ) {
			/* translators: %s: bathrooms */
			$items[] = array( 'bath', (string) ( $c['baths'] + 0 ), _n( 'bath', 'baths', (int) ceil( $c['baths'] ), 'dev3-ai-real-estate' ), sprintf( __( '%s bathrooms', 'dev3-ai-real-estate' ), $c['baths'] + 0 ) );
		}
		if ( $c['area'] ) {
			$items[] = array( 'area', $c['area'], '', __( 'Living area', 'dev3-ai-real-estate' ) . ' ' . $c['area'] );
		}
		if ( ! empty( $c['garages'] ) ) {
			/* translators: %d: parking spaces */
			$items[] = array( 'car', (string) $c['garages'], _n( 'parking', 'parking', $c['garages'], 'dev3-ai-real-estate' ), sprintf( _n( '%d parking space', '%d parking spaces', $c['garages'], 'dev3-ai-real-estate' ), $c['garages'] ) );
		}
		if ( ! $items ) {
			return '';
		}
		$html = '<ul class="' . esc_attr( $list_class ) . '">';
		foreach ( $items as $it ) {
			$html .= '<li title="' . esc_attr( $it[3] ) . '">' . self::icon( $it[0], 16 ) . '<span class="dev3re-sr">' . esc_html( $it[3] ) . '</span><span aria-hidden="true"><strong>' . esc_html( $it[1] ) . '</strong>' . ( $it[2] ? ' ' . esc_html( $it[2] ) : '' ) . '</span></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Property card.
	 *
	 * @param int  $id      Property.
	 * @param bool $heading Use h3 (false = div for compact lists).
	 * @return string
	 */
	public static function card( $id, $heading = true ) {
		$c     = Property::card( $id );
		$img   = (int) get_post_thumbnail_id( $id );
		$gal   = (array) Property::value( $id, 'gallery' );
		$img   = $img ? $img : ( $gal ? (int) $gal[0] : 0 );
		$media = $img ? wp_get_attachment_image(
			$img,
			'medium_large',
			false,
			array(
				'class'   => 'dev3re-prop__img',
				'loading' => 'lazy',
				'alt'     => '',
				'sizes'   => '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 400px',
			)
		) : '<span class="dev3re-prop__ph">' . self::icon( 'home', 40 ) . '</span>';
		$tag   = $heading ? 'h3' : 'div';
		$tags  = '<span class="dev3re-tag dev3re-tag--' . esc_attr( $c['listing_type'] ) . '">' . esc_html( self::deal_label( $c['listing_type'] ) ) . '</span>';
		if ( $c['featured'] ) {
			$tags .= '<span class="dev3re-tag dev3re-tag--featured">' . esc_html__( 'Featured', 'dev3-ai-real-estate' ) . '</span>';
		}
		if ( 'available' !== $c['market'] ) {
			$tags .= '<span class="dev3re-tag dev3re-tag--' . esc_attr( $c['market'] ) . '">' . esc_html( Property::market_label( $c['market'] ) ) . '</span>';
		}
		$html  = '<article class="dev3re-prop' . ( in_array( $c['market'], array( 'sold', 'rented' ), true ) ? ' is-closed' : '' ) . '" data-id="' . (int) $id . '">';
		$html .= '<div class="dev3re-prop__media"><a href="' . esc_url( $c['url'] ) . '" tabindex="-1" aria-hidden="true">' . $media . '</a>';
		$html .= '<span class="dev3re-prop__tags">' . $tags . '</span>';
		if ( $c['image_count'] > 1 ) {
			$html .= '<span class="dev3re-prop__count">' . self::icon( 'camera', 14 ) . esc_html( (string) $c['image_count'] ) . '<span class="dev3re-sr"> ' . esc_html__( 'photos', 'dev3-ai-real-estate' ) . '</span></span>';
		}
		$html .= '<span class="dev3re-prop__actions">' . self::action_buttons( $id ) . '</span></div>';
		$html .= '<div class="dev3re-prop__body">';
		$html .= '<div class="dev3re-prop__price">' . esc_html( $c['price_html'] ) . '</div>';
		$html .= '<' . $tag . ' class="dev3re-prop__title"><a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['title'] ) . '</a></' . $tag . '>';
		if ( $c['address'] ) {
			$html .= '<p class="dev3re-prop__addr">' . self::icon( 'pin', 14 ) . '<span>' . esc_html( $c['address'] ) . '</span></p>';
		}
		$html .= self::facts( $c );
		if ( $c['type'] ) {
			$html .= '<p class="dev3re-prop__type">' . esc_html( $c['type'] ) . '</p>';
		}
		$html .= '</div></article>';
		/**
		 * Filters the property card HTML.
		 *
		 * @param string $html Card.
		 * @param int    $id   Property.
		 * @param array  $c    Card data.
		 */
		return (string) apply_filters( 'dev3re_card_html', $html, $id, $c );
	}

	/**
	 * Cards grid.
	 *
	 * @param int[] $ids     Properties.
	 * @param int   $columns Columns (desktop).
	 * @return string
	 */
	public static function cards( array $ids, $columns = 3 ) {
		$html = '<div class="dev3re-grid" style="--dev3re-cols:' . (int) min( 4, max( 1, $columns ) ) . '">';
		foreach ( $ids as $id ) {
			$html .= self::card( $id );
		}
		return $html . '</div>';
	}

	/**
	 * Empty results.
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 * @return string
	 */
	public static function empty_state( $title, $text = '' ) {
		return '<div class="dev3re-empty">' . self::icon( 'home', 36 ) . '<p class="dev3re-empty__title">' . esc_html( $title ) . '</p>' . ( $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</div>';
	}

	/**
	 * Pagination links (buttons; JS intercepts, links work without JS).
	 *
	 * @param int    $page  Page.
	 * @param int    $pages Pages.
	 * @param string $base  Base URL with current query.
	 * @return string
	 */
	public static function pagination( $page, $pages, $base ) {
		if ( $pages < 2 ) {
			return '';
		}
		$html  = '<nav class="dev3re-pages" aria-label="' . esc_attr__( 'Results pages', 'dev3-ai-real-estate' ) . '">';
		$range = array_unique( array_filter( array( 1, $page - 1, $page, $page + 1, $pages ), static fn( $p ) => $p >= 1 && $p <= $pages ) );
		sort( $range );
		if ( $page > 1 ) {
			$html .= '<a class="dev3re-pages__btn" data-page="' . ( $page - 1 ) . '" href="' . esc_url( add_query_arg( 'pg', $page - 1, $base ) ) . '" aria-label="' . esc_attr__( 'Previous page', 'dev3-ai-real-estate' ) . '">' . self::icon( 'left', 16 ) . '</a>';
		}
		$prev = 0;
		foreach ( $range as $p ) {
			if ( $prev && $p > $prev + 1 ) {
				$html .= '<span class="dev3re-pages__gap" aria-hidden="true">…</span>';
			}
			$html .= $p === $page ? '<span class="dev3re-pages__btn is-current" aria-current="page">' . (int) $p . '</span>' : '<a class="dev3re-pages__btn" data-page="' . (int) $p . '" href="' . esc_url( add_query_arg( 'pg', $p, $base ) ) . '">' . (int) $p . '</a>';
			$prev  = $p;
		}
		if ( $page < $pages ) {
			$html .= '<a class="dev3re-pages__btn" data-page="' . ( $page + 1 ) . '" href="' . esc_url( add_query_arg( 'pg', $page + 1, $base ) ) . '" aria-label="' . esc_attr__( 'Next page', 'dev3-ai-real-estate' ) . '">' . self::icon( 'right', 16 ) . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * Select element.
	 *
	 * @param string $name     Name.
	 * @param string $label    Label.
	 * @param array  $options  value => label.
	 * @param mixed  $selected Selected.
	 * @param string $id       Id.
	 * @return string
	 */
	public static function select( $name, $label, array $options, $selected, $id ) {
		$html = '<div class="dev3re-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $html . '</select></div>';
	}

	/**
	 * Term options value => label (hierarchical indent).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $all      "Any" label.
	 * @return array
	 */
	public static function term_options( $taxonomy, $all ) {
		$out   = array( '' => $all );
		$terms = Post_Types::terms( $taxonomy );
		$kids  = array();
		foreach ( $terms as $t ) {
			$kids[ $t['parent'] ][] = $t;
		}
		$walk = static function ( $parent_id, $depth ) use ( &$walk, &$out, $kids ) {
			foreach ( $kids[ $parent_id ] ?? array() as $t ) {
				$out[ $t['slug'] ] = str_repeat( '— ', $depth ) . $t['name'];
				$walk( $t['id'], $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $out;
	}

	/**
	 * Price options for selects.
	 *
	 * @param string $deal sale|rent.
	 * @return array
	 */
	public static function price_steps( $deal = 'sale' ) {
		$steps = 'rent' === $deal ? array( 500, 750, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 7500, 10000 ) : array( 50000, 100000, 150000, 200000, 300000, 400000, 500000, 750000, 1000000, 1500000, 2000000, 5000000 );
		/**
		 * Price steps of the search selects.
		 *
		 * @param int[]  $steps Steps.
		 * @param string $deal  sale|rent.
		 */
		return (array) apply_filters( 'dev3re_price_steps', $steps, $deal );
	}

	/**
	 * Full search page (filters, chips, results, map).
	 *
	 * @param array $atts { listing_type, type, location, columns, per_page, map (yes|no), view (list|map) }.
	 * @return string
	 */
	public static function search( array $atts ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only search parameters.
		$get = array();
		foreach ( wp_unslash( $_GET ) as $k => $v ) {
			$get[ sanitize_key( $k ) ] = is_array( $v ) ? array_map( 'sanitize_text_field', $v ) : sanitize_text_field( (string) $v );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$defaults = array(
			'listing_type' => '',
			'type'         => '',
			'location'     => '',
			'columns'      => 2,
			'per_page'     => (int) Settings::get( 'per_page', 12 ),
			'map'          => 'yes',
			'view'         => 'list',
		);
		$atts     = array_merge( $defaults, array_intersect_key( $atts, $defaults ) );
		$raw      = array_merge( array_filter( array( 'listing_type' => $atts['listing_type'], 'type' => $atts['type'], 'location' => $atts['location'] ) ), $get ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$nl       = isset( $get['nl'] ) ? mb_substr( (string) $get['nl'], 0, 200 ) : '';
		if ( '' !== $nl ) {
			$raw = array_merge( Nl_Parser::parse( $nl ), $raw );
		}
		$raw['page']     = isset( $get['pg'] ) ? (int) $get['pg'] : 1;
		$raw['per_page'] = (int) $atts['per_page'];
		$args            = Search::sanitize( $raw );
		$res             = Search::run( $args );
		$map             = 'no' !== $atts['map'];
		$uid             = 'dev3re-s' . wp_rand( 100, 9999 );
		$deal            = $args['listing_type'];
		$first           = static function ( $ids, $tax ) {
			if ( ! $ids ) {
				return '';
			}
			$t = get_term( $ids[0], $tax );
			return $t && ! is_wp_error( $t ) ? $t->slug : '';
		};
		$money_opts      = static function ( $deal, $any ) {
			$out = array( '' => $any );
			foreach ( self::price_steps( $deal ? $deal : 'sale' ) as $step ) {
				$out[ $step ] = Property::money( $step );
			}
			return $out;
		};
		$beds            = array( '' => __( 'Any', 'dev3-ai-real-estate' ) );
		for ( $i = 1; $i <= 6; $i++ ) {
			$beds[ $i ] = $i . '+';
		}
		$features = Post_Types::terms( Post_Types::FEATURE );
		$config   = array(
			'perPage' => (int) $atts['per_page'],
			'columns' => (int) $atts['columns'],
			'map'     => $map,
			'base'    => (string) get_permalink(),
			'prices'  => array(),
			'money'   => array(
				'symbol'   => (string) Settings::get( 'currency_symbol', '$' ),
				'position' => (string) Settings::get( 'currency_position', 'before' ),
				'sep'      => (string) Settings::get( 'thousands_sep', ',' ),
			),
		);
		foreach ( array( 'sale', 'rent' ) as $deal_key ) {
			foreach ( self::price_steps( $deal_key ) as $step ) {
				$config['prices'][ $deal_key ][] = array( (int) $step, Property::money( $step ) );
			}
		}
		ob_start();
		?>
		<div class="dev3re dev3re-search<?php echo $map ? ' has-map' : ''; ?><?php echo 'map' === $atts['view'] ? ' is-map-view' : ''; ?>" data-dev3re-search="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<?php echo Frontend::notice_html(); ?>
			<form class="dev3re-sbar" role="search" method="get" action="<?php echo esc_url( (string) get_permalink() ); ?>" data-dev3re-form>
				<div class="dev3re-sbar__nl">
					<label for="<?php echo esc_attr( $uid ); ?>-nl" class="dev3re-sr"><?php esc_html_e( 'Describe the home you are looking for', 'dev3-ai-real-estate' ); ?></label>
					<span class="dev3re-sbar__icon"><?php echo self::icon( 'search' ); ?></span>
					<input id="<?php echo esc_attr( $uid ); ?>-nl" type="search" name="nl" value="<?php echo esc_attr( $nl ); ?>" placeholder="<?php esc_attr_e( 'e.g. 3 bed house with a garden under 450k', 'dev3-ai-real-estate' ); ?>" autocomplete="off" enterkeyhint="search">
					<button type="submit" class="dev3re-btn dev3re-btn--primary"><?php esc_html_e( 'Search', 'dev3-ai-real-estate' ); ?></button>
				</div>
				<div class="dev3re-sbar__row">
					<fieldset class="dev3re-seg">
						<legend class="dev3re-sr"><?php esc_html_e( 'Buy or rent', 'dev3-ai-real-estate' ); ?></legend>
						<?php foreach ( array( '' => __( 'All', 'dev3-ai-real-estate' ), 'sale' => __( 'Buy', 'dev3-ai-real-estate' ), 'rent' => __( 'Rent', 'dev3-ai-real-estate' ) ) as $value => $label ) : // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
							<label><input type="radio" name="listing_type" value="<?php echo esc_attr( $value ); ?>" <?php checked( $deal, $value ); ?>><span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
					</fieldset>
					<?php
					echo self::select( 'type', __( 'Property type', 'dev3-ai-real-estate' ), self::term_options( Post_Types::TYPE, __( 'Any type', 'dev3-ai-real-estate' ) ), $first( $args['type'], Post_Types::TYPE ), $uid . '-type' );
					echo self::select( 'location', __( 'Location', 'dev3-ai-real-estate' ), self::term_options( Post_Types::LOCATION, __( 'Anywhere', 'dev3-ai-real-estate' ) ), $first( $args['location'], Post_Types::LOCATION ), $uid . '-loc' );
					echo self::select( 'price_min', __( 'Min price', 'dev3-ai-real-estate' ), $money_opts( $deal, __( 'No min', 'dev3-ai-real-estate' ) ), $args['price_min'] ? (int) $args['price_min'] : '', $uid . '-pmin' );
					echo self::select( 'price_max', __( 'Max price', 'dev3-ai-real-estate' ), $money_opts( $deal, __( 'No max', 'dev3-ai-real-estate' ) ), $args['price_max'] ? (int) $args['price_max'] : '', $uid . '-pmax' );
					echo self::select( 'beds', __( 'Bedrooms', 'dev3-ai-real-estate' ), $beds, $args['beds'] ? $args['beds'] : '', $uid . '-beds' );
					?>
					<button type="button" class="dev3re-btn dev3re-btn--ghost dev3re-sbar__more" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-more" data-dev3re-more><?php echo self::icon( 'filter' ); ?><span><?php esc_html_e( 'More filters', 'dev3-ai-real-estate' ); ?></span><span class="dev3re-count" data-dev3re-more-count hidden></span></button>
				</div>
				<div class="dev3re-sbar__panel" id="<?php echo esc_attr( $uid ); ?>-more" hidden>
					<div class="dev3re-sbar__grid">
						<?php
						echo self::select( 'baths', __( 'Bathrooms', 'dev3-ai-real-estate' ), array_slice( $beds, 0, 5, true ), $args['baths'] ? (int) $args['baths'] : '', $uid . '-baths' );
						?>
						<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-amin"><?php echo esc_html( sprintf( /* translators: %s: unit */ __( 'Min area (%s)', 'dev3-ai-real-estate' ), Property::unit_label() ) ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-amin" type="number" inputmode="numeric" min="0" name="area_min" value="<?php echo $args['area_min'] ? esc_attr( (string) ( $args['area_min'] + 0 ) ) : ''; ?>"></div>
						<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-amax"><?php echo esc_html( sprintf( /* translators: %s: unit */ __( 'Max area (%s)', 'dev3-ai-real-estate' ), Property::unit_label() ) ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-amax" type="number" inputmode="numeric" min="0" name="area_max" value="<?php echo $args['area_max'] ? esc_attr( (string) ( $args['area_max'] + 0 ) ) : ''; ?>"></div>
						<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-year"><?php esc_html_e( 'Built after', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-year" type="number" inputmode="numeric" min="1800" max="2100" name="year_min" value="<?php echo $args['year_min'] ? esc_attr( (string) $args['year_min'] ) : ''; ?>"></div>
						<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-q"><?php esc_html_e( 'Keyword', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-q" type="text" name="q" value="<?php echo esc_attr( $args['q'] ); ?>" placeholder="<?php esc_attr_e( 'Street, reference…', 'dev3-ai-real-estate' ); ?>"></div>
					</div>
					<?php if ( $features ) : ?>
						<fieldset class="dev3re-checks">
							<legend><?php esc_html_e( 'Features', 'dev3-ai-real-estate' ); ?></legend>
							<?php foreach ( $features as $f ) : ?>
								<label><input type="checkbox" name="features[]" value="<?php echo esc_attr( $f['slug'] ); ?>" <?php checked( in_array( $f['id'], $args['features'], true ) ); ?>><span><?php echo esc_html( $f['name'] ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>
					<div class="dev3re-sbar__panelfoot">
						<button type="button" class="dev3re-btn dev3re-btn--link" data-dev3re-reset><?php esc_html_e( 'Clear all', 'dev3-ai-real-estate' ); ?></button>
						<button type="submit" class="dev3re-btn dev3re-btn--primary"><?php esc_html_e( 'Show results', 'dev3-ai-real-estate' ); ?></button>
					</div>
				</div>
				<input type="hidden" name="sort" value="<?php echo esc_attr( $args['sort'] ); ?>" data-dev3re-sort-field>
				<input type="hidden" name="bbox" value="<?php echo esc_attr( $args['bbox'] ? implode( ',', $args['bbox'] ) : '' ); ?>">
			</form>

			<div class="dev3re-chips" data-dev3re-chips aria-live="polite"><?php echo self::chips( Search::describe( $args ) ); ?></div>

			<div class="dev3re-toolbar">
				<p class="dev3re-toolbar__count" data-dev3re-count aria-live="polite"><?php echo esc_html( self::count_label( $res['total'] ) ); ?></p>
				<div class="dev3re-toolbar__actions">
					<?php
					echo self::select(
						'sort_ui',
						__( 'Sort by', 'dev3-ai-real-estate' ),
						array(
							'newest'     => __( 'Newest', 'dev3-ai-real-estate' ),
							'price_asc'  => __( 'Price: low to high', 'dev3-ai-real-estate' ),
							'price_desc' => __( 'Price: high to low', 'dev3-ai-real-estate' ),
							'area_desc'  => __( 'Largest first', 'dev3-ai-real-estate' ),
						),
						$args['sort'],
						$uid . '-sort'
					);
					?>
					<?php if ( Settings::get( 'saved_searches_enabled', true ) ) : ?>
						<button type="button" class="dev3re-btn dev3re-btn--ghost" data-dev3re-save-search aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-save"><?php echo self::icon( 'bell' ); ?><span><?php esc_html_e( 'Save search', 'dev3-ai-real-estate' ); ?></span></button>
					<?php endif; ?>
					<?php if ( $map ) : ?>
						<button type="button" class="dev3re-btn dev3re-btn--ghost dev3re-viewtoggle" data-dev3re-view aria-pressed="<?php echo 'map' === $atts['view'] ? 'true' : 'false'; ?>"><?php echo self::icon( 'map' ); ?><span><?php esc_html_e( 'Map', 'dev3-ai-real-estate' ); ?></span></button>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( Settings::get( 'saved_searches_enabled', true ) ) : ?>
				<form class="dev3re-savebox" id="<?php echo esc_attr( $uid ); ?>-save" data-dev3re-savebox hidden>
					<p class="dev3re-savebox__title"><?php echo self::icon( 'bell' ); ?><?php esc_html_e( 'Get an email when new properties match this search', 'dev3-ai-real-estate' ); ?></p>
					<div class="dev3re-savebox__row">
						<?php if ( ! is_user_logged_in() ) : ?>
							<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-email" type="email" name="email" required autocomplete="email"></div>
						<?php endif; ?>
						<?php
						echo self::select(
							'frequency',
							__( 'How often', 'dev3-ai-real-estate' ),
							array(
								'daily'   => __( 'Daily', 'dev3-ai-real-estate' ),
								'weekly'  => __( 'Weekly', 'dev3-ai-real-estate' ),
								'instant' => __( 'As soon as they are listed', 'dev3-ai-real-estate' ),
							),
							'daily',
							$uid . '-freq'
						);
						?>
						<button type="submit" class="dev3re-btn dev3re-btn--primary"><?php esc_html_e( 'Save', 'dev3-ai-real-estate' ); ?></button>
					</div>
					<p class="dev3re-savebox__msg" data-dev3re-savebox-msg role="status"></p>
				</form>
			<?php endif; ?>

			<div class="dev3re-slayout">
				<div class="dev3re-slayout__results" data-dev3re-results aria-busy="false">
					<?php echo $res['ids'] ? self::cards( $res['ids'], (int) $atts['columns'] ) : self::empty_state( __( 'No properties match your search', 'dev3-ai-real-estate' ), __( 'Try removing a filter or searching a wider area.', 'dev3-ai-real-estate' ) ); ?>
					<?php echo self::pagination( $res['page'], $res['pages'], remove_query_arg( 'pg' ) ); ?>
				</div>
				<?php if ( $map ) : ?>
					<div class="dev3re-slayout__map">
						<div class="dev3re-map" data-dev3re-map data-markers="<?php echo esc_attr( wp_json_encode( Search::markers( $args ) ) ); ?>" role="region" aria-label="<?php esc_attr_e( 'Map of results', 'dev3-ai-real-estate' ); ?>"></div>
						<label class="dev3re-map__toggle"><input type="checkbox" data-dev3re-map-move> <span><?php esc_html_e( 'Search as I move the map', 'dev3-ai-real-estate' ); ?></span></label>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Removable filter chips.
	 *
	 * @param array $chips Chips.
	 * @return string
	 */
	public static function chips( array $chips ) {
		if ( ! $chips ) {
			return '';
		}
		$html = '<span class="dev3re-chips__label">' . esc_html__( 'Searching for:', 'dev3-ai-real-estate' ) . '</span>';
		foreach ( $chips as $chip ) {
			$html .= '<button type="button" class="dev3re-chip" data-key="' . esc_attr( $chip['key'] ) . '" data-value="' . esc_attr( isset( $chip['value'] ) ? (string) $chip['value'] : '' ) . '"><span>' . esc_html( $chip['label'] ) . '</span>' . self::icon( 'x', 14 ) . '<span class="dev3re-sr">' . esc_html__( 'Remove filter', 'dev3-ai-real-estate' ) . '</span></button>';
		}
		return $html;
	}

	/**
	 * "12 properties".
	 *
	 * @param int $total Total.
	 * @return string
	 */
	public static function count_label( $total ) {
		/* translators: %s: number of properties */
		return sprintf( _n( '%s property', '%s properties', $total, 'dev3-ai-real-estate' ), number_format_i18n( $total ) );
	}

	/**
	 * Hero quick search that opens the search page.
	 *
	 * @param array $atts { listing_type }.
	 * @return string
	 */
	public static function quick_search( array $atts ) {
		$uid   = 'dev3re-q' . wp_rand( 100, 9999 );
		$deal  = isset( $atts['listing_type'] ) && in_array( $atts['listing_type'], array( 'sale', 'rent' ), true ) ? $atts['listing_type'] : '';
		$html  = '<form class="dev3re dev3re-quick" method="get" role="search" action="' . esc_url( Settings::page_url( 'search' ) ) . '">';
		$html .= '<fieldset class="dev3re-seg dev3re-seg--light"><legend class="dev3re-sr">' . esc_html__( 'Buy or rent', 'dev3-ai-real-estate' ) . '</legend>';
		foreach ( array( 'sale' => __( 'Buy', 'dev3-ai-real-estate' ), 'rent' => __( 'Rent', 'dev3-ai-real-estate' ) ) as $value => $label ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			$html .= '<label><input type="radio" name="listing_type" value="' . esc_attr( $value ) . '"' . checked( $deal ? $deal : 'sale', $value, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		$html .= '</fieldset><div class="dev3re-quick__row"><label class="dev3re-sr" for="' . esc_attr( $uid ) . '">' . esc_html__( 'Describe the home you are looking for', 'dev3-ai-real-estate' ) . '</label>';
		$html .= '<span class="dev3re-sbar__icon">' . self::icon( 'search' ) . '</span><input id="' . esc_attr( $uid ) . '" type="search" name="nl" placeholder="' . esc_attr__( 'City, neighbourhood or “2 bed flat near the park”', 'dev3-ai-real-estate' ) . '" enterkeyhint="search">';
		$html .= '<button class="dev3re-btn dev3re-btn--primary" type="submit">' . esc_html__( 'Search', 'dev3-ai-real-estate' ) . '</button></div></form>';
		return $html;
	}

	/**
	 * Properties grid block/shortcode.
	 *
	 * @param array $atts { listing_type, type, location, agent, featured, sort, limit, columns }.
	 * @return string
	 */
	public static function grid( array $atts ) {
		$args = Search::sanitize(
			array(
				'listing_type' => $atts['listing_type'] ?? '',
				'type'         => $atts['type'] ?? '',
				'location'     => $atts['location'] ?? '',
				'agent'        => $atts['agent'] ?? 0,
				'featured'     => ( $atts['featured'] ?? 'no' ),
				'sort'         => $atts['sort'] ?? 'newest',
				'per_page'     => $atts['limit'] ?? 6,
			)
		);
		$res  = Search::run( $args );
		if ( ! $res['ids'] ) {
			return '<div class="dev3re">' . self::empty_state( __( 'No properties to show yet', 'dev3-ai-real-estate' ) ) . '</div>';
		}
		return '<div class="dev3re dev3re-gridwrap">' . self::cards( $res['ids'], (int) ( $atts['columns'] ?? 3 ) ) . '</div>';
	}

	/**
	 * Agent mini card (single property sidebar, agency page, agents grid).
	 *
	 * @param int  $agent_id Agent.
	 * @param bool $contacts Show contact buttons.
	 * @return string
	 */
	public static function agent_card( $agent_id, $contacts = true ) {
		$a = Agents::get( $agent_id );
		if ( ! $a ) {
			return '';
		}
		$photo = $a['photo'] ? wp_get_attachment_image(
			$a['photo'],
			'thumbnail',
			false,
			array(
				'class'   => 'dev3re-agent__photo',
				'alt'     => '',
				'loading' => 'lazy',
			)
		) : '<span class="dev3re-agent__photo dev3re-agent__photo--ph">' . esc_html( mb_strtoupper( mb_substr( $a['name'], 0, 1 ) ) ) . '</span>';
		$html  = '<div class="dev3re-agent">' . $photo . '<div class="dev3re-agent__info"><a class="dev3re-agent__name" href="' . esc_url( $a['url'] ) . '">' . esc_html( $a['name'] ) . '</a>';
		$sub   = array_filter( array( $a['fields']['position'] ?? '', $a['agency'] ?? '' ) );
		if ( $sub ) {
			$html .= '<span class="dev3re-agent__sub">' . esc_html( implode( ' · ', $sub ) ) . '</span>';
		}
		$html .= '</div></div>';
		if ( $contacts ) {
			$btns = '';
			$tel  = $a['fields']['mobile'] ? $a['fields']['mobile'] : $a['fields']['phone'];
			if ( $tel ) {
				$btns .= '<a class="dev3re-btn dev3re-btn--ghost" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . self::icon( 'phone' ) . '<span>' . esc_html__( 'Call', 'dev3-ai-real-estate' ) . '</span></a>';
			}
			if ( $a['fields']['whatsapp'] ) {
				$btns .= '<a class="dev3re-btn dev3re-btn--ghost" href="' . esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $a['fields']['whatsapp'] ) ) . '" target="_blank" rel="noopener">' . self::icon( 'chat' ) . '<span>' . esc_html__( 'WhatsApp', 'dev3-ai-real-estate' ) . '</span></a>';
			}
			if ( $a['fields']['email'] ) {
				$btns .= '<a class="dev3re-btn dev3re-btn--ghost" href="mailto:' . esc_attr( $a['fields']['email'] ) . '">' . self::icon( 'mail' ) . '<span>' . esc_html__( 'Email', 'dev3-ai-real-estate' ) . '</span></a>';
			}
			$html .= $btns ? '<div class="dev3re-agent__contacts">' . $btns . '</div>' : '';
		}
		return $html;
	}

	/**
	 * Agents grid.
	 *
	 * @param array $atts { agency, limit }.
	 * @return string
	 */
	public static function agents( array $atts ) {
		$query = array(
			'post_type'      => Post_Types::AGENT,
			'post_status'    => 'publish',
			'posts_per_page' => min( 60, max( 1, (int) ( $atts['limit'] ?? 12 ) ) ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		if ( ! empty( $atts['agency'] ) ) {
			$query['meta_key']   = '_dev3re_agency_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
			$query['meta_value'] = (int) $atts['agency']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
		}
		$ids = get_posts( $query );
		if ( ! $ids ) {
			return '<div class="dev3re">' . self::empty_state( __( 'No agents yet', 'dev3-ai-real-estate' ) ) . '</div>';
		}
		$html = '<div class="dev3re dev3re-agents">';
		foreach ( $ids as $id ) {
			$count = Agents::listing_count( $id );
			$html .= '<div class="dev3re-agents__item">' . self::agent_card( $id, false ) . '<p class="dev3re-agents__count">' . esc_html( self::count_label( $count ) ) . '</p></div>';
		}
		return $html . '</div>';
	}

	/**
	 * Mortgage calculator (JS computes; inputs work as a form without JS too).
	 *
	 * @param array $atts { price }.
	 * @return string
	 */
	public static function mortgage( array $atts ) {
		$uid   = 'dev3re-m' . wp_rand( 100, 9999 );
		$price = isset( $atts['price'] ) ? max( 0, (float) $atts['price'] ) : 400000;
		$down  = (float) Settings::get( 'mortgage_down', 20 );
		$rate  = (float) Settings::get( 'mortgage_rate', 6.5 );
		$term  = (int) Settings::get( 'mortgage_term', 30 );
		$calc  = \Dev3\AiRealEstate\Search\Mortgage::breakdown( $price, $price * $down / 100, $rate, $term );
		$cfg   = array(
			'symbol'   => (string) Settings::get( 'currency_symbol', '$' ),
			'position' => (string) Settings::get( 'currency_position', 'before' ),
			'sep'      => (string) Settings::get( 'thousands_sep', ',' ),
		);
		ob_start();
		?>
		<form class="dev3re dev3re-mortgage" data-dev3re-mortgage="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>" onsubmit="return false">
			<div class="dev3re-mortgage__inputs">
				<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-p"><?php esc_html_e( 'Property price', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-p" type="number" inputmode="decimal" min="0" step="1000" name="price" value="<?php echo esc_attr( (string) round( $price ) ); ?>"></div>
				<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-d"><?php esc_html_e( 'Down payment (%)', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-d" type="number" inputmode="decimal" min="0" max="100" step="1" name="down" value="<?php echo esc_attr( (string) ( $down + 0 ) ); ?>"></div>
				<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-r"><?php esc_html_e( 'Interest rate (%)', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-r" type="number" inputmode="decimal" min="0" max="30" step="0.05" name="rate" value="<?php echo esc_attr( (string) ( $rate + 0 ) ); ?>"></div>
				<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-t"><?php esc_html_e( 'Term (years)', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-t" type="number" inputmode="numeric" min="1" max="50" step="1" name="term" value="<?php echo esc_attr( (string) $term ); ?>"></div>
			</div>
			<div class="dev3re-mortgage__out" aria-live="polite">
				<p class="dev3re-mortgage__label"><?php esc_html_e( 'Estimated monthly payment', 'dev3-ai-real-estate' ); ?></p>
				<p class="dev3re-mortgage__monthly" data-out="monthly"><?php echo esc_html( Property::money( $calc['monthly'] ) ); ?></p>
				<div class="dev3re-mortgage__bar" aria-hidden="true"><span data-out="bar" style="width:<?php echo esc_attr( (string) ( $calc['total_paid'] > 0 ? round( $calc['loan'] / $calc['total_paid'] * 100 ) : 100 ) ); ?>%"></span></div>
				<dl class="dev3re-mortgage__dl">
					<div><dt><span class="dev3re-dot dev3re-dot--p"></span><?php esc_html_e( 'Loan amount', 'dev3-ai-real-estate' ); ?></dt><dd data-out="loan"><?php echo esc_html( Property::money( $calc['loan'] ) ); ?></dd></div>
					<div><dt><span class="dev3re-dot dev3re-dot--i"></span><?php esc_html_e( 'Total interest', 'dev3-ai-real-estate' ); ?></dt><dd data-out="interest"><?php echo esc_html( Property::money( $calc['total_interest'] ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Total paid', 'dev3-ai-real-estate' ); ?></dt><dd data-out="total"><?php echo esc_html( Property::money( $calc['total_paid'] ) ); ?></dd></div>
				</dl>
				<p class="dev3re-mortgage__note"><?php esc_html_e( 'An estimate only. Taxes, insurance and fees are not included.', 'dev3-ai-real-estate' ); ?></p>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Favourites page shell (JS fills it from localStorage / account).
	 *
	 * @return string
	 */
	public static function favourites() {
		$ids = is_user_logged_in() ? \Dev3\AiRealEstate\Data\Favourites::get( get_current_user_id() ) : array();
		return '<div class="dev3re dev3re-favs" data-dev3re-favourites>' . ( $ids ? self::cards( $ids, 3 ) : self::empty_state( __( 'No saved properties yet', 'dev3-ai-real-estate' ), __( 'Tap the heart on any property to save it here.', 'dev3-ai-real-estate' ) ) ) . '</div>';
	}

	/**
	 * Compare page shell.
	 *
	 * @return string
	 */
	public static function compare_shell() {
		return '<div class="dev3re dev3re-comparepage" data-dev3re-comparepage>' . self::empty_state( __( 'Nothing to compare yet', 'dev3-ai-real-estate' ), __( 'Use the compare button on up to 4 properties.', 'dev3-ai-real-estate' ) ) . '</div>';
	}

	/**
	 * Compare table.
	 *
	 * @param int[] $ids Properties.
	 * @return string
	 */
	public static function compare_table( array $ids ) {
		$cards = array();
		foreach ( array_slice( $ids, 0, 4 ) as $id ) {
			$cards[] = Property::card( $id );
		}
		if ( ! $cards ) {
			return self::empty_state( __( 'Nothing to compare yet', 'dev3-ai-real-estate' ), __( 'Use the compare button on up to 4 properties.', 'dev3-ai-real-estate' ) );
		}
		$all_features = array();
		foreach ( $cards as $c ) {
			$all_features = array_merge( $all_features, $c['features'] );
		}
		$all_features = array_values( array_unique( $all_features ) );
		sort( $all_features );
		$rows = array(
			__( 'Price', 'dev3-ai-real-estate' )          => static fn( $c ) => esc_html( $c['price_html'] ),
			__( 'Listing', 'dev3-ai-real-estate' )        => static fn( $c ) => esc_html( self::deal_label( $c['listing_type'] ) . ( 'available' !== $c['market'] ? ' · ' . Property::market_label( $c['market'] ) : '' ) ),
			__( 'Type', 'dev3-ai-real-estate' )           => static fn( $c ) => esc_html( $c['type'] ? $c['type'] : '—' ),
			__( 'Bedrooms', 'dev3-ai-real-estate' )       => static fn( $c ) => esc_html( $c['beds'] ? (string) $c['beds'] : '—' ),
			__( 'Bathrooms', 'dev3-ai-real-estate' )      => static fn( $c ) => esc_html( $c['baths'] ? (string) ( $c['baths'] + 0 ) : '—' ),
			__( 'Living area', 'dev3-ai-real-estate' )    => static fn( $c ) => esc_html( $c['area'] ? $c['area'] : '—' ),
			__( 'Price per area', 'dev3-ai-real-estate' ) => static fn( $c ) => esc_html( $c['price_per'] ? $c['price_per'] : '—' ),
			__( 'Parking', 'dev3-ai-real-estate' )        => static fn( $c ) => esc_html( $c['garages'] ? (string) $c['garages'] : '—' ),
			__( 'Year built', 'dev3-ai-real-estate' )     => static fn( $c ) => esc_html( $c['year_built'] ? (string) $c['year_built'] : '—' ),
			__( 'Location', 'dev3-ai-real-estate' )       => static fn( $c ) => esc_html( $c['address'] ? $c['address'] : '—' ),
		);
		$html = '<div class="dev3re-cmp" role="region" aria-label="' . esc_attr__( 'Property comparison', 'dev3-ai-real-estate' ) . '" tabindex="0"><table class="dev3re-cmp__table"><thead><tr><td></td>';
		foreach ( $cards as $c ) {
			$html .= '<th scope="col"><div class="dev3re-cmp__head">' . ( $c['image'] ? '<img src="' . esc_url( $c['image'] ) . '" alt="" loading="lazy" width="320" height="200">' : '' ) . '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['title'] ) . '</a><button type="button" class="dev3re-btn dev3re-btn--link" data-dev3re-compare-remove="' . (int) $c['id'] . '">' . esc_html__( 'Remove', 'dev3-ai-real-estate' ) . '</button></div></th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $label => $cb ) {
			$html .= '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $cards as $c ) {
				$html .= '<td>' . $cb( $c ) . '</td>';
			}
			$html .= '</tr>';
		}
		foreach ( $all_features as $feature ) {
			$html .= '<tr><th scope="row">' . esc_html( $feature ) . '</th>';
			foreach ( $cards as $c ) {
				$has   = in_array( $feature, $c['features'], true );
				$html .= '<td class="' . ( $has ? 'is-yes' : 'is-no' ) . '">' . ( $has ? self::icon( 'check', 16 ) . '<span class="dev3re-sr">' . esc_html__( 'Yes', 'dev3-ai-real-estate' ) . '</span>' : '<span aria-hidden="true">—</span><span class="dev3re-sr">' . esc_html__( 'No', 'dev3-ai-real-estate' ) . '</span>' ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></div>';
	}

	/**
	 * Enquiry / viewing form.
	 *
	 * @param int $property_id Property (0 for agent page).
	 * @param int $agent_id    Agent.
	 * @return string
	 */
	public static function enquiry_form( $property_id, $agent_id = 0 ) {
		$uid  = 'dev3re-e' . wp_rand( 100, 9999 );
		$user = wp_get_current_user();
		$msg  = $property_id ? sprintf(
			/* translators: %s: property title */
			__( 'Hello, I am interested in "%s". Please get in touch.', 'dev3-ai-real-estate' ),
			get_the_title( $property_id )
		) : '';
		ob_start();
		?>
		<form class="dev3re-enquiry" data-dev3re-enquiry novalidate>
			<input type="hidden" name="property_id" value="<?php echo (int) $property_id; ?>">
			<input type="hidden" name="agent_id" value="<?php echo (int) $agent_id; ?>">
			<fieldset class="dev3re-seg dev3re-seg--block">
				<legend class="dev3re-sr"><?php esc_html_e( 'Request type', 'dev3-ai-real-estate' ); ?></legend>
				<label><input type="radio" name="kind" value="enquiry" checked><span><?php esc_html_e( 'Ask a question', 'dev3-ai-real-estate' ); ?></span></label>
				<?php if ( $property_id ) : ?>
					<label><input type="radio" name="kind" value="viewing"><span><?php esc_html_e( 'Book a viewing', 'dev3-ai-real-estate' ); ?></span></label>
				<?php endif; ?>
			</fieldset>
			<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-n"><?php esc_html_e( 'Your name', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-n" name="name" required autocomplete="name" value="<?php echo esc_attr( $user->exists() ? $user->display_name : '' ); ?>"></div>
			<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-e"><?php esc_html_e( 'Email', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-e" type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $user->exists() ? $user->user_email : '' ); ?>"></div>
			<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-p"><?php esc_html_e( 'Phone (optional)', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-p" type="tel" name="phone" autocomplete="tel"></div>
			<div class="dev3re-field" data-dev3re-viewing hidden><label for="<?php echo esc_attr( $uid ); ?>-d"><?php esc_html_e( 'Preferred date', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-d" type="date" name="viewing_at" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></div>
			<div class="dev3re-field"><label for="<?php echo esc_attr( $uid ); ?>-m"><?php esc_html_e( 'Message', 'dev3-ai-real-estate' ); ?></label><textarea id="<?php echo esc_attr( $uid ); ?>-m" name="message" rows="4"><?php echo esc_textarea( $msg ); ?></textarea></div>
			<div class="dev3re-hp" aria-hidden="true"><label for="<?php echo esc_attr( $uid ); ?>-w"><?php esc_html_e( 'Leave this empty', 'dev3-ai-real-estate' ); ?></label><input id="<?php echo esc_attr( $uid ); ?>-w" name="website" tabindex="-1" autocomplete="off"></div>
			<?php if ( Settings::get( 'leads_consent', true ) ) : ?>
				<label class="dev3re-consent"><input type="checkbox" name="consent" value="1" required><span><?php esc_html_e( 'I agree that my details are sent to the agent to answer my request.', 'dev3-ai-real-estate' ); ?></span></label>
			<?php endif; ?>
			<button type="submit" class="dev3re-btn dev3re-btn--primary dev3re-btn--block"><?php esc_html_e( 'Send message', 'dev3-ai-real-estate' ); ?></button>
			<p class="dev3re-enquiry__msg" role="status" data-dev3re-enquiry-msg></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
