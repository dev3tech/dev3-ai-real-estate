<?php
/**
 * Shared test helpers: HTTP mock and Gemini responses.
 *
 * @package Dev3\AiRealEstate
 */

/**
 * Routes outgoing HTTP to test handlers (pattern => callable|array), refuses everything else.
 */
class Dev3re_Http {

	/**
	 * Handlers.
	 *
	 * @var array
	 */
	public static $routes = array();

	/**
	 * Recorded requests.
	 *
	 * @var array
	 */
	public static $requests = array();

	public static function reset() {
		self::$routes   = array();
		self::$requests = array();
	}

	public static function on( $pattern, $handler ) {
		self::$routes[ $pattern ] = $handler;
	}

	public static function count( $pattern ) {
		return count(
			array_filter(
				self::$requests,
				static function ( $r ) use ( $pattern ) {
					return false !== strpos( $r['url'], $pattern );
				}
			)
		);
	}

	public static function handle( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		foreach ( self::$routes as $pattern => $handler ) {
			if ( false !== strpos( $url, $pattern ) ) {
				$body             = isset( $args['body'] ) ? $args['body'] : '';
				self::$requests[] = array(
					'url'  => $url,
					'args' => $args,
					'json' => is_string( $body ) ? json_decode( $body, true ) : $body,
				);
				$res              = is_callable( $handler ) ? call_user_func( $handler, end( self::$requests ) ) : $handler;
				return array(
					'headers'  => array(),
					'body'     => is_string( $res[1] ) ? $res[1] : wp_json_encode( $res[1] ),
					'response' => array(
						'code'    => $res[0],
						'message' => 'x',
					),
					'cookies'  => array(),
				);
			}
		}
		return new WP_Error( 'http_blocked', 'Blocked in tests: ' . $url );
	}

	public static function gemini( array $data ) {
		return array(
			200,
			array(
				'candidates'    => array(
					array(
						'content'      => array( 'parts' => array( array( 'text' => wp_json_encode( $data ) ) ) ),
						'finishReason' => 'STOP',
					),
				),
				'usageMetadata' => array(
					'promptTokenCount'     => 120,
					'candidatesTokenCount' => 60,
					'totalTokenCount'      => 180,
				),
			),
		);
	}
}
