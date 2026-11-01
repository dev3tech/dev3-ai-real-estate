<?php
/**
 * Property model, index, agents, mortgage maths, schema.
 *
 * @package Dev3\AiRealEstate
 */

require_once __DIR__ . '/testcase.php';

use Dev3\AiRealEstate\Core3\Core\Settings_Store;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Data\Tables;
use Dev3\AiRealEstate\Front\Schema_Markup;
use Dev3\AiRealEstate\Search\Mortgage;

class ModelTest extends Dev3re_TestCase {

	public function test_fields_are_sanitized_and_typed() {
		$this->assertSame( 'rent', Property::clean( 'listing_type', 'rent' ) );
		$this->assertSame( 'sale', Property::clean( 'listing_type', 'lease' ) );
		$this->assertSame( 2.5, Property::clean( 'baths', '2.4' ) );
		$this->assertSame( 0, Property::clean( 'beds', -3 ) );
		$this->assertSame( 450000.0, Property::clean( 'price', '$450,000' ) );
		$this->assertSame( '', Property::clean( 'lat', 'abc' ) );
		$this->assertSame( '', Property::clean( 'lat', 120 ) );
		$this->assertSame( array( 3, 5 ), Property::clean( 'gallery', array( '3', 5, 3, -1 ) ) );
		$plans = Property::clean( 'floor_plans', array( array( 'title' => '<b>Ground</b>', 'beds' => 2, 'area' => 80 ), array( 'notes' => 'x' ), 'bad' ) ); // phpcs:ignore
		$this->assertCount( 1, $plans );
		$this->assertSame( 'Ground', $plans[0]['title'] );
	}

