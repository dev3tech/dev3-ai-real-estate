<?php
/**
 * DEV3 AI features with Gemini and Overpass mocked over pre_http_request.
 *
 * @package Dev3\AiRealEstate
 */

require_once __DIR__ . '/testcase.php';

use Dev3\AiRealEstate\Ai\Describer;
use Dev3\AiRealEstate\Ai\Neighbourhood;
use Dev3\AiRealEstate\Ai\Nl_Search;
use Dev3\AiRealEstate\Core3\Core\Cache;
use Dev3\AiRealEstate\Core3\Core\Settings_Store;
use Dev3\AiRealEstate\Data\Agents;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;
use Dev3\AiRealEstate\Search\Amenities;

class AiTest extends Dev3re_TestCase {

	private function overpass() {
		return array(
			200,
			array(
				'elements' => array(
					array( 'type' => 'node', 'lat' => 39.781, 'lon' => -89.651, 'tags' => array( 'amenity' => 'school', 'name' => 'Lincoln Elementary' ) ),
					array( 'type' => 'way', 'center' => array( 'lat' => 39.785, 'lon' => -89.655 ), 'tags' => array( 'leisure' => 'park', 'name' => 'Washington Park' ) ),
					array( 'type' => 'node', 'lat' => 39.779, 'lon' => -89.649, 'tags' => array( 'highway' => 'bus_stop' ) ),
					array( 'type' => 'node', 'lat' => 39.782, 'lon' => -89.650, 'tags' => array( 'shop' => 'supermarket', 'name' => 'County Market' ) ),
					array( 'type' => 'node', 'lat' => 39.79, 'lon' => -89.66, 'tags' => array( 'amenity' => 'bench' ) ),
				),
			),
		);
	}

	public function test_description_from_photos_sends_inline_images_and_filters_features() {
		$this->with_key();
		$garden = $this->term( 'Garden', Post_Types::FEATURE );
		$this->term( 'Fireplace', Post_Types::FEATURE );
		$img1 = $this->image();
		$img2 = $this->image();
		Dev3re_Http::on(
			'generativelanguage.googleapis.com',
			Dev3re_Http::gemini(
				array(
					'title'        => 'Light-filled family home with a garden',
					'excerpt'      => 'Three bedrooms and a big garden.',
					'description'  => "First paragraph.\n\nSecond <script>x</script> paragraph.",
					'highlights'   => array( 'Big garden', 'Open-plan kitchen' ),
					'features'     => array( 'garden', 'Helipad' ),
					'observations' => array( 'Kitchen with island' ),
				)
			)
		);
		$listing = array(
			'title'    => 'Draft',
			'type'     => array( $this->term( 'House', Post_Types::TYPE ) ),
			'features' => array(),
			'fields'   => array(
				'beds'    => 3,
				'city'    => 'Springfield',
				'gallery' => array( $img1, $img2 ),
				'notes'   => 'New roof 2022',
			),
		);
		$res     = Describer::generate( $listing );
		$this->assertIsArray( $res, is_wp_error( $res ) ? $res->get_error_message() : '' );
		$this->assertSame( 2, $res['photos'] );
		$this->assertSame( 'Garden', $res['draft']['features'], 'Only site vocabulary, canonical case' );
		$this->assertSame( array( $garden ), $res['feature_ids'] );
		$this->assertSame( "<p>First paragraph.</p>\n<p>Second &lt;script&gt;x&lt;/script&gt; paragraph.</p>", $res['draft']['content'] );
		$this->assertSame( "Big garden\nOpen-plan kitchen", $res['draft']['highlights'] );
		$body  = Dev3re_Http::$requests[0]['json'];
		$parts = $body['contents'][0]['parts'];
		$this->assertCount( 3, $parts, 'Two images + the prompt' );
		$this->assertSame( 'image/png', $parts[0]['inline_data']['mime_type'] );
		$this->assertNotEmpty( base64_decode( $parts[0]['inline_data']['data'], true ) ); // phpcs:ignore
		$this->assertStringContainsString( 'New roof 2022', $parts[2]['text'] );
		$this->assertStringContainsString( 'Bedrooms: 3', $parts[2]['text'] );
		$this->assertStringContainsString( 'Fireplace, Garden', $parts[2]['text'] );
		$this->assertArrayHasKey( 'responseSchema', $body['generationConfig'] );
		// Nothing was saved: a draft only.
		$this->assertSame( 0, (int) ( new WP_Query( array( 'post_type' => Post_Types::PROPERTY, 'post_status' => 'any', 'fields' => 'ids' ) ) )->found_posts );
		// Cached for identical input.
		Cache::reset_memo();
		$this->assertTrue( Describer::generate( $listing )['cached'] );
		$this->assertSame( 1, Dev3re_Http::count( 'generativelanguage' ) );
	}

