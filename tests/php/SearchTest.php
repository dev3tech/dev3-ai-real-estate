<?php
/**
 * Search engine, plain-language parser, saved searches + alerts, leads.
 *
 * @package Dev3\AiRealEstate
 */

require_once __DIR__ . '/testcase.php';

use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Searches;
use Dev3\AiRealEstate\Search\Nl_Parser;
use Dev3\AiRealEstate\Search\Search;

class SearchTest extends Dev3re_TestCase {

	/**
	 * Fixture ids.
	 *
	 * @var array
	 */
	private $p = array();

	private function fixtures() {
		$house  = $this->term( 'House', Post_Types::TYPE );
		$flat   = $this->term( 'Apartment', Post_Types::TYPE );
		$garden = $this->term( 'Garden', Post_Types::FEATURE );
		$pool   = $this->term( 'Swimming pool', Post_Types::FEATURE );
		$this->p['maple']  = $this->property( 'Maple House', array( 'price' => 450000, 'beds' => 3, 'baths' => 2, 'area' => 1800, 'lat' => 39.78, 'lng' => -89.65, 'street' => '12 Elm St' ), array( 'type' => array( $house ), 'features' => array( $garden, $pool ) ) ); // phpcs:ignore
		$this->p['cedar']  = $this->property( 'Cedar Cottage', array( 'price' => 280000, 'beds' => 2, 'baths' => 1, 'area' => 900, 'lat' => 39.80, 'lng' => -89.60 ), array( 'type' => array( $house ), 'features' => array( $garden ) ) ); // phpcs:ignore
		$this->p['loft']   = $this->property( 'City Loft', array( 'listing_type' => 'rent', 'price' => 1900, 'beds' => 1, 'area' => 700, 'lat' => 39.79, 'lng' => -89.64 ), array( 'type' => array( $flat ) ) ); // phpcs:ignore
		$this->p['sold']   = $this->property( 'Sold Villa', array( 'price' => 900000, 'beds' => 5, 'market' => 'sold' ), array( 'type' => array( $house ) ) ); // phpcs:ignore
		$this->p['far']    = $this->property( 'Lake House', array( 'price' => 600000, 'beds' => 4, 'city' => 'Chicago', 'lat' => 41.88, 'lng' => -87.63 ), array( 'type' => array( $house ) ) ); // phpcs:ignore
		$this->p['draft']  = $this->property( 'Draft Home', array( 'price' => 100000 ), array( 'status' => 'draft' ) );
		return compact( 'house', 'flat', 'garden', 'pool' );
	}

	private function ids( array $raw ) {
		$ids = Search::run( Search::sanitize( $raw ) )['ids'];
		sort( $ids );
		return $ids;
	}

	private function expect( array $keys ) {
		$ids = array_map( fn( $k ) => $this->p[ $k ], $keys );
		sort( $ids );
		return $ids;
	}

	public function test_filters() {
		$t = $this->fixtures();
		$this->assertSame( $this->expect( array( 'maple', 'cedar', 'loft', 'far' ) ), $this->ids( array() ), 'Published, sold hidden' );
		$this->assertSame( $this->expect( array( 'loft' ) ), $this->ids( array( 'listing_type' => 'rent' ) ) );
		$this->assertSame( $this->expect( array( 'maple', 'cedar' ) ), $this->ids( array( 'listing_type' => 'sale', 'price_max' => 500000, 'price_min' => 200000 ) ) );
		$this->assertSame( $this->expect( array( 'maple', 'far' ) ), $this->ids( array( 'beds' => 3 ) ) );
		$this->assertSame( $this->expect( array( 'maple' ) ), $this->ids( array( 'features' => 'garden,swimming-pool' ) ), 'Features are AND' );
		$this->assertSame( $this->expect( array( 'maple', 'cedar' ) ), $this->ids( array( 'features' => array( $t['garden'] ) ) ) );
		$this->assertSame( $this->expect( array( 'maple' ) ), $this->ids( array( 'area_min' => 1000 ) ), 'Area in ft²' );
		$this->assertSame( $this->expect( array( 'loft' ) ), $this->ids( array( 'type' => 'apartment' ) ) );
		$this->assertSame( $this->expect( array( 'far' ) ), $this->ids( array( 'location' => 'chicago' ) ) );
		$this->assertSame( $this->expect( array( 'maple' ) ), $this->ids( array( 'q' => 'elm' ) ) );
		$this->assertSame( $this->expect( array( 'maple', 'cedar', 'loft' ) ), $this->ids( array( 'bbox' => '39.7,-89.7,39.9,-89.5' ) ) );
		$this->assertSame( $this->expect( array( 'maple', 'cedar', 'loft' ) ), $this->ids( array( 'lat' => 39.79, 'lng' => -89.63, 'radius' => 10 ) ) );
		$this->assertSame( $this->expect( array( 'maple', 'cedar', 'loft', 'sold', 'far' ) ), $this->ids( array( 'include_sold' => 1 ) ) );
	}

