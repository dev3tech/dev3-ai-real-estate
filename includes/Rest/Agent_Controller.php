<?php
/**
 * Agent routes (front-end submission + dashboard). Agents only see and change their own data.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Rest;

use Dev3\AiRealEstate\Ai\Describer;
use Dev3\AiRealEstate\Ai\Neighbourhood;
use Dev3\AiRealEstate\Core3\Core\Rate_Limiter;
use Dev3\AiRealEstate\Core3\Rest\Base_Controller;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Front\Dashboard;
use Dev3\AiRealEstate\Front\Submission;
use Dev3\AiRealEstate\Search\Geo;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under dev3re/v1/agent.
 */
class Agent_Controller extends Base_Controller {

	const MAX_UPLOAD = 10485760;

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		$routes = array(
			'/agent/properties'                    => array( 'GET,POST', 'properties' ),
			'/agent/properties/(?P<id>\d+)'        => array( 'GET,POST,DELETE', 'property' ),
			'/agent/properties/(?P<id>\d+)/market' => array( 'POST', 'market' ),
			'/agent/leads'                         => array( 'GET', 'leads' ),
			'/agent/leads/(?P<id>\d+)'             => array( 'POST', 'lead' ),
			'/agent/upload'                        => array( 'POST', 'upload' ),
			'/agent/geocode'                       => array( 'POST', 'geocode' ),
			'/agent/ai/describe'                   => array( 'POST', 'describe' ),
			'/agent/ai/neighbourhood'              => array( 'POST', 'neighbourhood' ),
			'/agent/profile'                       => array( 'POST', 'profile' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, 'is_agent' ),
				)
			);
		}
		register_rest_route(
			self::NS,
			'/agent/become',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'become' ),
				'permission_callback' => array( $this, 'logged_in' ),
			)
		);
	}

	/**
	 * Agent (or editor) with a valid nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function is_agent( \WP_REST_Request $request ) {
		$ok = $this->logged_in( $request );
		if ( true !== $ok ) {
			return $ok;
		}
		if ( ! Settings::get( 'submission_enabled', true ) || ( ! Agents::is_agent( get_current_user_id() ) && ! current_user_can( 'edit_others_posts' ) ) ) {
			return new \WP_Error( 'dev3re_forbidden', __( 'Only agents can do that.', 'dev3-ai-real-estate' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Logged in + nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function logged_in( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! $this->nonce_ok( $request ) ) {
			return new \WP_Error( 'dev3re_login_required', __( 'Please log in first.', 'dev3-ai-real-estate' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Builds Property::save() data from a form payload.
	 *
	 * @param array $p Payload.
	 * @return array
	 */
	public static function payload( array $p ) {
		$fields = isset( $p['fields'] ) && is_array( $p['fields'] ) ? $p['fields'] : array();
		if ( isset( $fields['highlights'] ) && is_string( $fields['highlights'] ) ) {
			$fields['highlights'] = preg_split( '/\r?\n/', $fields['highlights'] );
		}
		if ( isset( $fields['nearby'] ) && is_string( $fields['nearby'] ) ) {
			$fields['nearby'] = preg_split( '/\r?\n/', $fields['nearby'] );
		}
		$data = array(
			'title'  => (string) ( $p['title'] ?? '' ),
			'fields' => $fields,
		);
		if ( isset( $p['content'] ) ) {
			$content         = (string) $p['content'];
			$data['content'] = preg_match( '/<\s*(p|ul|ol|h[2-6]|br)\b/i', $content ) ? $content : Describer::to_html( $content );
		}
		if ( isset( $p['excerpt'] ) ) {
			$data['excerpt'] = (string) $p['excerpt'];
		}
		foreach ( array( 'type', 'features', 'locations' ) as $key ) {
			if ( isset( $p[ $key ] ) ) {
				$data[ $key ] = array_filter( array_map( 'intval', (array) $p[ $key ] ) );
			}
		}
		if ( isset( $p['status'] ) ) {
			$data['status'] = (string) $p['status'];
		}
		return $data;
	}

	/**
	 * GET: my listings; POST: create.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function properties( \WP_REST_Request $request ) {
		$user = get_current_user_id();
		if ( 'GET' === $request->get_method() ) {
			$items = array();
			foreach ( Dashboard::property_ids( $user ) as $id ) {
				$items[] = array_merge( Property::card( $id ), array( 'status' => get_post_status( $id ) ) );
			}
			return $this->respond( array( 'items' => $items ) );
		}
		$data = self::payload( (array) $request->get_json_params() );
		unset( $data['id'] );
		$id = Property::save( $data, $user, 'agent' );
		if ( is_wp_error( $id ) ) {
			return $this->respond( $id );
		}
		return $this->respond( $this->saved( $id ), 201 );
	}

	/**
	 * Response after a save.
	 *
	 * @param int $id Property.
	 * @return array
	 */
	private function saved( $id ) {
		$status = get_post_status( $id );
		return array(
			'id'      => (int) $id,
			'status'  => $status,
			'url'     => (string) get_permalink( $id ),
			'message' => 'publish' === $status ? __( 'Your property is live.', 'dev3-ai-real-estate' ) : ( 'pending' === $status ? __( 'Thanks! Your property was sent for review.', 'dev3-ai-real-estate' ) : __( 'Draft saved.', 'dev3-ai-real-estate' ) ),
		);
	}

	/**
	 * GET/POST/DELETE one listing (owner only).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function property( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$user = get_current_user_id();
		if ( ! Submission::can_edit( $id, $user ) ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Property not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		if ( 'GET' === $request->get_method() ) {
			return $this->respond( Property::get( $id ) );
		}
		if ( 'DELETE' === $request->get_method() ) {
			wp_trash_post( $id );
			return $this->respond( array( 'deleted' => true ) );
		}
		$data       = self::payload( (array) $request->get_json_params() );
		$data['id'] = $id;
		$saved      = Property::save( $data, $user, 'agent' );
		return $this->respond( is_wp_error( $saved ) ? $saved : $this->saved( $saved ) );
	}

	/**
	 * POST market status { market }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function market( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! Submission::can_edit( $id, get_current_user_id() ) ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Property not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		update_post_meta( $id, Property::meta_key( 'market' ), Property::clean( 'market', (string) $request->get_param( 'market' ) ) );
		\Dev3\AiRealEstate\Data\Index::sync( $id );
		return $this->respond( array( 'market' => Property::value( $id, 'market' ) ) );
	}

	/**
	 * GET my leads.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function leads( \WP_REST_Request $request ) {
		$agent = Agents::agent_for_user( get_current_user_id() );
		if ( ! $agent ) {
			return $this->respond(
				array(
					'items' => array(),
					'total' => 0,
				)
			);
		}
		return $this->respond(
			Leads::query(
				array(
					'agent_id' => $agent,
					'status'   => (string) $request->get_param( 'status' ),
					'page'     => (int) $request->get_param( 'page' ),
				)
			)
		);
	}

	/**
	 * POST one of my leads { status, notes }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function lead( \WP_REST_Request $request ) {
		$lead  = Leads::get( (int) $request['id'] );
		$agent = Agents::agent_for_user( get_current_user_id() );
		if ( ! $lead || ! $agent || $lead['agent_id'] !== $agent ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Lead not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		return $this->respond( Leads::update( $lead['id'], (array) $request->get_json_params() ) );
	}

	/**
	 * POST upload (multipart `file`): images only, owned by the agent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function upload( \WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! empty( $files['file']['error'] ) ) {
			return $this->respond( new \WP_Error( 'dev3re_upload', __( 'Choose a photo to upload.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		if ( (int) $files['file']['size'] > self::MAX_UPLOAD ) {
			return $this->respond( new \WP_Error( 'dev3re_upload_size', __( 'Photos can be up to 10 MB.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		$check = wp_check_filetype_and_ext( $files['file']['tmp_name'], $files['file']['name'] );
		if ( ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return $this->respond( new \WP_Error( 'dev3re_upload_type', __( 'Upload JPEG, PNG or WebP photos.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		if ( ! Rate_Limiter::hit( 'upload', (string) get_current_user_id(), 60 ) ) {
			return $this->respond( new \WP_Error( 'dev3re_rate_limited', __( 'Too many uploads. Please wait a moment.', 'dev3-ai-real-estate' ), array( 'status' => 429 ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$_FILES['dev3re_upload'] = $files['file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST nonce checked in permission callback.
		$id                      = media_handle_upload( 'dev3re_upload', 0, array(), array( 'test_form' => false ) );
		unset( $_FILES['dev3re_upload'] );
		if ( is_wp_error( $id ) ) {
			return $this->respond( $id );
		}
		return $this->respond(
			array(
				'id'  => (int) $id,
				'url' => (string) wp_get_attachment_image_url( $id, 'medium' ),
			),
			201
		);
	}

	/**
	 * POST geocode { address }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function geocode( \WP_REST_Request $request ) {
		$geo = Geo::geocode( mb_substr( sanitize_text_field( (string) $request->get_param( 'address' ) ), 0, 300 ) );
		if ( ! $geo ) {
			return $this->respond( new \WP_Error( 'dev3re_geocode', __( 'Address not found. Drag the pin to the right spot.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		return $this->respond( $geo );
	}

	/**
	 * POST ai/describe { listing, mode: ai|template }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function describe( \WP_REST_Request $request ) {
		$p       = (array) $request->get_json_params();
		$listing = self::payload( (array) ( $p['listing'] ?? array() ) );
		if ( 'template' === ( $p['mode'] ?? '' ) ) {
			return $this->respond( Describer::template( $listing ) );
		}
		if ( ! Settings::get( 'ai_describe', true ) ) {
			return $this->respond( new \WP_Error( 'dev3re_ai_off', __( 'This feature is turned off.', 'dev3-ai-real-estate' ), array( 'status' => 403 ) ) );
		}
		if ( ! Rate_Limiter::hit( 'agent_ai', (string) get_current_user_id(), 6 ) ) {
			return $this->respond( new \WP_Error( 'dev3re_rate_limited', __( 'Too many requests. Please wait a moment.', 'dev3-ai-real-estate' ), array( 'status' => 429 ) ) );
		}
		$trusted = array();
		$pid     = (int) ( $p['id'] ?? 0 );
		if ( $pid && Submission::can_edit( $pid, get_current_user_id() ) ) {
			$trusted = (array) Property::value( $pid, 'gallery' );
		}
		return $this->respond( Describer::generate( $listing, array( 'trusted' => $trusted ), 'submission', get_current_user_id() ) );
	}

	/**
	 * POST ai/neighbourhood { fields, ai }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function neighbourhood( \WP_REST_Request $request ) {
		$p      = (array) $request->get_json_params();
		$use_ai = ! empty( $p['ai'] ) && Settings::get( 'ai_neighbourhood', true );
		if ( $use_ai && ! Rate_Limiter::hit( 'agent_ai', (string) get_current_user_id(), 6 ) ) {
			return $this->respond( new \WP_Error( 'dev3re_rate_limited', __( 'Too many requests. Please wait a moment.', 'dev3-ai-real-estate' ), array( 'status' => 429 ) ) );
		}
		return $this->respond( Neighbourhood::generate( (array) ( $p['fields'] ?? array() ), 'submission', $use_ai ) );
	}

	/**
	 * POST profile { name, bio, fields }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function profile( \WP_REST_Request $request ) {
		$agent = Agents::ensure_agent( get_current_user_id() );
		if ( ! $agent ) {
			return $this->respond( new \WP_Error( 'dev3re_forbidden', __( 'Only agents can do that.', 'dev3-ai-real-estate' ), array( 'status' => 403 ) ) );
		}
		$p     = (array) $request->get_json_params();
		$saved = Agents::save_agent(
			array(
				'id'     => $agent,
				'name'   => (string) ( $p['name'] ?? '' ),
				'bio'    => wpautop( esc_html( (string) ( $p['bio'] ?? '' ) ) ),
				'fields' => array_intersect_key( (array) ( $p['fields'] ?? array() ), array_flip( array( 'position', 'email', 'phone', 'mobile', 'whatsapp', 'license', 'languages', 'website', 'facebook', 'instagram', 'linkedin', 'x' ) ) ),
			)
		);
		return $this->respond( is_wp_error( $saved ) ? $saved : Agents::get( $saved ) );
	}

	/**
	 * POST become: self-service agent profile (when allowed).
	 *
	 * @return \WP_REST_Response
	 */
	public function become() {
		if ( ! Settings::get( 'agent_signup', false ) ) {
			return $this->respond( new \WP_Error( 'dev3re_forbidden', __( 'Agent sign-up is closed.', 'dev3-ai-real-estate' ), array( 'status' => 403 ) ) );
		}
		$user = wp_get_current_user();
		if ( ! in_array( Post_Types::ROLE, (array) $user->roles, true ) ) {
			$user->add_role( Post_Types::ROLE );
		}
		return $this->respond( array( 'agent_id' => Agents::ensure_agent( $user->ID ) ) );
	}
}
