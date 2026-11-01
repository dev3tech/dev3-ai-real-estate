<?php
/**
 * Favourites of logged-in users (guests keep theirs in localStorage).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers.
 */
class Favourites {

	const META = '_dev3re_favourites';
	const MAX  = 200;

	/**
	 * Favourite ids of a user (published properties only).
	 *
	 * @param int $user_id User.
	 * @return int[]
	 */
	public static function get( $user_id ) {
		$ids = get_user_meta( (int) $user_id, self::META, true );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return Post_Types::PROPERTY === get_post_type( $id ) && 'publish' === get_post_status( $id );
				}
			)
		);
	}

	/**
	 * Adds or removes one property.
	 *
	 * @param int  $user_id User.
	 * @param int  $id      Property.
	 * @param bool $on      Add (true) or remove.
	 * @return int[] New list.
	 */
	public static function set( $user_id, $id, $on ) {
		$ids = self::get( $user_id );
		$ids = array_values( array_diff( $ids, array( (int) $id ) ) );
		if ( $on && Post_Types::PROPERTY === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			array_unshift( $ids, (int) $id );
		}
		$ids = array_slice( $ids, 0, self::MAX );
		update_user_meta( (int) $user_id, self::META, $ids );
		return $ids;
	}

	/**
	 * Merges ids from the browser (after login).
	 *
	 * @param int   $user_id User.
	 * @param int[] $ids     Ids.
	 * @return int[]
	 */
	public static function merge( $user_id, array $ids ) {
		$list = self::get( $user_id );
		foreach ( array_slice( array_map( 'intval', $ids ), 0, self::MAX ) as $id ) {
			if ( ! in_array( $id, $list, true ) && Post_Types::PROPERTY === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
				$list[] = $id;
			}
		}
		$list = array_slice( $list, 0, self::MAX );
		update_user_meta( (int) $user_id, self::META, $list );
		return $list;
	}
}