	public function test_sorting_paging_and_markers() {
		$this->fixtures();
		$res = Search::run( Search::sanitize( array( 'listing_type' => 'sale', 'sort' => 'price_asc', 'per_page' => 2 ) ) );
		$this->assertSame( array( $this->p['cedar'], $this->p['maple'] ), $res['ids'] );
		$this->assertSame( 3, $res['total'] );
		$this->assertSame( 2, $res['pages'] );
		$markers = Search::markers( Search::sanitize( array( 'listing_type' => 'sale' ) ) );
		$this->assertCount( 3, $markers );
		$this->assertSame( '$450k', wp_list_pluck( $markers, 'price', 'id' )[ $this->p['maple'] ] );
	}

	public function test_describe_and_to_query() {
		$t    = $this->fixtures();
		$args = Search::sanitize( array( 'listing_type' => 'sale', 'type' => 'house', 'price_max' => 500000, 'beds' => '3', 'features' => array( $t['garden'] ) ) );
		$this->assertSame( array( 'For sale', 'House', 'Garden', 'Up to $500,000', '3+ beds' ), wp_list_pluck( Search::describe( $args ), 'label' ) );
		$q = Search::to_query( $args );
		$this->assertSame( array( 'house' ), $q['type'] );
		$this->assertSame( array( $t['house'] ), Search::to_query( $args, false )['type'] );
		$this->assertArrayNotHasKey( 'sort', $q );
	}

	public function test_rule_parser() {
		$this->fixtures();
		$this->term( 'Springfield', Post_Types::LOCATION );
		$q = Nl_Parser::parse( '3 bed house for rent under 2000 with pool in Springfield' );
		$this->assertSame( 'rent', $q['listing_type'] );
		$this->assertSame( 3, $q['beds'] );
		$this->assertSame( 2000.0, $q['price_max'] );
		$this->assertCount( 1, $q['type'] );
		$this->assertSame( 'House', get_term( $q['type'][0] )->name );
		$this->assertSame( 'Swimming pool', get_term( $q['features'][0] )->name );
		$this->assertSame( 'Springfield', get_term( $q['location'][0] )->name );
		$q = Nl_Parser::parse( 'two bedroom flat between 200k and 350k, 2 baths, at least 80 m2' );
		$this->assertSame( 2, $q['beds'] );
		$this->assertSame( 2.0, $q['baths'] );
		$this->assertSame( 200000.0, $q['price_min'] );
		$this->assertSame( 350000.0, $q['price_max'] );
		$this->assertSame( 'Apartment', get_term( $q['type'][0] )->name );
		$this->assertSame( 861.0, $q['area_min'], '80 m² in the site unit (ft²)' );
		$this->assertSame( 1200000.0, Nl_Parser::amount( '$1.2m' ) );
		$this->assertSame( array( 'q' => 'quiet cul de sac' ), Nl_Parser::parse( 'quiet cul-de-sac' ) );
	}

