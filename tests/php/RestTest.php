<?php
/**
 * REST permissions and public endpoints.
 *
 * @package Dev3\AiRealEstate
 */

require_once __DIR__ . '/testcase.php';

use Dev3\AiRealEstate\Core3\Core\Settings_Store;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Favourites;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;

class RestTest extends Dev3re_TestCase {

	private function agent_user( $name ) {
		$user = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		Agents::save_agent(
			array(
				'name'    => $name,
				'user_id' => $user,
			)
		);
		return $user;
	}

	public function test_public_search_returns_cards_markers_and_chips() {
		$this->property( 'Maple House', array( 'price' => 450000, 'lat' => 39.7, 'lng' => -89.6, 'beds' => 3 ) );
		$this->property( 'City Loft', array( 'listing_type' => 'rent', 'price' => 1900 ) );
		$res = $this->rest( 'GET', 'properties', array( 'listing_type' => 'sale', 'markers' => 1, 'beds' => 2 ), false );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertSame( 1, $data['total'] );
		$this->assertStringContainsString( 'Maple House', $data['html'] );
		$this->assertStringNotContainsString( 'City Loft', $data['html'] );
		$this->assertCount( 1, $data['markers'] );
		$this->assertStringContainsString( '2+ beds', $data['chips'] );
		$this->assertSame( array( 'listing_type' => 'sale', 'beds' => 2 ), $data['query'] );
	}

