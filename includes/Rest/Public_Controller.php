<?php
/**
 * Public (storefront) routes: search, natural-language search, enquiries, saved searches, favourites.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Rest;

use Dev3\AiRealEstate\Ai\Nl_Search;
use Dev3\AiRealEstate\Core3\Core\Rate_Limiter;
use Dev3\AiRealEstate\Core3\Rest\Base_Controller;
use Dev3\AiRealEstate\Data\Favourites;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Searches;
use Dev3\AiRealEstate\Front\Templates;
use Dev3\AiRealEstate\Search\Search;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under dev3re/v1.
 */
class Public_Controller extends Base_Controller {

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		$routes = array(
			'/properties'           => array( 'GET', 'properties', fn( $r ) => $this->public_permission( $r, 'search', 90 ) ),
			'/properties/by-ids'    => array( 'GET', 'by_ids', fn( $r ) => $this->public_permission( $r, 'search', 90 ) ),
			'/nl-search'            => array( 'POST', 'nl_search', fn( $r ) => $this->public_permission( $r, 'nl', 15 ) ),
			'/leads'                => array( 'POST', 'lead', fn( $r ) => $this->public_permission( $r, 'lead', 5 ) ),
			'/searches'             => array( 'POST', 'save_search', fn( $r ) => $this->public_permission( $r, 'searches', 6 ) ),
			'/searches/(?P<id>\d+)' => array( 'DELETE', 'delete_search', array( $this, 'logged_in' ) ),
			'/favourites'           => array( 'GET,POST', 'favourites', array( $this, 'logged_in' ) ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => $def[2],
				)
			);
		}
	}

	/**
	 * Logged-in user with a valid nonce.
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
	 * GET /properties: cards HTML + markers + chips for the search page.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function properties( \WP_REST_Request $request ) {
		$raw         = $request->get_query_params();
		$raw['page'] = isset( $raw['pg'] ) ? (int) $raw['pg'] : (int) ( $raw['page'] ?? 1 );
		$args        = Search::sanitize( $raw );
		$res         = Search::run( $args );
		$columns     = isset( $raw['columns'] ) ? min( 4, max( 1, (int) $raw['columns'] ) ) : 2;
		$base        = isset( $raw['base'] ) ? esc_url_raw( (string) $raw['base'] ) : Settings::page_url( 'search' );
		$query       = Search::to_query( $args );
		$base        = add_query_arg( array_map( static fn( $v ) => is_array( $v ) ? implode( ',', $v ) : $v, $query ), wp_validate_redirect( $base, Settings::page_url( 'search' ) ) );
		$out         = array(
			'html'       => $res['ids'] ? Templates::cards( $res['ids'], $columns ) : Templates::empty_state( __( 'No properties match your search', 'dev3-ai-real-estate' ), __( 'Try removing a filter or searching a wider area.', 'dev3-ai-real-estate' ) ),
			'pagination' => Templates::pagination( $res['page'], $res['pages'], $base ),
			'total'      => $res['total'],
			'count'      => Templates::count_label( $res['total'] ),
			'page'       => $res['page'],
			'pages'      => $res['pages'],
			'chips'      => Templates::chips( Search::describe( $args ) ),
			'query'      => $query,
		);
		if ( ! empty( $raw['markers'] ) ) {
			$out['markers'] = Search::markers( $args );
		}
		return $this->respond( $out );
	}

	/**
	 * GET /properties/by-ids?ids=1,2&format=cards|compare.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function by_ids( \WP_REST_Request $request ) {
		$ids    = array_slice( array_values( array_filter( array_map( 'intval', explode( ',', (string) $request->get_param( 'ids' ) ) ) ) ), 0, 60 );
		$ids    = array_values(
			array_filter(
				$ids,
				static fn( $id ) => Post_Types::PROPERTY === get_post_type( $id ) && 'publish' === get_post_status( $id )
			)
		);
		$format = 'compare' === $request->get_param( 'format' ) ? 'compare' : 'cards';
		if ( 'compare' === $format ) {
			return $this->respond(
				array(
					'html' => Templates::compare_table( $ids ),
					'ids'  => array_slice( $ids, 0, 4 ),
				)
			);
		}
		return $this->respond(
			array(
				'html' => $ids ? Templates::cards( $ids, 3 ) : Templates::empty_state( __( 'No saved properties yet', 'dev3-ai-real-estate' ), __( 'Tap the heart on any property to save it here.', 'dev3-ai-real-estate' ) ),
				'ids'  => $ids,
			)
		);
	}

	/**
	 * POST /nl-search { text } → filters (AI with rule-based fallback, cached).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function nl_search( \WP_REST_Request $request ) {
		$text = (string) $request->get_param( 'text' );
		if ( '' === trim( $text ) ) {
			return $this->respond( new \WP_Error( 'dev3re_empty', __( 'Type what you are looking for.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		return $this->respond( Nl_Search::interpret( $text, 'storefront' ) );
	}

	/**
	 * POST /leads: enquiry or viewing request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function lead( \WP_REST_Request $request ) {
		$p = $request->get_json_params();
		$p = is_array( $p ) && $p ? $p : $request->get_body_params();
		// Honeypot: pretend success so bots learn nothing.
		if ( ! empty( $p['website'] ) ) {
			return $this->respond( array( 'ok' => true ), 201 );
		}
		if ( Settings::get( 'leads_consent', true ) && empty( $p['consent'] ) ) {
			return $this->respond( new \WP_Error( 'dev3re_consent', __( 'Please tick the consent box.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		$p['ip']     = Rate_Limiter::client_ip();
		$p['source'] = ! empty( $p['property_id'] ) ? 'property' : 'agent';
		$id          = Leads::create( (array) $p );
		if ( is_wp_error( $id ) ) {
			return $this->respond( $id );
		}
		return $this->respond(
			array(
				'ok' => true,
				'id' => $id,
			),
			201
		);
	}

	/**
	 * POST /searches { email, frequency, query{} }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_search( \WP_REST_Request $request ) {
		if ( ! Settings::get( 'saved_searches_enabled', true ) ) {
			return $this->respond( new \WP_Error( 'dev3re_disabled', __( 'Saved searches are turned off.', 'dev3-ai-real-estate' ), array( 'status' => 403 ) ) );
		}
		$p     = (array) $request->get_json_params();
		$user  = is_user_logged_in() && $this->nonce_ok( $request ) ? get_current_user_id() : 0;
		$saved = Searches::create(
			array(
				'user_id'   => $user,
				'email'     => $user ? '' : (string) ( $p['email'] ?? '' ),
				'frequency' => (string) ( $p['frequency'] ?? 'daily' ),
				'query'     => is_array( $p['query'] ?? null ) ? $p['query'] : array(),
				'label'     => (string) ( $p['label'] ?? '' ),
			)
		);
		if ( is_wp_error( $saved ) ) {
			return $this->respond( $saved );
		}
		return $this->respond(
			array(
				'id'            => $saved['id'],
				'label'         => $saved['label'],
				'needs_confirm' => $saved['needs_confirm'],
			),
			201
		);
	}

	/**
	 * DELETE /searches/<id> (owner).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_search( \WP_REST_Request $request ) {
		$row = Searches::get( (int) $request['id'] );
		if ( ! $row || ( get_current_user_id() !== (int) $row['user_id'] && ! current_user_can( 'manage_options' ) ) ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Saved search not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		Searches::delete( $row['id'] );
		return $this->respond( array( 'deleted' => true ) );
	}

	/**
	 * GET /favourites, POST /favourites { id, on } | { merge: [ids] }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function favourites( \WP_REST_Request $request ) {
		$user = get_current_user_id();
		if ( 'GET' === $request->get_method() ) {
			return $this->respond( array( 'ids' => Favourites::get( $user ) ) );
		}
		$p = (array) $request->get_json_params();
		if ( isset( $p['merge'] ) ) {
			delete_user_meta( $user, '_dev3re_fav_sync' );
			return $this->respond( array( 'ids' => Favourites::merge( $user, (array) $p['merge'] ) ) );
		}
		$ids = Favourites::set( $user, (int) ( $p['id'] ?? 0 ), ! empty( $p['on'] ) );
		return $this->respond(
			array(
				'ids'   => $ids,
				'saved' => in_array( (int) ( $p['id'] ?? 0 ), $ids, true ),
			)
		);
	}
}
