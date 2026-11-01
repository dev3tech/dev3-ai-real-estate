<?php
/**
 * Plugin Name:       DEV3 AI Real Estate Listings for WordPress
 * Plugin URI:        https://dev3.tech/item/dev3-ai-real-estate
 * Description:       Property listings for sale and rent with agents, map search, saved searches, mortgage calculator and DEV3 AI descriptions from photos.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DEV3 Technologies
 * Author URI:        https://dev3.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dev3-ai-real-estate
 * Domain Path:       /languages
 *
 * @package Dev3\AiRealEstate
 */

defined( 'ABSPATH' ) || exit;

define( 'DEV3RE_VERSION', '1.0.0' );
define( 'DEV3RE_FILE', __FILE__ );
define( 'DEV3RE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DEV3RE_URL', plugin_dir_url( __FILE__ ) );
define( 'DEV3RE_SLUG', 'dev3-ai-real-estate' );
define( 'DEV3RE_NAME', 'DEV3 AI Real Estate Listings for WordPress' );
define( 'DEV3RE_MIN_PHP', '7.4' );
define( 'DEV3RE_MIN_WP', '6.0' );
define( 'DEV3RE_DB_VERSION', '1.0.0' );

if ( version_compare( PHP_VERSION, DEV3RE_MIN_PHP, '<' ) ) {
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Dev3\AiRealEstate\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = DEV3RE_PATH . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( '\Dev3\AiRealEstate\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Dev3\AiRealEstate\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( '\Dev3\AiRealEstate\Plugin', 'instance' ) );