	public function test_save_sets_terms_thumbnail_city_location_and_index() {
		$house  = $this->term( 'House', Post_Types::TYPE );
		$garden = $this->term( 'Garden', Post_Types::FEATURE );
		$img    = $this->image();
		$id     = $this->property(
			'Maple House',
			array(
				'beds'    => 3,
				'baths'   => 2,
				'area'    => 1500,
				'gallery' => array( $img ),
				'lat'     => 39.78,
				'lng'     => -89.65,
			),
			array(
				'type'     => array( $house ),
				'features' => array( $garden ),
			)
		);
		$this->assertSame( $img, (int) get_post_thumbnail_id( $id ) );
		$locs = wp_get_object_terms( $id, Post_Types::LOCATION, array( 'fields' => 'names' ) );
		$this->assertSame( array( 'Springfield' ), $locs, 'City becomes a location term' );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'index' ) . ' WHERE post_id = %d', $id ), ARRAY_A ); // phpcs:ignore
		$this->assertSame( 'publish', $row['status'] );
		$this->assertSame( 3, (int) $row['beds'] );
		$this->assertEqualsWithDelta( 139.35, (float) $row['area_m2'], 0.05, '1500 ft² stored as m²' );
		$this->assertStringContainsString( ',' . $garden . ',', $row['feature_ids'] );
		$this->assertEqualsWithDelta( 39.78, (float) $row['lat'], 0.0001 );
		wp_trash_post( $id );
		$this->assertNull( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'index' ) . ' WHERE post_id = %d', $id ) ) ); // phpcs:ignore
	}

	public function test_title_is_required() {
		$this->assertWPError( Property::save( array( 'title' => '  ' ) ) );
	}

	public function test_price_labels_and_area_units() {
		$f = array(
			'price'            => 2100,
			'listing_type'     => 'rent',
			'price_period'     => 'month',
			'price_on_request' => false,
			'price_prefix'     => '',
			'price_suffix'     => '',
		);
		$this->assertSame( '$2,100 / month', Property::price_label( $f ) );
		$f['price_on_request'] = true;
		$this->assertSame( 'Price on request', Property::price_label( $f ) );
		$this->assertSame( '$1.25M', Property::price_short( array( 'price' => 1250000, 'price_on_request' => false ) ) );
		$this->assertSame( '$450k', Property::price_short( array( 'price' => 450000, 'price_on_request' => false ) ) );
		Settings_Store::update(
			array(
				'currency_symbol'   => '€',
				'currency_position' => 'after_space',
				'thousands_sep'     => '.',
				'area_unit'         => 'sqm',
			)
		);
		$this->assertSame( '1.250.000 €', Property::money( 1250000 ) );
		$this->assertSame( '120 m²', Property::area_label( 120 ) );
		$this->assertSame( 100.0, Property::area_m2( 100 ) );
		$this->assertEqualsWithDelta( 9.29, Property::area_m2( 100, 'sqft' ), 0.01 );
	}

	public function test_tour_embeds_are_allow_listed() {
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', Property::embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
		$this->assertSame( 'https://player.vimeo.com/video/123456789', Property::embed_url( 'https://vimeo.com/123456789' ) );
		$this->assertSame( 'https://my.matterport.com/show/?m=abc123', Property::embed_url( 'https://my.matterport.com/show/?m=abc123' ) );
		$this->assertSame( '', Property::embed_url( 'https://evil.example.com/tour' ) );
		$this->assertSame( '', Property::embed_url( 'http://my.matterport.com/show/?m=abc' ), 'https only' );
		$this->assertSame( '', Property::embed_url( 'https://www.google.com/search?q=x' ), 'Google only as maps embed' );
	}

	public function test_agents_link_users_and_role() {
		$user   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$agency = Agents::save_agency( array( 'name' => 'Prairie Homes' ) );
		$agent  = Agents::save_agent(
			array(
				'name'      => 'Maya Chen',
				'user_id'   => $user,
				'agency_id' => $agency,
				'fields'    => array(
					'email'   => 'not-an-email',
					'phone'   => '+1 555 0100',
					'website' => 'javascript:alert(1)',
				),
			)
		);
		$this->assertSame( $agent, Agents::agent_for_user( $user ) );
		$this->assertTrue( Agents::is_agent( $user ) );
		$this->assertContains( Post_Types::ROLE, get_userdata( $user )->roles );
		$data = Agents::get( $agent );
		$this->assertSame( '', $data['fields']['email'] );
		$this->assertSame( '', $data['fields']['website'] );
		$this->assertSame( 'Prairie Homes', $data['agency'] );
		$this->assertSame( array( $agent ), Agents::agency_agents( $agency ) );
		$this->assertSame( get_userdata( $user )->user_email, Agents::email( $agent ), 'Falls back to the linked user email' );
		// Agent-role users without a profile get one on demand.
		$other = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		$this->assertSame( 0, Agents::agent_for_user( $other ) );
		$this->assertGreaterThan( 0, Agents::ensure_agent( $other ) );
		$this->assertSame( 0, Agents::ensure_agent( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );
	}

	public function test_agent_context_cannot_feature_or_reassign_and_is_moderated() {
		$user  = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		$id    = Property::save(
			array(
				'title'  => 'Agent listing',
				'status' => 'publish',
				'fields' => array(
					'featured' => true,
					'agent_id' => 999,
					'price'    => 1000,
				),
			),
			$user,
			'agent'
		);
		$agent = Agents::agent_for_user( $user );
		$this->assertSame( 'pending', get_post_status( $id ) );
		$this->assertFalse( Property::value( $id, 'featured' ) );
		$this->assertSame( $agent, Property::value( $id, 'agent_id' ) );
		Settings_Store::update( array( 'submission_moderation' => 'publish' ) );
		$id2 = Property::save( array( 'title' => 'Second' ), $user, 'agent' );
		$this->assertSame( 'publish', get_post_status( $id2 ) );
		$id3 = Property::save( array( 'title' => 'Draft one', 'status' => 'draft' ), $user, 'agent' ); // phpcs:ignore
		$this->assertSame( 'draft', get_post_status( $id3 ) );
	}

	public function test_mortgage_matches_the_annuity_formula() {
		$this->assertSame( 2022.62, Mortgage::monthly( 320000, 6.5, 30 ) );
		$this->assertSame( 1479.38, Mortgage::monthly( 200000, 4, 15 ) );
		$this->assertSame( 1000.0, Mortgage::monthly( 120000, 0, 10 ) );
		$this->assertSame( 0.0, Mortgage::monthly( 0, 5, 30 ) );
		$b = Mortgage::breakdown( 400000, 80000, 6.5, 30 );
		$this->assertSame( 320000.0, $b['loan'] );
		$this->assertCount( 30, $b['schedule'] );
		$this->assertEqualsWithDelta( 0, end( $b['schedule'] )['balance'], 1 );
		$this->assertEqualsWithDelta( $b['total_paid'] - 320000, $b['total_interest'], 0.01 );
	}

	public function test_real_estate_listing_schema() {
		$agent = Agents::save_agent(
			array(
				'name'   => 'Maya Chen',
				'fields' => array( 'phone' => '555' ),
			)
		);
		$id    = $this->property(
			'Maple House',
			array(
				'beds'       => 3,
				'baths'      => 2,
				'area'       => 1500,
				'year_built' => 1998,
				'street'     => '12 Elm St',
				'postcode'   => '62701',
				'region'     => 'IL',
				'country'    => 'US',
				'lat'        => 39.78,
				'lng'        => -89.65,
				'agent_id'   => $agent,
				'gallery'    => array( $this->image() ),
				'reference'  => 'MH-1',
			),
			array( 'type' => array( $this->term( 'House', Post_Types::TYPE ) ) )
		);
		$s     = Schema_Markup::property( $id );
		$this->assertSame( 'RealEstateListing', $s['@type'] );
		foreach ( array( 'name', 'url', 'image', 'datePosted', 'offers', 'about' ) as $key ) {
			$this->assertArrayHasKey( $key, $s );
		}
		$this->assertSame( 300000.0, $s['offers']['price'] );
		$this->assertSame( 'USD', $s['offers']['priceCurrency'] );
		$this->assertStringContainsString( 'Sell', $s['offers']['businessFunction'] );
		$this->assertSame( 'RealEstateAgent', $s['offers']['offeredBy']['@type'] );
		$this->assertSame( 'SingleFamilyResidence', $s['about']['@type'] );
		$this->assertSame( '12 Elm St', $s['about']['address']['streetAddress'] );
		$this->assertSame( 'FTK', $s['about']['floorSize']['unitCode'] );
		$this->assertSame( 3, $s['about']['numberOfBedrooms'] );
		$this->assertEqualsWithDelta( 39.78, $s['about']['geo']['latitude'], 0.0001 );
		$this->assertSame( 'MH-1', $s['identifier'] );
		// Hidden address: no street, no geo.
		update_post_meta( $id, Property::meta_key( 'hide_address' ), true );
		$s = Schema_Markup::property( $id );
		$this->assertArrayNotHasKey( 'streetAddress', $s['about']['address'] );
		$this->assertArrayNotHasKey( 'geo', $s['about'] );
		// Rent: lease + unit price.
		update_post_meta( $id, Property::meta_key( 'listing_type' ), 'rent' );
		$s = Schema_Markup::property( $id );
		$this->assertStringContainsString( 'LeaseOut', $s['offers']['businessFunction'] );
		$this->assertSame( 'MON', $s['offers']['priceSpecification']['unitCode'] );
		$this->assertSame( 'Apartment', Schema_Markup::residence_type( 'Condo' ) );
	}

	public function test_single_page_renders_layout_and_ai_label() {
		$id = $this->property(
			'Maple House',
			array(
				'beds'             => 3,
				'neighbourhood'    => 'Quiet streets and a park nearby.',
				'neighbourhood_ai' => true,
				'tour_url'         => 'https://my.matterport.com/show/?m=abc',
				'highlights'       => array( 'South-facing garden' ),
				'lat'              => 39.7,
				'lng'              => -89.6,
			)
		);
		$html = \Dev3\AiRealEstate\Front\Single::property( $id, '<p>Lovely home.</p>' );
		$this->assertStringContainsString( 'AI-generated summary', $html );
		$this->assertStringContainsString( 'South-facing garden', $html );
		$this->assertStringContainsString( 'data-dev3re-embed="https://my.matterport.com/show/?m=abc"', $html );
		$this->assertStringContainsString( 'data-dev3re-mortgage', $html );
		$this->assertStringContainsString( 'data-dev3re-enquiry', $html );
		$this->assertStringNotContainsString( '<iframe', $html, 'Tours load on click only' );
		update_post_meta( $id, Property::meta_key( 'neighbourhood_ai' ), false );
		$this->assertStringNotContainsString( 'AI-generated summary', \Dev3\AiRealEstate\Front\Single::property( $id, '' ) );
	}
}
