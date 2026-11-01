<?php
/**
 * Enquiries / viewing requests (lead inbox).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

use Dev3\AiRealEstate\Emails\Mailer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom table, values prepared.

/**
 * Static repository.
 */
class Leads {

	const STATUSES = array( 'new', 'contacted', 'viewing', 'closed', 'spam' );

	/**
	 * Creates a lead from a visitor submission and sends the emails.
	 *
	 * @param array $data { property_id, agent_id, name, email, phone, message, kind, viewing_at, source }.
	 * @return int|\WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;
		$name  = mb_substr( sanitize_text_field( (string) ( $data['name'] ?? '' ) ), 0, 120 );
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( '' === $name || ! is_email( $email ) ) {
			return new \WP_Error( 'dev3re_lead_invalid', __( 'Please enter your name and a valid email address.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$property = (int) ( $data['property_id'] ?? 0 );
		if ( $property && ( Post_Types::PROPERTY !== get_post_type( $property ) || 'publish' !== get_post_status( $property ) ) ) {
			return new \WP_Error( 'dev3re_not_found', __( 'This property is no longer available.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) );
		}
		$agent = (int) ( $data['agent_id'] ?? 0 );
		if ( $property ) {
			$agent = (int) Property::value( $property, 'agent_id' );
		} elseif ( $agent && Post_Types::AGENT !== get_post_type( $agent ) ) {
			$agent = 0;
		}
		$kind = isset( $data['kind'] ) && 'viewing' === $data['kind'] ? 'viewing' : 'enquiry';
		$now  = current_time( 'mysql', true );
		$row  = array(
			'property_id' => $property,
			'agent_id'    => $agent,
			'name'        => $name,
			'email'       => $email,
			'phone'       => mb_substr( preg_replace( '/[^0-9+()\-.\s]/', '', (string) ( $data['phone'] ?? '' ) ), 0, 40 ),
			'message'     => mb_substr( sanitize_textarea_field( (string) ( $data['message'] ?? '' ) ), 0, 3000 ),
			'kind'        => $kind,
			'viewing_at'  => 'viewing' === $kind ? mb_substr( sanitize_text_field( (string) ( $data['viewing_at'] ?? '' ) ), 0, 40 ) : '',
			'status'      => 'new',
			'notes'       => '',
			'source'      => mb_substr( sanitize_key( (string) ( $data['source'] ?? 'property' ) ), 0, 40 ),
			'ip_hash'     => isset( $data['ip'] ) ? sha1( wp_salt( 'nonce' ) . (string) $data['ip'] ) : '',
			'created_at'  => $now,
			'updated_at'  => $now,
		);
		/**
		 * Filters a lead before it is stored (return WP_Error to reject, e.g. spam checks).
		 *
		 * @param array $row  Row.
		 * @param array $data Raw input.
		 */
		$row = apply_filters( 'dev3re_lead_before_insert', $row, $data );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! $wpdb->insert( Tables::name( 'leads' ), $row ) ) {
			return new \WP_Error( 'dev3re_db', __( 'Could not save your message. Please try again.', 'dev3-ai-real-estate' ), array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;
		Mailer::new_lead( self::get( $id ) );
		/**
		 * Fires after a new lead is stored.
		 *
		 * @param int   $id   Lead.
		 * @param array $row  Row.
		 */
		do_action( 'dev3re_lead_created', $id, $row );
		return $id;
	}

	/**
	 * One lead.
	 *
	 * @param int $id Lead.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'leads' ) . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Adds labels.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function shape( array $row ) {
		foreach ( array( 'id', 'property_id', 'agent_id' ) as $k ) {
			$row[ $k ] = (int) $row[ $k ];
		}
		unset( $row['ip_hash'] );
		$row['property']     = $row['property_id'] ? get_the_title( $row['property_id'] ) : '';
		$row['property_url'] = $row['property_id'] ? (string) get_permalink( $row['property_id'] ) : '';
		$row['agent']        = $row['agent_id'] ? get_the_title( $row['agent_id'] ) : '';
		$row['created']      = mysql2date( 'c', $row['created_at'] . ' +0000' );
		return $row;
	}

	/**
	 * WHERE clause.
	 *
	 * @param array $args Filters.
	 * @return string
	 */
	private static function where( array $args ) {
		global $wpdb;
		$where = array( '1=1' );
		if ( ! empty( $args['agent_id'] ) ) {
			$where[] = $wpdb->prepare( 'agent_id = %d', $args['agent_id'] );
		}
		if ( ! empty( $args['property_id'] ) ) {
			$where[] = $wpdb->prepare( 'property_id = %d', $args['property_id'] );
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		} elseif ( empty( $args['include_spam'] ) ) {
			$where[] = "status <> 'spam'";
		}
		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR message LIKE %s)', $like, $like, $like );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Paged list.
	 *
	 * @param array $args { agent_id, property_id, status, search, page, per_page }.
	 * @return array { items, total }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$per   = min( 200, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
		$where = self::where( $args );
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . Tables::name( 'leads' ) . " WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT " . (int) $per . ' OFFSET ' . (int) ( ( $page - 1 ) * $per ), ARRAY_A );
		return array(
			'items' => array_map( array( __CLASS__, 'shape' ), (array) $rows ),
			'total' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::name( 'leads' ) . " WHERE {$where}" ),
		);
	}

	/**
	 * Count.
	 *
	 * @param array $args Filters.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::name( 'leads' ) . ' WHERE ' . self::where( $args ) );
	}

	/**
	 * Updates status/notes.
	 *
	 * @param int   $id   Lead.
	 * @param array $data { status, notes }.
	 * @return array|\WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		if ( ! self::get( $id ) ) {
			return new \WP_Error( 'dev3re_not_found', __( 'Lead not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) );
		}
		$set = array( 'updated_at' => current_time( 'mysql', true ) );
		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ) {
			$set['status'] = $data['status'];
		}
		if ( isset( $data['notes'] ) ) {
			$set['notes'] = mb_substr( sanitize_textarea_field( (string) $data['notes'] ), 0, 3000 );
		}
		$wpdb->update( Tables::name( 'leads' ), $set, array( 'id' => (int) $id ) );
		return self::get( $id );
	}

	/**
	 * Deletes a lead.
	 *
	 * @param int $id Lead.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( Tables::name( 'leads' ), array( 'id' => (int) $id ) );
	}

	/**
	 * Counts by status.
	 *
	 * @param int $agent_id Optional agent.
	 * @return array
	 */
	public static function status_counts( $agent_id = 0 ) {
		global $wpdb;
		$where = $agent_id ? $wpdb->prepare( 'WHERE agent_id = %d', $agent_id ) : '';
		$rows  = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . Tables::name( 'leads' ) . " {$where} GROUP BY status", ARRAY_A );
		$out   = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Rows for CSV export.
	 *
	 * @param array $args Filters.
	 * @return array[]
	 */
	public static function export_rows( array $args = array() ) {
		$args['per_page'] = 200;
		$out              = array( array( 'id', 'date', 'status', 'kind', 'name', 'email', 'phone', 'property', 'agent', 'viewing', 'message', 'notes' ) );
		$page             = 1;
		do {
			$args['page'] = $page;
			$res          = self::query( $args );
			foreach ( $res['items'] as $l ) {
				$out[] = array( $l['id'], $l['created_at'], $l['status'], $l['kind'], $l['name'], $l['email'], $l['phone'], $l['property'], $l['agent'], $l['viewing_at'], $l['message'], $l['notes'] );
			}
			++$page;
			$more = count( $res['items'] ) === 200;
		} while ( $more && $page < 100 );
		return $out;
	}

	/**
	 * Personal data of an email (privacy exporter/eraser).
	 *
	 * @param string $email Email.
	 * @return array[]
	 */
	public static function by_email( $email ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'leads' ) . ' WHERE email = %s ORDER BY id ASC LIMIT 500', $email ), ARRAY_A );
	}

	/**
	 * Anonymises the leads of an email.
	 *
	 * @param string $email Email.
	 * @return int Rows.
	 */
	public static function anonymise( $email ) {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . Tables::name( 'leads' ) . " SET name = %s, email = '', phone = '', message = '' WHERE email = %s", __( '[deleted]', 'dev3-ai-real-estate' ), $email ) );
	}
}
