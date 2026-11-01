<?php
/**
 * Admin app routes.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Rest;

use Dev3\AiRealEstate\Ai\Describer;
use Dev3\AiRealEstate\Ai\Neighbourhood;
use Dev3\AiRealEstate\Core3\Api\Ai;
use Dev3\AiRealEstate\Core3\Rest\Base_Controller;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Index;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Data\Searches;
use Dev3\AiRealEstate\Data\Tables;
use Dev3\AiRealEstate\Front\Frontend;
use Dev3\AiRealEstate\Search\Geo;
use Dev3\AiRealEstate\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under dev3re/v1/admin.
 */
class Admin_Controller extends Base_Controller {

	/**
	 * Registers routes.
	 */
	public function register_routes() {
		$manage = array( $this, 'can_manage' );
		$routes = array(
			'/admin/meta'                                  => array( 'GET', 'meta', $manage ),
			'/admin/stats'                                 => array( 'GET', 'stats', $manage ),
			'/admin/properties'                            => array( 'GET,POST', 'properties', $manage ),
			'/admin/properties/bulk'                       => array( 'POST', 'bulk', $manage ),
			'/admin/properties/(?P<id>\d+)'                => array( 'GET,POST,DELETE', 'property', $manage ),
			'/admin/reindex'                               => array( 'POST', 'reindex', $manage ),
			'/admin/(?P<kind>agents|agencies)'             => array( 'GET,POST', 'profiles', $manage ),
			'/admin/(?P<kind>agents|agencies)/(?P<id>\d+)' => array( 'GET,POST,DELETE', 'profile', $manage ),
			'/admin/users'                                 => array( 'GET', 'users', $manage ),
			'/admin/leads'                                 => array( 'GET', 'leads', $manage ),
			'/admin/leads/(?P<id>\d+)'                     => array( 'POST,DELETE', 'lead', $manage ),
			'/admin/searches'                              => array( 'GET', 'searches', $manage ),
			'/admin/searches/send'                         => array( 'POST', 'send_alerts', $manage ),
			'/admin/searches/(?P<id>\d+)'                  => array( 'DELETE', 'delete_search', $manage ),
			'/admin/terms/(?P<tax>type|location|feature)'  => array( 'POST', 'add_term', $manage ),
			'/admin/terms/(?P<tax>type|location|feature)/(?P<id>\d+)' => array( 'DELETE', 'delete_term', $manage ),
			'/admin/pages'                                 => array( 'POST', 'pages', $manage ),
			'/admin/geocode'                               => array( 'POST', 'geocode', $manage ),
			'/admin/ai/describe'                           => array( 'POST', 'describe', array( $this, 'can_generate' ) ),
			'/admin/ai/neighbourhood'                      => array( 'POST', 'neighbourhood', array( $this, 'can_generate' ) ),
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
	 * Taxonomy key → name.
	 *
	 * @param string $key type|location|feature.
	 * @return string
	 */
	private static function tax( $key ) {
		$map = array(
			'type'     => Post_Types::TYPE,
			'location' => Post_Types::LOCATION,
			'feature'  => Post_Types::FEATURE,
		);
		return $map[ $key ] ?? Post_Types::TYPE;
	}

	/**
	 * GET meta: vocabularies + options for the editor.
	 *
	 * @return \WP_REST_Response
	 */
	public function meta() {
		$pages = array();
		foreach ( get_pages( array( 'number' => 300 ) ) as $p ) {
			$pages[] = array(
				'value' => (int) $p->ID,
				'label' => $p->post_title ? $p->post_title : '#' . $p->ID,
			);
		}
		return $this->respond(
			array(
				'types'     => Post_Types::terms( Post_Types::TYPE ),
				'locations' => Post_Types::terms( Post_Types::LOCATION ),
				'features'  => Post_Types::terms( Post_Types::FEATURE ),
				'agents'    => Agents::options( 'agent' ),
				'agencies'  => Agents::options( 'agency' ),
				'pages'     => $pages,
				'unit'      => Property::unit_label(),
				'currency'  => (string) Settings::get( 'currency', 'USD' ),
				'ai'        => Ai::status(),
			)
		);
	}

	/**
	 * GET stats for the dashboard.
	 *
	 * @return \WP_REST_Response
	 */
	public function stats() {
		global $wpdb;
		$index = Tables::name( 'index' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by  = $wpdb->get_results( "SELECT status, listing_type, market, COUNT(*) AS n, SUM(featured) AS f FROM {$index} GROUP BY status, listing_type, market", ARRAY_A );
		$top = $wpdb->get_col( "SELECT post_id FROM {$index} WHERE status = 'publish' ORDER BY post_id DESC LIMIT 200" );
		// phpcs:enable
		$out = array(
			'publish'  => 0,
			'pending'  => 0,
			'draft'    => 0,
			'sale'     => 0,
			'rent'     => 0,
			'closed'   => 0,
			'featured' => 0,
		);
		foreach ( (array) $by as $r ) {
			$n = (int) $r['n'];
			if ( isset( $out[ $r['status'] ] ) ) {
				$out[ $r['status'] ] += $n;
			}
			if ( 'publish' === $r['status'] ) {
				if ( in_array( $r['market'], array( 'sold', 'rented' ), true ) ) {
					$out['closed'] += $n;
				} else {
					$out[ 'rent' === $r['listing_type'] ? 'rent' : 'sale' ] += $n;
				}
				$out['featured'] += (int) $r['f'];
			}
		}
		$views = array();
		foreach ( array_map( 'intval', (array) $top ) as $id ) {
			$views[ $id ] = (int) get_post_meta( $id, '_dev3re_views', true );
		}
		arsort( $views );
		$popular = array();
		foreach ( array_slice( $views, 0, 5, true ) as $id => $v ) {
			$c         = Property::card( $id );
			$popular[] = array(
				'id'    => $id,
				'title' => $c['title'],
				'price' => $c['price_html'],
				'image' => $c['image'],
				'views' => $v,
				'leads' => Leads::count( array( 'property_id' => $id ) ),
			);
		}
		$recent = Leads::query( array( 'per_page' => 6 ) );
		$checks = array(
			'pages'    => (int) Settings::get( 'page_search', 0 ) > 0,
			'property' => ( $out['publish'] + $out['draft'] + $out['pending'] ) > 0,
			'agent'    => count( Agents::options( 'agent' ) ) > 0,
			'map'      => 'google' !== Settings::get( 'map_provider', 'osm' ) || '' !== (string) Settings::get( 'google_maps_key', '' ),
			'ai'       => Ai::status()['key_set'],
		);
		return $this->respond(
			array(
				'properties' => $out,
				'leads'      => Leads::status_counts(),
				'leads_7d'   => $this->leads_since( 7 ),
				'searches'   => Searches::query( array( 'per_page' => 1 ) )['total'],
				'recent'     => $recent['items'],
				'popular'    => $popular,
				'checks'     => $checks,
			)
		);
	}

	/**
	 * Leads in the last N days.
	 *
	 * @param int $days Days.
	 * @return int
	 */
	private function leads_since( $days ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::name( 'leads' ) . " WHERE created_at >= %s AND status <> 'spam'", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}

	/**
	 * GET list / POST create.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function properties( \WP_REST_Request $request ) {
		if ( 'POST' === $request->get_method() ) {
			$data = Agent_Controller::payload( (array) $request->get_json_params() );
			$data = $this->admin_payload( $data, (array) $request->get_json_params() );
			$id   = Property::save( $data, get_current_user_id(), 'admin' );
			return $this->respond( is_wp_error( $id ) ? $id : Property::get( $id ), 201 );
		}
		$status   = (string) $request->get_param( 'status' );
		$statuses = in_array( $status, array( 'publish', 'pending', 'draft', 'private' ), true ) ? array( $status ) : array( 'publish', 'pending', 'draft', 'private' );
		$args     = array(
			'post_type'      => Post_Types::PROPERTY,
			'post_status'    => $statuses,
			'posts_per_page' => min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ) ) ),
			'paged'          => max( 1, (int) $request->get_param( 'page' ) ),
			's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'orderby'        => in_array( $request->get_param( 'orderby' ), array( 'date', 'title', 'modified' ), true ) ? $request->get_param( 'orderby' ) : 'date',
			'order'          => 'asc' === $request->get_param( 'order' ) ? 'ASC' : 'DESC',
		);
		$deal     = (string) $request->get_param( 'listing_type' );
		if ( in_array( $deal, array( 'sale', 'rent' ), true ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
				array(
					'key'   => Property::meta_key( 'listing_type' ),
					'value' => $deal,
				),
			);
		}
		$agent = (int) $request->get_param( 'agent' );
		if ( $agent ) {
			$args['meta_query']   = isset( $args['meta_query'] ) ? $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query
			$args['meta_query'][] = array(
				'key'   => Property::meta_key( 'agent_id' ),
				'value' => $agent,
			);
		}
		$q     = new \WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$c       = Property::card( $post->ID );
			$aid     = (int) Property::value( $post->ID, 'agent_id' );
			$items[] = array(
				'id'           => (int) $post->ID,
				'title'        => $post->post_title,
				'status'       => $post->post_status,
				'url'          => $c['url'],
				'image'        => $c['image'] ? (string) get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) : '',
				'price'        => $c['price_html'],
				'listing_type' => $c['listing_type'],
				'market'       => $c['market'],
				'beds'         => $c['beds'],
				'baths'        => $c['baths'],
				'area'         => $c['area'],
				'city'         => $c['city'],
				'type'         => $c['type'],
				'featured'     => $c['featured'],
				'agent'        => $aid ? get_the_title( $aid ) : '',
				'views'        => (int) get_post_meta( $post->ID, '_dev3re_views', true ),
				'leads'        => Leads::count( array( 'property_id' => $post->ID ) ),
				'date'         => get_the_date( 'c', $post ),
			);
		}
		$counts = array();
		foreach ( array( 'publish', 'pending', 'draft' ) as $st ) {
			$counts[ $st ] = (int) wp_count_posts( Post_Types::PROPERTY )->$st;
		}
		return $this->respond(
			array(
				'items'  => $items,
				'total'  => (int) $q->found_posts,
				'counts' => $counts,
			)
		);
	}

	/**
	 * Admin-only extras (status, location terms, featured/agent fields already pass in admin context).
	 *
	 * @param array $data Data.
	 * @param array $raw  Raw.
	 * @return array
	 */
	private function admin_payload( array $data, array $raw ) {
		if ( isset( $raw['status'] ) ) {
			$data['status'] = (string) $raw['status'];
		}
		if ( isset( $raw['id'] ) ) {
			$data['id'] = (int) $raw['id'];
		}
		return $data;
	}

	/**
	 * GET/POST/DELETE one property.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function property( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( Post_Types::PROPERTY !== get_post_type( $id ) ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Property not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		if ( 'DELETE' === $request->get_method() ) {
			wp_trash_post( $id );
			return $this->respond( array( 'deleted' => true ) );
		}
		if ( 'POST' === $request->get_method() ) {
			$raw        = (array) $request->get_json_params();
			$data       = $this->admin_payload( Agent_Controller::payload( $raw ), $raw );
			$data['id'] = $id;
			$saved      = Property::save( $data, get_current_user_id(), 'admin' );
			if ( is_wp_error( $saved ) ) {
				return $this->respond( $saved );
			}
		}
		return $this->respond( Property::get( $id ) );
	}

	/**
	 * POST bulk { ids, action }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function bulk( \WP_REST_Request $request ) {
		$ids    = array_filter( array_map( 'intval', (array) $request->get_param( 'ids' ) ) );
		$action = (string) $request->get_param( 'action' );
		$done   = 0;
		foreach ( $ids as $id ) {
			if ( Post_Types::PROPERTY !== get_post_type( $id ) ) {
				continue;
			}
			switch ( $action ) {
				case 'publish':
				case 'draft':
				case 'pending':
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => $action,
						)
					);
					break;
				case 'trash':
					wp_trash_post( $id );
					break;
				case 'feature':
				case 'unfeature':
					update_post_meta( $id, Property::meta_key( 'featured' ), 'feature' === $action );
					Index::sync( $id );
					break;
				default:
					continue 2;
			}
			++$done;
		}
		return $this->respond( array( 'done' => $done ) );
	}

	/**
	 * POST reindex.
	 *
	 * @return \WP_REST_Response
	 */
	public function reindex() {
		return $this->respond( array( 'indexed' => Index::rebuild() ) );
	}

	/**
	 * GET agents|agencies, POST create.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function profiles( \WP_REST_Request $request ) {
		$is_agent = 'agents' === $request['kind'];
		if ( 'POST' === $request->get_method() ) {
			$p = (array) $request->get_json_params();
			unset( $p['id'] );
			$saved = $is_agent ? Agents::save_agent( $p ) : Agents::save_agency( $p );
			return $this->respond( is_wp_error( $saved ) ? $saved : Agents::get( $saved ), 201 );
		}
		$posts = get_posts(
			array(
				'post_type'      => $is_agent ? Post_Types::AGENT : Post_Types::AGENCY,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'title',
				'order'          => 'ASC',
				's'              => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'fields'         => 'ids',
			)
		);
		return $this->respond( array( 'items' => array_values( array_filter( array_map( array( Agents::class, 'get' ), $posts ) ) ) ) );
	}

	/**
	 * GET/POST/DELETE one agent/agency.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function profile( \WP_REST_Request $request ) {
		$id       = (int) $request['id'];
		$is_agent = 'agents' === $request['kind'];
		if ( get_post_type( $id ) !== ( $is_agent ? Post_Types::AGENT : Post_Types::AGENCY ) ) {
			return $this->respond( new \WP_Error( 'dev3re_not_found', __( 'Profile not found.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
		}
		if ( 'DELETE' === $request->get_method() ) {
			if ( $is_agent ) {
				Agents::link_user( $id, 0 );
			}
			wp_trash_post( $id );
			return $this->respond( array( 'deleted' => true ) );
		}
		if ( 'POST' === $request->get_method() ) {
			$p       = (array) $request->get_json_params();
			$p['id'] = $id;
			$saved   = $is_agent ? Agents::save_agent( $p ) : Agents::save_agency( $p );
			if ( is_wp_error( $saved ) ) {
				return $this->respond( $saved );
			}
		}
		return $this->respond( Agents::get( $id ) );
	}

	/**
	 * GET users?search= (link agents to accounts).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function users( \WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$users  = get_users(
			array(
				'number'  => 20,
				'search'  => $search ? '*' . $search . '*' : '',
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
				'orderby' => 'display_name',
			)
		);
		$out    = array();
		foreach ( $users as $u ) {
			$out[] = array(
				'value' => (int) $u->ID,
				'label' => $u->display_name . ' (' . $u->user_email . ')',
			);
		}
		return $this->respond( array( 'items' => $out ) );
	}

	/**
	 * GET leads.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function leads( \WP_REST_Request $request ) {
		$res           = Leads::query(
			array(
				'status'       => (string) $request->get_param( 'status' ),
				'agent_id'     => (int) $request->get_param( 'agent' ),
				'property_id'  => (int) $request->get_param( 'property' ),
				'search'       => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'page'         => (int) $request->get_param( 'page' ),
				'per_page'     => (int) ( $request->get_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 20 ),
				'include_spam' => 'spam' === $request->get_param( 'status' ),
			)
		);
		$res['counts'] = Leads::status_counts();
		return $this->respond( $res );
	}

	/**
	 * POST/DELETE one lead.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function lead( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( 'DELETE' === $request->get_method() ) {
			return $this->respond( array( 'deleted' => Leads::delete( $id ) ) );
		}
		return $this->respond( Leads::update( $id, (array) $request->get_json_params() ) );
	}

	/**
	 * GET saved searches.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function searches( \WP_REST_Request $request ) {
		return $this->respond(
			Searches::query(
				array(
					'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
					'page'     => (int) $request->get_param( 'page' ),
					'per_page' => 20,
				)
			)
		);
	}

	/**
	 * POST send alerts now.
	 *
	 * @return \WP_REST_Response
	 */
	public function send_alerts() {
		return $this->respond( Searches::run_alerts( true ) );
	}

	/**
	 * DELETE a saved search.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_search( \WP_REST_Request $request ) {
		return $this->respond( array( 'deleted' => Searches::delete( (int) $request['id'] ) ) );
	}

	/**
	 * POST term { name, parent }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add_term( \WP_REST_Request $request ) {
		$tax  = self::tax( (string) $request['tax'] );
		$name = mb_substr( sanitize_text_field( (string) $request->get_param( 'name' ) ), 0, 80 );
		if ( '' === $name ) {
			return $this->respond( new \WP_Error( 'dev3re_name_required', __( 'Add a name.', 'dev3-ai-real-estate' ), array( 'status' => 400 ) ) );
		}
		$res = wp_insert_term( $name, $tax, array( 'parent' => is_taxonomy_hierarchical( $tax ) ? (int) $request->get_param( 'parent' ) : 0 ) );
		if ( is_wp_error( $res ) ) {
			$res->add_data( array( 'status' => 400 ) );
			return $this->respond( $res );
		}
		return $this->respond( array( 'terms' => Post_Types::terms( $tax ) ), 201 );
	}

	/**
	 * DELETE term.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_term( \WP_REST_Request $request ) {
		$tax = self::tax( (string) $request['tax'] );
		wp_delete_term( (int) $request['id'], $tax );
		return $this->respond( array( 'terms' => Post_Types::terms( $tax ) ) );
	}

	/**
	 * POST create default pages.
	 *
	 * @return \WP_REST_Response
	 */
	public function pages() {
		Frontend::create_pages();
		return $this->respond( \Dev3\AiRealEstate\Core3\Core\Settings_Store::public_settings() );
	}

	/**
	 * POST geocode { address }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function geocode( \WP_REST_Request $request ) {
		$geo = Geo::geocode( mb_substr( sanitize_text_field( (string) $request->get_param( 'address' ) ), 0, 300 ) );
		return $this->respond( $geo ? $geo : new \WP_Error( 'dev3re_geocode', __( 'Address not found. Drag the pin to the right spot.', 'dev3-ai-real-estate' ), array( 'status' => 404 ) ) );
	}

	/**
	 * POST ai/describe { listing, tone, mode }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function describe( \WP_REST_Request $request ) {
		$p       = (array) $request->get_json_params();
		$listing = Agent_Controller::payload( (array) ( $p['listing'] ?? array() ) );
		if ( 'template' === ( $p['mode'] ?? '' ) ) {
			return $this->respond( Describer::template( $listing ) );
		}
		return $this->respond( Describer::generate( $listing, array( 'tone' => (string) ( $p['tone'] ?? '' ) ), 'admin' ) );
	}

	/**
	 * POST ai/neighbourhood { fields, ai }.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function neighbourhood( \WP_REST_Request $request ) {
		$p = (array) $request->get_json_params();
		return $this->respond( Neighbourhood::generate( (array) ( $p['fields'] ?? array() ), 'admin', ! empty( $p['ai'] ) ) );
	}
}
