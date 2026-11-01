<?php
/**
 * Base test case.
 *
 * @package Dev3\AiRealEstate
 */

use Dev3\AiRealEstate\Core3\Api\Ai;
use Dev3\AiRealEstate\Core3\Core\Cache;
use Dev3\AiRealEstate\Core3\Core\Schema;
use Dev3\AiRealEstate\Core3\Core\Settings_Store;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Data\Property;

/**
 * Fresh tables, settings and mocks for every test.
 */
abstract class Dev3re_TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Schema::install( DEV3RE_DB_VERSION );
		update_option( Settings_Store::OPTION, array() );
		Settings_Store::flush();
		Cache::reset_memo();
		Ai::$client = null;
		Dev3re_Http::reset();
		add_filter( 'dev3re_pre_geocode', '__return_false' );
		add_filter( 'dev3re_retry_delay_ms', '__return_zero' );
		\Dev3\AiRealEstate\Ai\Prompts_Library::register();
		\Dev3\AiRealEstate\Core3\Data\Usage_Repository::flush();
		Post_Types::register();
		Post_Types::add_role();
		reset_phpmailer_instance();
		global $wpdb;
		foreach ( \Dev3\AiRealEstate\Data\Tables::all() as $table ) {
			$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore
		}
		$GLOBALS['wp_rest_server'] = null;
	}

	public function tear_down() {
		remove_filter( 'dev3re_pre_geocode', '__return_false' );
		Dev3re_Http::reset();
		parent::tear_down();
	}

	protected function with_key() {
		Settings_Store::update( array( 'api_key' => 'AIza-test-key-000000000000' ) );
		Settings_Store::flush();
	}

	/**
	 * Term id (created when missing).
	 *
	 * @param string $name     Name.
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	protected function term( $name, $taxonomy ) {
		$t = term_exists( $name, $taxonomy );
		if ( ! $t ) {
			$t = wp_insert_term( $name, $taxonomy );
		}
		return (int) $t['term_id'];
	}

	/**
	 * Published property.
	 *
	 * @param string $title  Title.
	 * @param array  $fields Fields.
	 * @param array  $extra  Extra save data.
	 * @return int
	 */
	protected function property( $title, array $fields = array(), array $extra = array() ) {
		$id = Property::save(
			array_merge(
				array(
					'title'  => $title,
					'status' => 'publish',
					'fields' => array_merge( array( 'listing_type' => 'sale', 'price' => 300000, 'city' => 'Springfield' ), $fields ), // phpcs:ignore
				),
				$extra
			),
			1,
			'admin'
		);
		$this->assertIsInt( $id );
		return $id;
	}

	/**
	 * REST request helper.
	 *
	 * @param string $method Method.
	 * @param string $route  Route under dev3re/v1.
	 * @param array  $body   JSON body or query params.
	 * @param bool   $nonce  Send a nonce.
	 * @param array  $headers Headers.
	 * @return WP_REST_Response
	 */
	protected function rest( $method, $route, array $body = array(), $nonce = true, array $headers = array() ) {
		if ( ! did_action( 'rest_api_init' ) || empty( $GLOBALS['wp_rest_server'] ) ) {
			rest_get_server();
		}
		$req = new WP_REST_Request( $method, '/dev3re/v1/' . ltrim( $route, '/' ) );
		if ( 'GET' === $method ) {
			$req->set_query_params( $body );
		} else {
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body( wp_json_encode( $body ) );
		}
		if ( $nonce ) {
			$req->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		}
		foreach ( $headers as $k => $v ) {
			$req->set_header( $k, $v );
		}
		return rest_do_request( $req );
	}

	/**
	 * Tiny real JPEG attachment (for vision tests).
	 *
	 * @param int $author Author.
	 * @return int
	 */
	protected function image( $author = 1 ) {
		$file = wp_tempnam( 'dev3re' ) . '.png';
		$img  = imagecreatetruecolor( 40, 30 );
		imagefill( $img, 0, 0, imagecolorallocate( $img, 30, 120, 90 ) );
		imagepng( $img, $file );
		imagedestroy( $img );
		$id = self::factory()->attachment->create_upload_object( $file );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_author' => $author,
			)
		);
		return (int) $id;
	}
}
