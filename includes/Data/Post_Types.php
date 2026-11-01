<?php
/**
 * Post types, taxonomies, the agent role and default terms.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Registration.
 */
class Post_Types {

	const PROPERTY = 'dev3re_property';
	const AGENT    = 'dev3re_agent';
	const AGENCY   = 'dev3re_agency';
	const TYPE     = 'dev3re_type';
	const LOCATION = 'dev3re_location';
	const FEATURE  = 'dev3re_feature';
	const ROLE     = 'dev3re_agent';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
	}

	/**
	 * Registers CPTs + taxonomies.
	 */
	public static function register() {
		$common = array(
			'public'           => true,
			'show_ui'          => true,
			'show_in_menu'     => false,
			'show_in_rest'     => false,
			'map_meta_cap'     => true,
			'menu_icon'        => 'dashicons-admin-home',
			'supports'         => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'has_archive'      => false,
			'hierarchical'     => false,
			'query_var'        => true,
			'can_export'       => true,
			'delete_with_user' => false,
		);
		register_post_type(
			self::PROPERTY,
			array_merge(
				$common,
				array(
					'labels'      => array(
						'name'          => __( 'Properties', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Property', 'dev3-ai-real-estate' ),
						'edit_item'     => __( 'Edit property', 'dev3-ai-real-estate' ),
						'view_item'     => __( 'View property', 'dev3-ai-real-estate' ),
						'all_items'     => __( 'All properties', 'dev3-ai-real-estate' ),
					),
					'rewrite'     => array(
						'slug'       => _x( 'property', 'URL slug', 'dev3-ai-real-estate' ),
						'with_front' => false,
					),
					'has_archive' => _x( 'properties', 'URL slug', 'dev3-ai-real-estate' ),
				)
			)
		);
		register_post_type(
			self::AGENT,
			array_merge(
				$common,
				array(
					'labels'   => array(
						'name'          => __( 'Agents', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Agent', 'dev3-ai-real-estate' ),
					),
					'supports' => array( 'title', 'editor', 'thumbnail' ),
					'rewrite'  => array(
						'slug'       => _x( 'agent', 'URL slug', 'dev3-ai-real-estate' ),
						'with_front' => false,
					),
				)
			)
		);
		register_post_type(
			self::AGENCY,
			array_merge(
				$common,
				array(
					'labels'   => array(
						'name'          => __( 'Agencies', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Agency', 'dev3-ai-real-estate' ),
					),
					'supports' => array( 'title', 'editor', 'thumbnail' ),
					'rewrite'  => array(
						'slug'       => _x( 'agency', 'URL slug', 'dev3-ai-real-estate' ),
						'with_front' => false,
					),
				)
			)
		);
		$tax_common = array(
			'public'            => true,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_in_rest'      => false,
			'show_admin_column' => false,
			'query_var'         => true,
		);
		register_taxonomy(
			self::TYPE,
			array( self::PROPERTY ),
			array_merge(
				$tax_common,
				array(
					'hierarchical' => true,
					'labels'       => array(
						'name'          => __( 'Property types', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Property type', 'dev3-ai-real-estate' ),
					),
					'rewrite'      => array( 'slug' => _x( 'property-type', 'URL slug', 'dev3-ai-real-estate' ) ),
				)
			)
		);
		register_taxonomy(
			self::LOCATION,
			array( self::PROPERTY ),
			array_merge(
				$tax_common,
				array(
					'hierarchical' => true,
					'labels'       => array(
						'name'          => __( 'Locations', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Location', 'dev3-ai-real-estate' ),
					),
					'rewrite'      => array( 'slug' => _x( 'property-location', 'URL slug', 'dev3-ai-real-estate' ) ),
				)
			)
		);
		register_taxonomy(
			self::FEATURE,
			array( self::PROPERTY ),
			array_merge(
				$tax_common,
				array(
					'hierarchical' => false,
					'labels'       => array(
						'name'          => __( 'Features', 'dev3-ai-real-estate' ),
						'singular_name' => __( 'Feature', 'dev3-ai-real-estate' ),
					),
					'rewrite'      => array( 'slug' => _x( 'property-feature', 'URL slug', 'dev3-ai-real-estate' ) ),
				)
			)
		);
	}

	/**
	 * Agent role (activation).
	 */
	public static function add_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role(
				self::ROLE,
				__( 'Real estate agent', 'dev3-ai-real-estate' ),
				array(
					'read'         => true,
					'upload_files' => true,
				)
			);
		}
	}

	/**
	 * Default property types and features (activation, once).
	 */
	public static function default_terms() {
		if ( get_option( 'dev3re_terms_seeded' ) ) {
			return;
		}
		$types    = array( 'House', 'Apartment', 'Villa', 'Townhouse', 'Condo', 'Land', 'Commercial', 'Office' );
		$features = array( 'Air conditioning', 'Balcony', 'Garden', 'Garage', 'Swimming pool', 'Gym', 'Elevator', 'Fireplace', 'Furnished', 'Pet friendly', 'Sea view', 'Solar panels', 'Security', 'Storage', 'Terrace', 'Wheelchair access' );
		foreach ( $types as $name ) {
			if ( ! term_exists( $name, self::TYPE ) ) {
				wp_insert_term( $name, self::TYPE );
			}
		}
		foreach ( $features as $name ) {
			if ( ! term_exists( $name, self::FEATURE ) ) {
				wp_insert_term( $name, self::FEATURE );
			}
		}
		update_option( 'dev3re_terms_seeded', 1, false );
	}

	/**
	 * Terms of a taxonomy as [ { id, name, slug, parent, count } ].
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	public static function terms( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'     => (int) $t->term_id,
				'name'   => $t->name,
				'slug'   => $t->slug,
				'parent' => (int) $t->parent,
				'count'  => (int) $t->count,
			);
		}
		return $out;
	}
}
