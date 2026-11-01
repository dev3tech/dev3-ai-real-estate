<?php
/**
 * Agents and agencies.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers.
 */
class Agents {

	/**
	 * Agent profile fields: key => max length (0 = URL).
	 *
	 * @var array
	 */
	const AGENT_FIELDS = array(
		'position'  => 80,
		'email'     => 190,
		'phone'     => 40,
		'mobile'    => 40,
		'whatsapp'  => 40,
		'license'   => 60,
		'languages' => 120,
		'website'   => 0,
		'facebook'  => 0,
		'instagram' => 0,
		'linkedin'  => 0,
		'x'         => 0,
	);

	/**
	 * Agency fields.
	 *
	 * @var array
	 */
	const AGENCY_FIELDS = array(
		'email'   => 190,
		'phone'   => 40,
		'website' => 0,
		'address' => 190,
		'license' => 60,
	);

	/**
	 * Sanitizes a profile field.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param array  $defs  Definitions.
	 * @return string
	 */
	private static function clean( $key, $value, array $defs ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( 'email' === $key ) {
			$value = sanitize_email( $value );
			return is_email( $value ) ? $value : '';
		}
		if ( 0 === $defs[ $key ] ) {
			return esc_url_raw( $value, array( 'http', 'https' ) );
		}
		return mb_substr( sanitize_text_field( $value ), 0, $defs[ $key ] );
	}

	/**
	 * Creates/updates an agent.
	 *
	 * @param array $data { id?, name, bio, photo, user_id, agency_id, status, fields{} }.
	 * @return int|\WP_Error
	 */
	public static function save_agent( array $data ) {
		return self::save( Post_Types::AGENT, self::AGENT_FIELDS, $data );
	}

	/**
	 * Creates/updates an agency.
	 *
	 * @param array $data { id?, name, bio, photo, status, fields{} }.
	 * @return int|\WP_Error
	 */
	public static function save_agency( array $data ) {
		return self::save( Post_Types::AGENCY, self::AGENCY_FIELDS, $data );
	}

