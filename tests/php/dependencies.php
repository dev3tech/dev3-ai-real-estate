<?php
/**
 * Test dependencies: HTTP guard, mail sender and shared helpers.
 *
 * @package Dev3\AiRealEstate
 */

tests_add_filter( 'dev3re_retry_delay_ms', '__return_zero' );
tests_add_filter(
	'wp_mail_from',
	static function () {
		return 'wordpress@example.org';
	}
);

require_once __DIR__ . '/helpers.php';

// No real network in tests: anything not answered by a test's own mock is refused.
tests_add_filter( 'pre_http_request', array( 'Dev3re_Http', 'handle' ), 99, 3 );
