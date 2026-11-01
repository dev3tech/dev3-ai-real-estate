<?php
/**
 * Search index table: one row per property with numeric/geo columns for fast range queries.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom index table.

/**
 * Static helpers.
 */
class Index {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'save_post_' . Post_Types::PROPERTY, array( __CLASS__, 'on_save' ), 20 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms' ), 20 );
		add_action( 'deleted_post', array( __CLASS__, 'remove' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 20, 3 );
	}

	/**
	 * Save hook (covers edits made outside the plugin, e.g. Quick Edit).
	 *
	 * @param int $id Post.
	 */
	public static function on_save( $id ) {
		if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) {
			return;
		}
		self::sync( $id );
	}

	/**
	 * Term changes.
	 *
	 * @param int $id Object.
	 */
	public static function on_terms( $id ) {
		if ( Post_Types::PROPERTY === get_post_type( $id ) ) {
			self::sync( $id );
		}
	}

	/**
	 * Status transitions (trash, publish).
	 *
	 * @param string   $new_status New.
	 * @param string   $old_status Old.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( $post instanceof \WP_Post && Post_Types::PROPERTY === $post->post_type && $new_status !== $old_status ) {
			self::sync( $post->ID );
		}
	}

	/**
	 * Writes the index row of a property.
	 *
	 * @param int $id Property.
	 */
	public static function sync( $id ) {
		global $wpdb;
		$post = get_post( $id );
		if ( ! $post || Post_Types::PROPERTY !== $post->post_type ) {
			return;
		}
		if ( 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
			self::remove( $id );
			return;
		}
		$f     = Property::fields( $id );
		$terms = array();
		$names = array();
		foreach ( array( Post_Types::TYPE, Post_Types::LOCATION, Post_Types::FEATURE ) as $tax ) {
			$list          = wp_get_object_terms( $id, $tax );
			$list          = is_wp_error( $list ) ? array() : $list;
			$terms[ $tax ] = $list ? ',' . implode( ',', wp_list_pluck( $list, 'term_id' ) ) . ',' : '';
			foreach ( $list as $t ) {
				$names[] = $t->name;
			}
			if ( Post_Types::LOCATION === $tax ) {
				// Parents too, so a city search finds its neighbourhoods.
				$all = array();
				foreach ( $list as $t ) {
					$all[] = (int) $t->term_id;
					foreach ( get_ancestors( $t->term_id, $tax, 'taxonomy' ) as $parent ) {
						$all[] = (int) $parent;
					}
				}
				$terms[ $tax ] = $all ? ',' . implode( ',', array_unique( $all ) ) . ',' : '';
			}
		}
		$text = implode( ' ', array_filter( array( $post->post_title, $f['street'], $f['city'], $f['region'], $f['postcode'], $f['country'], $f['reference'], implode( ' ', $names ) ) ) );
		$row  = array(
			'post_id'      => (int) $id,
			'status'       => $post->post_status,
			'listing_type' => $f['listing_type'],
			'market'       => $f['market'],
			'price'        => (float) $f['price'],
			'beds'         => (int) $f['beds'],
			'baths'        => (float) $f['baths'],
			'area_m2'      => Property::area_m2( $f['area'], $f['area_unit'] ),
			'year_built'   => (int) $f['year_built'],
			'type_ids'     => mb_substr( $terms[ Post_Types::TYPE ], 0, 190 ),
			'location_ids' => mb_substr( $terms[ Post_Types::LOCATION ], 0, 190 ),
			'feature_ids'  => $terms[ Post_Types::FEATURE ],
			'featured'     => $f['featured'] ? 1 : 0,
			'agent_id'     => (int) $f['agent_id'],
			'author_id'    => (int) $post->post_author,
			'search_text'  => mb_strtolower( mb_substr( $text, 0, 2000 ) ),
			'published_at' => $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt ? $post->post_date_gmt : current_time( 'mysql', true ),
		);
		$wpdb->replace( Tables::name( 'index' ), $row );
		if ( '' !== $f['lat'] && '' !== $f['lng'] ) {
			$wpdb->update(
				Tables::name( 'index' ),
				array(
					'lat' => (float) $f['lat'],
					'lng' => (float) $f['lng'],
				),
				array( 'post_id' => (int) $id )
			);
		}
	}

	/**
	 * Removes a row.
	 *
	 * @param int $id Property.
	 */
	public static function remove( $id ) {
		global $wpdb;
		$wpdb->delete( Tables::name( 'index' ), array( 'post_id' => (int) $id ) );
	}

	/**
	 * Rebuilds the whole index.
	 *
	 * @return int Rows written.
	 */
	public static function rebuild() {
		global $wpdb;
		$table = Tables::name( 'index' );
		$wpdb->query( "DELETE FROM {$table}" );
		$ids = get_posts(
			array(
				'post_type'      => Post_Types::PROPERTY,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			self::sync( $id );
		}
		return count( $ids );
	}
}