	/**
	 * Shared save.
	 *
	 * @param string $type CPT.
	 * @param array  $defs Field definitions.
	 * @param array  $data Data.
	 * @return int|\WP_Error
	 */
	private static function save( $type, array $defs, array $data ) {
		$id = isset( $data['id'] ) ? (int) $data['id'] : 0;
		if ( $id && get_post_type( $id ) !== $type ) {
			return new \WP_Error( 'dev3re_not_found', __( 'Profile not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) );
		}
		$name = isset( $data['name'] ) ? mb_substr( sanitize_text_field( (string) $data['name'] ), 0, 120 ) : ( $id ? get_the_title( $id ) : '' );
		if ( '' === trim( $name ) ) {
			return new \WP_Error( 'dev3re_name_required', __( 'Add a name.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) );
		}
		$post = array(
			'post_type'   => $type,
			'post_title'  => $name,
			'post_status' => isset( $data['status'] ) && in_array( $data['status'], array( 'publish', 'draft' ), true ) ? $data['status'] : ( $id ? get_post_status( $id ) : 'publish' ),
		);
		if ( isset( $data['bio'] ) ) {
			$post['post_content'] = wp_kses_post( (string) $data['bio'] );
		}
		if ( $id ) {
			$post['ID'] = $id;
			$result     = wp_update_post( wp_slash( $post ), true );
		} else {
			$result = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;
		foreach ( (array) ( $data['fields'] ?? array() ) as $key => $value ) {
			if ( isset( $defs[ $key ] ) ) {
				update_post_meta( $id, '_dev3re_' . $key, self::clean( $key, $value, $defs ) );
			}
		}
		if ( array_key_exists( 'photo', $data ) ) {
			$photo = (int) $data['photo'];
			if ( $photo > 0 && wp_attachment_is_image( $photo ) ) {
				set_post_thumbnail( $id, $photo );
			} else {
				delete_post_thumbnail( $id );
			}
		}
		if ( Post_Types::AGENT === $type ) {
			if ( array_key_exists( 'agency_id', $data ) ) {
				$agency = (int) $data['agency_id'];
				update_post_meta( $id, '_dev3re_agency_id', $agency && Post_Types::AGENCY === get_post_type( $agency ) ? $agency : 0 );
			}
			if ( array_key_exists( 'user_id', $data ) ) {
				self::link_user( $id, (int) $data['user_id'] );
			}
		}
		return $id;
	}

	/**
	 * Links a WP user to an agent profile (one profile per user) and gives them the agent role.
	 *
	 * @param int $agent_id Agent.
	 * @param int $user_id  User (0 unlinks).
	 */
	public static function link_user( $agent_id, $user_id ) {
		$previous = (int) get_post_meta( $agent_id, '_dev3re_user_id', true );
		if ( $previous && $previous !== $user_id ) {
			delete_user_meta( $previous, '_dev3re_agent_id' );
		}
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			delete_post_meta( $agent_id, '_dev3re_user_id' );
			return;
		}
		$other = self::agent_for_user( $user_id );
		if ( $other && $other !== $agent_id ) {
			delete_post_meta( $other, '_dev3re_user_id' );
		}
		update_post_meta( $agent_id, '_dev3re_user_id', $user_id );
		update_user_meta( $user_id, '_dev3re_agent_id', $agent_id );
		$user = get_userdata( $user_id );
		if ( $user && ! user_can( $user, 'edit_others_posts' ) && ! in_array( Post_Types::ROLE, (array) $user->roles, true ) ) {
			$user->add_role( Post_Types::ROLE );
		}
	}

	/**
	 * Agent profile id of a user (0 when none).
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function agent_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}
		$id = (int) get_user_meta( $user_id, '_dev3re_agent_id', true );
		if ( $id && Post_Types::AGENT === get_post_type( $id ) && (int) get_post_meta( $id, '_dev3re_user_id', true ) === $user_id ) {
			return $id;
		}
		return 0;
	}

	/**
	 * Whether the user may submit properties from the front end.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_agent( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		return self::agent_for_user( $user_id ) > 0 || in_array( Post_Types::ROLE, (array) $user->roles, true );
	}

	/**
	 * Returns the user's agent profile, creating one for agent-role users without a profile.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	public static function ensure_agent( $user_id ) {
		$id = self::agent_for_user( $user_id );
		if ( $id || ! self::is_agent( $user_id ) ) {
			return $id;
		}
		$user = get_userdata( $user_id );
		$id   = self::save_agent(
			array(
				'name'    => $user->display_name ? $user->display_name : $user->user_login,
				'user_id' => (int) $user_id,
				'fields'  => array( 'email' => $user->user_email ),
			)
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Agent or agency record.
	 *
	 * @param int $id Post.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, array( Post_Types::AGENT, Post_Types::AGENCY ), true ) ) {
			return null;
		}
		$is_agent = Post_Types::AGENT === $post->post_type;
		$defs     = $is_agent ? self::AGENT_FIELDS : self::AGENCY_FIELDS;
		$fields   = array();
		foreach ( array_keys( $defs ) as $key ) {
			$fields[ $key ] = (string) get_post_meta( $id, '_dev3re_' . $key, true );
		}
		$photo = (int) get_post_thumbnail_id( $id );
		$out   = array(
			'id'        => (int) $id,
			'kind'      => $is_agent ? 'agent' : 'agency',
			'name'      => $post->post_title,
			'bio'       => $post->post_content,
			'status'    => $post->post_status,
			'url'       => (string) get_permalink( $id ),
			'photo'     => $photo,
			'photo_url' => $photo ? (string) wp_get_attachment_image_url( $photo, 'medium' ) : '',
			'fields'    => $fields,
		);
		if ( $is_agent ) {
			$agency            = (int) get_post_meta( $id, '_dev3re_agency_id', true );
			$user              = (int) get_post_meta( $id, '_dev3re_user_id', true );
			$udata             = $user ? get_userdata( $user ) : null;
			$out['agency_id']  = $agency;
			$out['agency']     = $agency ? get_the_title( $agency ) : '';
			$out['user_id']    = $udata ? $user : 0;
			$out['user_label'] = $udata ? $udata->display_name . ' (' . $udata->user_email . ')' : '';
			$out['listings']   = self::listing_count( $id );
			$out['leads']      = Leads::count( array( 'agent_id' => $id ) );
		} else {
			$out['agents'] = count( self::agency_agents( $id ) );
		}
		return $out;
	}

	/**
	 * Published listings of an agent.
	 *
	 * @param int $agent_id Agent.
	 * @return int
	 */
	public static function listing_count( $agent_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::name( 'index' ) . " WHERE agent_id = %d AND status = 'publish'", $agent_id ) );
	}

	/**
	 * Agent ids of an agency.
	 *
	 * @param int $agency_id Agency.
	 * @return int[]
	 */
	public static function agency_agents( $agency_id ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Post_Types::AGENT,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_key'       => '_dev3re_agency_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key
					'meta_value'     => (int) $agency_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
				)
			)
		);
	}

	/**
	 * Email that receives a property's enquiries.
	 *
	 * @param int $agent_id Agent.
	 * @return string
	 */
	public static function email( $agent_id ) {
		$email = $agent_id ? (string) get_post_meta( $agent_id, '_dev3re_email', true ) : '';
		if ( ! $email && $agent_id ) {
			$user  = (int) get_post_meta( $agent_id, '_dev3re_user_id', true );
			$udata = $user ? get_userdata( $user ) : null;
			$email = $udata ? $udata->user_email : '';
		}
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Options for selects.
	 *
	 * @param string $type agent|agency.
	 * @return array
	 */
	public static function options( $type = 'agent' ) {
		$posts = get_posts(
			array(
				'post_type'      => 'agency' === $type ? Post_Types::AGENCY : Post_Types::AGENT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out   = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'value' => (int) $p->ID,
				'label' => $p->post_title,
			);
		}
		return $out;
	}
}
