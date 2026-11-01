<?php
/**
 * Saved searches with email alerts.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

use Dev3\AiRealEstate\Emails\Mailer;
use Dev3\AiRealEstate\Search\Search;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom table, values prepared.

/**
 * Static repository + alert runner.
 */
class Searches {

	const FREQUENCIES   = array( 'instant', 'daily', 'weekly' );
	const MAX_PER_EMAIL = 20;

	/**
	 * Saves a search.
	 *
	 * @param array $data { email, user_id, label, query (array), frequency }.
	 * @return array|\WP_Error Row (+ needs_confirm).
	 */
	public static function create( array $data ) {
		global $wpdb;
		$user_id = (int) ( $data['user_id'] ?? 0 );
		$user    = $user_id ? get_userdata( $user_id ) : null;
		$email   = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! $email && $user ) {
			$email = $user->user_email;
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'dev3re_email_required', __( 'Enter a valid email address for the alerts.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$args  = Search::sanitize( is_array( $data['query'] ?? null ) ? $data['query'] : array() );
		$query = Search::to_query( $args, false );
		$label = mb_substr( sanitize_text_field( (string) ( $data['label'] ?? '' ) ), 0, 190 );
		if ( '' === $label ) {
			$label = self::label_for( $args );
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::name( 'searches' ) . ' WHERE email = %s', $email ) );
		if ( $count >= 25 ) {
			return new \WP_Error( 'dev3re_search_limit', __( 'You have reached the limit of saved searches. Delete one first.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$frequency = in_array( $data['frequency'] ?? '', self::FREQUENCIES, true ) ? $data['frequency'] : 'daily';
		$confirmed = $user && strtolower( $user->user_email ) === strtolower( $email );
		$row       = array(
			'user_id'      => $user ? $user_id : 0,
			'email'        => $email,
			'label'        => $label,
			'query'        => wp_json_encode( $query ),
			'frequency'    => $frequency,
			'token'        => wp_generate_password( 32, false, false ),
			'confirmed'    => $confirmed ? 1 : 0,
			'last_post_id' => self::max_post_id(),
			'created_at'   => current_time( 'mysql', true ),
		);
		$wpdb->insert( Tables::name( 'searches' ), $row );
		$id  = (int) $wpdb->insert_id;
		$out = self::get( $id );
		if ( ! $out ) {
			return new \WP_Error( 'dev3re_db', __( 'Could not save the search.', 'dev3-ai-real-estate' ), array( 'status' => 500 ) );
		}
		if ( ! $confirmed ) {
			Mailer::search_confirm( $out, $row['token'] );
		}
		$out['needs_confirm'] = ! $confirmed;
		return $out;
	}

	/**
	 * Highest indexed property id (alerts only include listings added after the search was saved).
	 *
	 * @return int
	 */
	public static function max_post_id() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT MAX(post_id) FROM ' . Tables::name( 'index' ) );
	}

	/**
	 * A readable label ("For sale · House · Springfield · Up to $500,000").
	 *
	 * @param array $args Sanitized args.
	 * @return string
	 */
	public static function label_for( array $args ) {
		$chips = wp_list_pluck( Search::describe( $args ), 'label' );
		return $chips ? mb_substr( implode( ' · ', $chips ), 0, 190 ) : __( 'All properties', 'dev3-ai-real-estate' );
	}

	/**
	 * One row (token removed).
	 *
	 * @param int  $id         Id.
	 * @param bool $with_token Include the token.
	 * @return array|null
	 */
	public static function get( $id, $with_token = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'searches' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? self::shape( $row, $with_token ) : null;
	}

	/**
	 * Shapes a row.
	 *
	 * @param array $row        Row.
	 * @param bool  $with_token Keep token.
	 * @return array
	 */
	private static function shape( array $row, $with_token = false ) {
		$row['id']           = (int) $row['id'];
		$row['user_id']      = (int) $row['user_id'];
		$row['confirmed']    = (bool) $row['confirmed'];
		$row['last_post_id'] = (int) $row['last_post_id'];
		$row['sent_count']   = (int) $row['sent_count'];
		$row['query']        = json_decode( (string) $row['query'], true );
		$row['query']        = is_array( $row['query'] ) ? $row['query'] : array();
		$row['url']          = add_query_arg( array_map( static fn( $v ) => is_array( $v ) ? implode( ',', $v ) : $v, $row['query'] ), \Dev3\AiRealEstate\Settings::page_url( 'search' ) );
		if ( ! $with_token ) {
			unset( $row['token'] );
		}
		return $row;
	}

	/**
	 * Paged list.
	 *
	 * @param array $args { user_id, email, page, per_page, search }.
	 * @return array { items, total }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$where = array( '1=1' );
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}
		if ( ! empty( $args['email'] ) ) {
			$where[] = $wpdb->prepare( 'email = %s', $args['email'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(email LIKE %s OR label LIKE %s)', $like, $like );
		}
		$where = implode( ' AND ', $where );
		$per   = min( 200, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . Tables::name( 'searches' ) . " WHERE {$where} ORDER BY id DESC LIMIT {$per} OFFSET " . ( ( $page - 1 ) * $per ), ARRAY_A );
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::name( 'searches' ) . " WHERE {$where}" ),
		);
	}

	/**
	 * Deletes a search.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( Tables::name( 'searches' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Confirms a guest's search (double opt-in).
	 *
	 * @param int    $id    Id.
	 * @param string $token Token.
	 * @return bool
	 */
	public static function confirm( $id, $token ) {
		global $wpdb;
		$row = self::get( $id, true );
		if ( ! $row || ! hash_equals( (string) $row['token'], (string) $token ) ) {
			return false;
		}
		$wpdb->update( Tables::name( 'searches' ), array( 'confirmed' => 1 ), array( 'id' => (int) $id ) );
		return true;
	}

	/**
	 * Unsubscribes (deletes) with the email token.
	 *
	 * @param int    $id    Id.
	 * @param string $token Token.
	 * @return bool
	 */
	public static function unsubscribe( $id, $token ) {
		$row = self::get( $id, true );
		if ( ! $row || ! hash_equals( (string) $row['token'], (string) $token ) ) {
			return false;
		}
		return self::delete( $id );
	}

	/**
	 * Whether a search is due for an alert.
	 *
	 * @param array $row Row.
	 * @param int   $now Timestamp.
	 * @return bool
	 */
	public static function is_due( array $row, $now ) {
		if ( empty( $row['confirmed'] ) ) {
			return false;
		}
		if ( 'instant' === $row['frequency'] || empty( $row['last_sent'] ) ) {
			return true;
		}
		$last = strtotime( $row['last_sent'] . ' UTC' );
		$gap  = 'weekly' === $row['frequency'] ? 6.5 * DAY_IN_SECONDS : 20 * HOUR_IN_SECONDS;
		return ( $now - $last ) >= $gap;
	}

	/**
	 * Sends due alerts (hourly cron).
	 *
	 * @param bool $force Ignore schedules (admin "send now").
	 * @return array { checked, sent }
	 */
	public static function run_alerts( $force = false ) {
		global $wpdb;
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . Tables::name( 'searches' ) . ' WHERE confirmed = 1 ORDER BY id ASC LIMIT 2000', ARRAY_A );
		$now   = time();
		$sent  = 0;
		$check = 0;
		foreach ( (array) $rows as $raw ) {
			$row = self::shape( $raw, true );
			if ( ! $force && ! self::is_due( $row, $now ) ) {
				continue;
			}
			++$check;
			$args             = Search::sanitize( $row['query'] );
			$args['after_id'] = $row['last_post_id'];
			$args['per_page'] = self::MAX_PER_EMAIL;
			$args['page']     = 1;
			$args['sort']     = 'newest';
			$res              = Search::run( $args );
			$update           = array( 'last_sent' => current_time( 'mysql', true ) );
			if ( $res['ids'] ) {
				if ( Mailer::alert_digest( $row, $res['ids'], $res['total'] ) ) {
					++$sent;
					$update['sent_count'] = $row['sent_count'] + 1;
				}
				$update['last_post_id'] = max( $res['ids'] );
				if ( $res['total'] > count( $res['ids'] ) ) {
					$update['last_post_id'] = self::max_post_id();
				}
			} elseif ( 'instant' === $row['frequency'] ) {
				// Nothing new: keep the last real send time so daily/weekly logic is unaffected.
				unset( $update['last_sent'] );
			}
			if ( $update ) {
				$wpdb->update( Tables::name( 'searches' ), $update, array( 'id' => $row['id'] ) );
			}
		}
		return array(
			'checked' => $check,
			'sent'    => $sent,
		);
	}
}