	public function test_agents_cannot_send_other_peoples_images() {
		$this->with_key();
		$owner = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		$other = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		$img   = $this->image( $owner );
		$this->assertCount( 1, Describer::images( array( $img ), $owner ) );
		$this->assertCount( 0, Describer::images( array( $img ), $other ) );
		$this->assertCount( 1, Describer::images( array( $img ), $other, array( $img ) ), 'Gallery of a property the user may edit' );
		$this->assertWPError( Describer::generate( array( 'fields' => array( 'gallery' => array( $img ) ) ), array(), 'submission', $other ), 'No photos and no notes left' );
	}

	public function test_template_description_works_without_ai() {
		$res = Describer::template(
			array(
				'type'     => array( $this->term( 'Villa', Post_Types::TYPE ) ),
				'features' => array( $this->term( 'Swimming pool', Post_Types::FEATURE ) ),
				'fields'   => array(
					'beds'         => 4,
					'baths'        => 3,
					'listing_type' => 'sale',
					'city'         => 'Malaga',
				),
			)
		);
		$this->assertTrue( $res['fallback'] );
		$this->assertSame( '4-bedroom villa for sale in Malaga', $res['draft']['title'] );
		$this->assertStringContainsString( 'swimming pool', $res['draft']['content'] );
		$this->assertSame( 0, count( Dev3re_Http::$requests ) );
	}

	public function test_neighbourhood_grounded_in_overpass_and_labelled() {
		$this->with_key();
		Dev3re_Http::on( 'overpass-api.de', $this->overpass() );
		Dev3re_Http::on(
			'generativelanguage.googleapis.com',
			Dev3re_Http::gemini(
				array(
					'summary'    => 'A calm street a short walk from Washington Park.',
					'highlights' => array( 'Lincoln Elementary 2 minutes away' ),
				)
			)
		);
		$fields = array(
			'street' => '12 Elm St',
			'city'   => 'Springfield',
			'lat'    => 39.78,
			'lng'    => -89.65,
		);
		$res    = Neighbourhood::generate( $fields );
		$this->assertTrue( $res['ai'] );
		$this->assertSame( 'A calm street a short walk from Washington Park.', $res['draft']['summary'] );
		$this->assertSame( array( 'schools', 'transit', 'parks', 'shops' ), array_keys( $res['amenities'] ) );
		$this->assertSame( 'Lincoln Elementary', $res['amenities']['schools']['names'][0]['name'] );
		$this->assertStringStartsWith( 'Schools · Lincoln Elementary · ', $res['nearby'][0] );
		$prompt = Dev3re_Http::$requests[1]['json']['contents'][0]['parts'][0]['text'];
		$this->assertStringContainsString( 'Washington Park', $prompt );
		// Overpass is cached: a second call does not refetch.
		Cache::reset_memo();
		Neighbourhood::generate( $fields, 'admin', false );
		$this->assertSame( 1, Dev3re_Http::count( 'overpass-api.de' ) );
		// Facts-only mode needs no AI.
		Settings_Store::update( array( 'ai_enabled' => false ) );
		$facts = Neighbourhood::generate( $fields, 'admin', false );
		$this->assertFalse( $facts['ai'] );
		$this->assertStringContainsString( 'Springfield', $facts['draft']['summary'] );
		$this->assertStringContainsString( 'nearest: Lincoln Elementary', $facts['draft']['highlights'] );
	}

	public function test_amenity_classifier() {
		$this->assertSame( 'transit', Amenities::classify( array( 'railway' => 'station' ) ) );
		$this->assertSame( 'health', Amenities::classify( array( 'amenity' => 'pharmacy' ) ) );
		$this->assertSame( '', Amenities::classify( array( 'amenity' => 'bench' ) ) );
		$this->assertStringContainsString( 'around:1000,39.780000,-89.650000', Amenities::query( 39.78, -89.65 ) );
	}

