<?php
/**
 * Bootstrap and wiring.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate;

use Dev3\AiRealEstate\Core3\Core\Assets;
use Dev3\AiRealEstate\Core3\Core\Privacy;
use Dev3\AiRealEstate\Core3\Core\Schema;
use Dev3\AiRealEstate\Data\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton entry point.
 */
final class Plugin {

	const CRON_HOOK  = 'dev3re_daily';
	const ALERT_HOOK = 'dev3re_alerts';

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boots once.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		self::register_schema();
		Schema::install( DEV3RE_DB_VERSION );
		Post_Types::register();
		Post_Types::add_role();
		Post_Types::default_terms();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::ALERT_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::ALERT_HOOK );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( self::ALERT_HOOK );
		flush_rewrite_rules();
	}

	/**
	 * Plugin tables / settings defaults are registered here so activation and runtime agree.
	 */
	public static function register_schema() {
		if ( ! has_filter( 'dev3re_settings_defaults', array( Settings::class, 'defaults' ) ) ) {
			add_filter( 'dev3re_settings_defaults', array( Settings::class, 'defaults' ) );
			add_filter( 'dev3re_settings_sanitize', array( Settings::class, 'sanitize' ), 10, 3 );
			add_filter( 'dev3re_schema_sql', array( Data\Tables::class, 'sql' ), 10, 2 );
		}
	}

	/**
	 * Hooks.
	 */
	private function boot() {
		self::register_schema();
		add_action( 'init', array( $this, 'textdomain' ), 1 );
		add_action( 'admin_init', static fn() => Schema::maybe_install( DEV3RE_DB_VERSION ) );
		add_action( self::CRON_HOOK, array( Schema::class, 'prune_usage' ) );
		add_action( self::ALERT_HOOK, array( Data\Searches::class, 'run_alerts' ) );
		add_action( 'rest_api_init', array( $this, 'rest' ) );
		add_action( 'init', array( Ai\Prompts_Library::class, 'register' ) );
		add_action( 'init', array( Privacy_Data::class, 'init' ) );
		Privacy::init();
		Post_Types::init();
		Data\Index::init();
		Emails\Mailer::init();
		Front\Frontend::init();
		add_action( 'init', static fn() => Assets::register( array( 'admin' ), Admin\Admin_Page::config() ) );
		if ( is_admin() ) {
			( new Admin\Admin_Page() )->register();
		}
	}

	/**
	 * Loads translations (on init, as WP 6.7+ expects).
	 */
	public function textdomain() {
		load_plugin_textdomain( 'dev3-ai-real-estate', false, dirname( plugin_basename( DEV3RE_FILE ) ) . '/languages' );
	}

	/**
	 * REST routes.
	 */
	public function rest() {
		( new Core3\Rest\Core_Controller() )->register_routes();
		( new Rest\Public_Controller() )->register_routes();
		( new Rest\Agent_Controller() )->register_routes();
		( new Rest\Admin_Controller() )->register_routes();
	}
}