	public function test_saved_search_alerts_only_send_new_matches() {
		$this->fixtures();
		$user  = self::factory()->user->create( array( 'user_email' => 'buyer@example.com' ) );
		$mine  = Searches::create(
			array(
				'user_id' => $user,
				'query'   => array( 'listing_type' => 'sale', 'type' => 'house', 'price_max' => 500000 ),
			)
		);
		$guest = Searches::create(
			array(
				'email' => 'guest@example.com',
				'query' => array( 'listing_type' => 'rent' ),
			)
		);
		$this->assertFalse( $mine['needs_confirm'] );
		$this->assertTrue( $guest['needs_confirm'] );
		$this->assertStringContainsString( 'Confirm', tests_retrieve_phpmailer_instance()->get_sent()->subject );
		reset_phpmailer_instance();
		$this->assertSame( array( 'checked' => 1, 'sent' => 0 ), Searches::run_alerts(), 'Existing listings are not sent; unconfirmed guests skipped' );
		$new = $this->property( 'Birch House', array( 'price' => 399000 ), array( 'type' => array( $this->term( 'House', Post_Types::TYPE ) ) ) );
		$this->property( 'Too Expensive House', array( 'price' => 999000 ), array( 'type' => array( $this->term( 'House', Post_Types::TYPE ) ) ) );
		$res = Searches::run_alerts( true );
		$this->assertSame( 1, $res['sent'] );
		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'buyer@example.com', $mail->to[0][0] );
		$body = quoted_printable_decode( $mail->body );
		$this->assertStringContainsString( 'Birch House', $body );
		$this->assertStringNotContainsString( 'Too Expensive', $body );
		$this->assertStringContainsString( 'dev3re_alert=unsubscribe', $body );
		reset_phpmailer_instance();
		$this->assertSame( 0, Searches::run_alerts( true )['sent'], 'Nothing new since the last digest' );
		// Guest confirms with the token; a wrong token fails; unsubscribe deletes.
		$row = Searches::get( $guest['id'], true );
		$this->assertFalse( Searches::confirm( $guest['id'], 'wrong' ) );
		$this->assertTrue( Searches::confirm( $guest['id'], $row['token'] ) );
		$this->assertTrue( Searches::get( $guest['id'] )['confirmed'] );
		$this->assertTrue( Searches::unsubscribe( $guest['id'], $row['token'] ) );
		$this->assertNull( Searches::get( $guest['id'] ) );
		unset( $new );
	}

	public function test_due_schedule() {
		$now = time();
		$this->assertFalse( Searches::is_due( array( 'confirmed' => false, 'frequency' => 'daily', 'last_sent' => null ), $now ) );
		$this->assertTrue( Searches::is_due( array( 'confirmed' => true, 'frequency' => 'daily', 'last_sent' => gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ) ), $now ) );
		$this->assertFalse( Searches::is_due( array( 'confirmed' => true, 'frequency' => 'weekly', 'last_sent' => gmdate( 'Y-m-d H:i:s', $now - 2 * DAY_IN_SECONDS ) ), $now ) );
		$this->assertTrue( Searches::is_due( array( 'confirmed' => true, 'frequency' => 'instant', 'last_sent' => gmdate( 'Y-m-d H:i:s', $now ) ), $now ) );
	}

	public function test_leads_route_to_agent_and_send_emails() {
		$agent = \Dev3\AiRealEstate\Data\Agents::save_agent(
			array(
				'name'   => 'Maya',
				'fields' => array( 'email' => 'maya@example.com' ),
			)
		);
		$id    = $this->property( 'Maple House', array( 'agent_id' => $agent ) );
		$lead  = Leads::create(
			array(
				'property_id' => $id,
				'agent_id'    => 999,
				'name'        => 'Ann',
				'email'       => 'ann@example.com',
				'phone'       => '+1 (555) 0100 <script>',
				'message'     => 'Is it still available?',
				'kind'        => 'viewing',
				'viewing_at'  => '2026-11-02',
			)
		);
		$row   = Leads::get( $lead );
		$this->assertSame( $agent, $row['agent_id'], 'Agent comes from the property, not the request' );
		$this->assertSame( '+1 (555) 0100 ', $row['phone'] );
		$this->assertSame( 'viewing', $row['kind'] );
		$sent = tests_retrieve_phpmailer_instance()->mock_sent;
		$to   = array_map( fn( $m ) => $m['to'][0][0], $sent );
		$this->assertContains( 'maya@example.com', $to );
		$this->assertContains( 'ann@example.com', $to, 'Auto-reply' );
		$this->assertContains( get_option( 'admin_email' ), $to, 'Admin copy' );
		$this->assertWPError( Leads::create( array( 'name' => 'X', 'email' => 'nope' ) ) );
		$this->assertWPError( Leads::create( array( 'name' => 'X', 'email' => 'x@example.com', 'property_id' => $this->p['draft'] ?? self::factory()->post->create() ) ) );
		$this->assertSame( 'contacted', Leads::update( $lead, array( 'status' => 'contacted', 'notes' => 'Called' ) )['status'] );
		$this->assertSame( 1, Leads::count( array( 'agent_id' => $agent ) ) );
		$rows = Leads::export_rows();
		$this->assertSame( 'Ann', $rows[1][4] );
		Leads::anonymise( 'ann@example.com' );
		$this->assertSame( '', Leads::get( $lead )['email'] );
	}
}