	public function test_nl_search_uses_ai_validated_against_vocabulary() {
		$this->with_key();
		$house = $this->term( 'House', Post_Types::TYPE );
		$this->term( 'Garden', Post_Types::FEATURE );
		$this->term( 'Springfield', Post_Types::LOCATION );
		Dev3re_Http::on(
			'generativelanguage.googleapis.com',
			Dev3re_Http::gemini(
				array(
					'listing_type' => 'sale',
					'types'        => array( 'house', 'Castle' ),
					'features'     => array( 'Garden' ),
					'locations'    => array( 'Springfield' ),
					'price_max'    => 450000,
					'beds'         => 3,
					'keywords'     => 'house garden',
					'sort'         => 'bogus',
				)
			)
		);
		$res = Nl_Search::interpret( 'family home with a garden in springfield under 450k, 3 beds' );
		$this->assertTrue( $res['ai'] );
		$this->assertSame( array( 'house' ), $res['query']['type'] );
		$this->assertSame( array( 'garden' ), $res['query']['features'] );
		$this->assertSame( array( 'springfield' ), $res['query']['location'] );
		$this->assertSame( 450000, $res['query']['price_max'] );
		$this->assertArrayNotHasKey( 'q', $res['query'], 'Keywords that repeat filters are dropped' );
		$this->assertArrayNotHasKey( 'sort', $res['query'] );
		$this->assertContains( 'Up to $450,000', wp_list_pluck( $res['chips'], 'label' ) );
		Cache::reset_memo();
		$this->assertTrue( Nl_Search::interpret( 'family home with a garden in springfield under 450k, 3 beds' )['cached'] );
		$this->assertSame( 1, Dev3re_Http::count( 'generativelanguage' ) );
		unset( $house );
	}

	public function test_nl_search_falls_back_without_ai_and_with_storefront_ai_off() {
		$this->with_key();
		$this->term( 'Apartment', Post_Types::TYPE );
		Settings_Store::update( array( 'public_ai' => false ) );
		$res = Nl_Search::interpret( '2 bed flat for rent under 1500' );
		$this->assertFalse( $res['ai'] );
		$this->assertSame( 'rent', $res['query']['listing_type'] );
		$this->assertSame( 2, $res['query']['beds'] );
		$this->assertSame( array( 'apartment' ), $res['query']['type'] );
		$this->assertSame( 0, count( Dev3re_Http::$requests ), 'Storefront AI switch is respected' );
	}

	public function test_admin_ai_routes_and_agent_submission_context() {
		$this->with_key();
		Dev3re_Http::on( 'generativelanguage.googleapis.com', Dev3re_Http::gemini( array( 'title' => 'T', 'excerpt' => 'E', 'description' => 'D', 'highlights' => array(), 'features' => array() ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$res = $this->rest( 'POST', 'admin/ai/describe', array( 'listing' => array( 'fields' => array( 'notes' => 'Corner plot' ) ) ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'T', $res->get_data()['draft']['title'] );
		$tpl = $this->rest( 'POST', 'admin/ai/describe', array( 'mode' => 'template', 'listing' => array( 'fields' => array( 'beds' => 2 ) ) ) )->get_data();
		$this->assertTrue( $tpl['fallback'] );
		// Agents use the submission context, which the storefront switch turns off.
		$agent = self::factory()->user->create( array( 'role' => Post_Types::ROLE ) );
		Agents::ensure_agent( $agent );
		wp_set_current_user( $agent );
		Settings_Store::update( array( 'public_ai' => false ) );
		$res = $this->rest( 'POST', 'agent/ai/describe', array( 'listing' => array( 'fields' => array( 'notes' => 'Corner plot' ) ) ) );
		$this->assertSame( 'dev3re_public_ai_disabled', $res->get_data()['code'] );
		$this->assertSame( 1, Dev3re_Http::count( 'generativelanguage' ) );
		unset( $tpl );
	}

	public function test_property_page_marks_ai_neighbourhood() {
		$id = $this->property( 'Maple', array( 'neighbourhood' => 'Near the park', 'neighbourhood_ai' => true ) );
		$this->assertTrue( Property::value( $id, 'neighbourhood_ai' ) );
	}
}
