<?php
/**
 * Admin menu + React app mount, CSV export and edit-link redirects.
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Admin;

use Dev3\AiRealEstate\Core3\Core\Assets;
use Dev3\AiRealEstate\Core3\Core\Capabilities;
use Dev3\AiRealEstate\Data\Leads;
use Dev3\AiRealEstate\Data\Post_Types;
use Dev3\AiRealEstate\Front\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level menu page.
 */
class Admin_Page {

	/**
	 * Hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_dev3re_export_leads', array( $this, 'export_leads' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DEV3RE_FILE ), array( $this, 'links' ) );
		add_action( 'load-post.php', array( $this, 'redirect_classic_editor' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_classic_editor' ) );
		add_action( 'load-edit.php', array( $this, 'redirect_classic_editor' ) );
	}

	/**
	 * App config for the admin entry (no secrets).
	 *
	 * @return array
	 */
	public static function config() {
		$front = Frontend::config();
		return array(
			'map'       => $front['map'],
			'exportUrl' => add_query_arg(
				array(
					'action'   => 'dev3re_export_leads',
					'_wpnonce' => wp_create_nonce( 'dev3re_export_leads' ),
				),
				admin_url( 'admin-post.php' )
			),
			'siteUrl'   => home_url( '/' ),
		);
	}

	/**
	 * Menu.
	 */
	public function menu() {
		$this->hook = (string) add_menu_page( DEV3RE_NAME, __( 'DEV3 AI Real Estate', 'dev3-ai-real-estate' ), Capabilities::manage(), DEV3RE_SLUG, array( $this, 'render' ), 'dashicons-admin-home', 26 );
	}

	/**
	 * Mount point.
	 */
	public function render() {
		// The h1 names the page for screen readers; wp-header-end keeps admin notices above the app.
		echo '<div class="wrap dev3re-wrap"><h1 class="screen-reader-text">' . esc_html( DEV3RE_NAME ) . '</h1><hr class="wp-header-end"><div id="dev3re-admin-root" class="dev3re-root dev3re-root--app"></div></div>';
	}

	/**
	 * Assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( $hook === $this->hook ) {
			wp_enqueue_media();
			Assets::enqueue( 'admin' );
		}
	}

	/**
	 * The classic editor / list screens for our post types open the app instead.
	 */
	public function redirect_classic_editor() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) {
			return; // The WordPress trash list stays available for restoring.
		}
		// phpcs:enable
		if ( $id ) {
			$type = (string) get_post_type( $id );
		}
		$routes = array(
			Post_Types::PROPERTY => 'properties',
			Post_Types::AGENT    => 'agents',
			Post_Types::AGENCY   => 'agencies',
		);
		if ( ! isset( $routes[ $type ] ) || ! current_user_can( Capabilities::manage() ) ) {
			return;
		}
		$hash = '#/' . $routes[ $type ] . ( $id ? '/' . $id : ( 'load-post-new.php' === current_action() ? '/new' : '' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . DEV3RE_SLUG ) . $hash );
		exit;
	}

	/**
	 * CSV export of leads.
	 */
	public function export_leads() {
		if ( ! current_user_can( Capabilities::manage() ) || ! check_admin_referer( 'dev3re_export_leads' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'dev3-ai-real-estate' ), 403 );
		}
		$args = array(
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=dev3re-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		foreach ( Leads::export_rows( $args ) as $row ) {
			// Neutralise spreadsheet formulas.
			$row = array_map(
				static function ( $v ) {
					$v = (string) $v;
					return preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
				},
				$row
			);
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Settings link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . DEV3RE_SLUG ) ) . '">' . esc_html__( 'Settings', 'dev3-ai-real-estate' ) . '</a>' );
		return $links;
	}
}