	public function test_public_endpoints_block_cross_origin_and_rate_limit() {
		$res = $this->rest( 'POST', 'nl-search', array( 'text' => '2 bed flat' ), false, array( 'Origin' => 'https://evil.example.com' ) );
		$this->assertSame( 403, $res->get_status() );
		add_filter( 'dev3re_public_rate_limit', fn() => 2 );
		$_SERVER['REMOTE_ADDR'] = '10.1.2.3';
		$codes = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$codes[] = $this->rest( 'POST', 'nl-search', array( 'text' => '2 bed flat' ), false )->get_status();
		}
		$this->assertSame( array( 200, 200, 429 ), $codes );
	}

	public function test_lead_endpoint_consent_and_honeypot() {
		$id  = $this->property( 'Maple House' );
		$_SERVER['REMOTE_ADDR'] = '10.9.9.' . wp_rand( 1, 250 );
		$res = $this->rest( 'POST', 'leads', array( 'property_id' => $id, 'name' => 'Ann', 'email' => 'ann@example.com' ), false );
		$this->assertSame( 400, $res->get_status() );
		$this->assertSame( 'dev3re_consent', $res->get_data()['code'] );
		$res = $this->rest( 'POST', 'leads', array( 'property_id' => $id, 'name' => 'Bot', 'email' => 'bot@example.com', 'consent' => 1, 'website' => 'http://spam' ), false );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( 0, Leads::count() );
		$res = $this->rest( 'POST', 'leads', array( 'property_id' => $id, 'name' => 'Ann', 'email' => 'ann@example.com', 'consent' => 1, 'message' => 'Hi' ), false );
		$this->assertSame( 201, $res->get_status() );
		$this->assertSame( 1, Leads::count() );
	}

	public function test_saved_search_and_favourites() {
		$id   = $this->property( 'Maple House' );
		$_SERVER['REMOTE_ADDR'] = '10.8.8.' . wp_rand( 1, 250 );
		$res  = $this->rest( 'POST', 'searches', array( 'email' => 'g@example.com', 'frequency' => 'weekly', 'query' => array( 'listing_type' => 'sale' ) ), false );
		$this->assertSame( 201, $res->get_status() );
		$this->assertTrue( $res->get_data()['needs_confirm'] );
		$this->assertSame( 401, $this->rest( 'POST', 'favourites', array( 'id' => $id, 'on' => true ), false )->get_status() );
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		$res = $this->rest( 'POST', 'favourites', array( 'id' => $id, 'on' => true ) );
		$this->assertTrue( $res->get_data()['saved'] );
		$this->assertSame( array( $id ), Favourites::get( $user ) );
		$this->assertSame( array( $id ), $this->rest( 'POST', 'favourites', array( 'merge' => array( $id, 99999 ) ) )->get_data()['ids'] );
		$compare = $this->rest( 'GET', 'properties/by-ids', array( 'ids' => $id . ',99999', 'format' => 'compare' ), false )->get_data();
		$this->assertSame( array( $id ), $compare['ids'] );
		$this->assertStringContainsString( 'dev3re-cmp__table', $compare['html'] );
	}

	public function test_agents_only_touch_their_own_listings_and_leads() {
		$alice = $this->agent_user( 'Alice' );
		$bob   = $this->agent_user( 'Bob' );
		$plain = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $plain );
		$this->assertSame( 403, $this->rest( 'POST', 'agent/properties', array( 'title' => 'Nope' ) )->get_status() );
		wp_set_current_user( $alice );
		$this->assertSame( 401, $this->rest( 'POST', 'agent/properties', array( 'title' => 'No nonce' ), false )->get_status() );
		$res = $this->rest(
			'POST',
			'agent/properties',
			array(
				'title'   => 'Alice Home',
				'content' => "Bright.\n\nQuiet.",
				'fields'  => array(
					'price'      => 250000,
					'featured'   => true,
					'highlights' => "Garden\nGarage",
				),
			)
		);
		$this->assertSame( 201, $res->get_status() );
		$aid = $res->get_data()['id'];
		$this->assertSame( 'pending', $res->get_data()['status'] );
		$this->assertSame( "<p>Bright.</p>\n<p>Quiet.</p>", get_post_field( 'post_content', $aid ) );
		$this->assertSame( array( 'Garden', 'Garage' ), Property::value( $aid, 'highlights' ) );
		$this->assertFalse( Property::value( $aid, 'featured' ) );
		wp_update_post( array( 'ID' => $aid, 'post_status' => 'publish' ) ); // phpcs:ignore
		$lead = Leads::create( array( 'property_id' => $aid, 'name' => 'Ann', 'email' => 'ann@example.com' ) ); // phpcs:ignore
		$this->assertSame( 200, $this->rest( 'POST', 'agent/leads/' . $lead, array( 'status' => 'contacted' ) )->get_status() );
		$this->assertSame( 1, $this->rest( 'GET', 'agent/leads' )->get_data()['total'] );
		wp_set_current_user( $bob );
		$this->assertSame( 404, $this->rest( 'GET', 'agent/properties/' . $aid )->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', 'agent/properties/' . $aid, array( 'title' => 'Hijack' ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'DELETE', 'agent/properties/' . $aid )->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', 'agent/properties/' . $aid . '/market', array( 'market' => 'sold' ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', 'agent/leads/' . $lead, array( 'status' => 'spam' ) )->get_status() );
		$this->assertSame( 0, $this->rest( 'GET', 'agent/leads' )->get_data()['total'] );
		$this->assertSame( 'Alice Home', get_the_title( $aid ) );
		wp_set_current_user( $alice );
		$this->assertSame( 'sold', $this->rest( 'POST', 'agent/properties/' . $aid . '/market', array( 'market' => 'sold' ) )->get_data()['market'] );
		$res = $this->rest( 'POST', 'agent/properties/' . $aid, array( 'title' => 'Alice Home 2' ) );
		$this->assertSame( 'publish', $res->get_data()['status'], 'Edits of live listings stay live' );
	}

	public function test_agent_signup_toggle() {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$this->assertSame( 403, $this->rest( 'POST', 'agent/become' )->get_status() );
		Settings_Store::update( array( 'agent_signup' => true ) );
		$this->assertGreaterThan( 0, $this->rest( 'POST', 'agent/become' )->get_data()['agent_id'] );
		$this->assertTrue( Agents::is_agent( $user ) );
	}

	public function test_admin_routes_need_manage_and_work() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->rest( 'GET', 'admin/properties' )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$feature = $this->term( 'Garden', Post_Types::FEATURE );
		$res     = $this->rest(
			'POST',
			'admin/properties',
			array(
				'title'    => 'Admin Home',
				'status'   => 'publish',
				'features' => array( $feature ),
				'fields'   => array(
					'price'    => 500000,
					'featured' => true,
				),
			)
		);
		$this->assertSame( 201, $res->get_status() );
		$id = $res->get_data()['id'];
		$this->assertTrue( Property::value( $id, 'featured' ) );
		$this->assertSame( array( $feature ), $res->get_data()['features'] );
		$list = $this->rest( 'GET', 'admin/properties', array( 'status' => 'publish' ) )->get_data();
		$this->assertSame( 1, $list['total'] );
		$this->assertSame( 1, $this->rest( 'POST', 'admin/properties/bulk', array( 'ids' => array( $id ), 'action' => 'unfeature' ) )->get_data()['done'] );
		$this->assertFalse( Property::value( $id, 'featured' ) );
		$stats = $this->rest( 'GET', 'admin/stats' )->get_data();
		$this->assertSame( 1, $stats['properties']['publish'] );
		$this->assertArrayHasKey( 'checks', $stats );
		$terms = $this->rest( 'POST', 'admin/terms/type', array( 'name' => 'Chalet' ) )->get_data()['terms'];
		$this->assertContains( 'Chalet', wp_list_pluck( $terms, 'name' ) );
		$agent = $this->rest( 'POST', 'admin/agents', array( 'name' => 'Maya', 'fields' => array( 'email' => 'm@example.com' ) ) )->get_data(); // phpcs:ignore
		$this->assertSame( 'agent', $agent['kind'] );
		$this->assertCount( 1, $this->rest( 'GET', 'admin/agents' )->get_data()['items'] );
		$pages = $this->rest( 'POST', 'admin/pages' )->get_data();
		$this->assertGreaterThan( 0, $pages['page_search'] );
		$this->assertStringContainsString( 'wp:dev3re/search', get_post_field( 'post_content', $pages['page_search'] ) );
	}
}
