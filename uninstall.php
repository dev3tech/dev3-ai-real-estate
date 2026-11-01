<?php
/**
 * Uninstall: removes data only when "delete data on uninstall" is enabled.
 *
 * @package Dev3\AiRealEstate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dev3re_settings = get_option( 'dev3re_settings', array() );
if ( empty( $dev3re_settings['delete_on_uninstall'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'dev3re_usage', 'dev3re_core_cache', 'dev3re_core_jobs', 'dev3re_core_embeddings', 'dev3re_index', 'dev3re_leads', 'dev3re_searches' ) as $dev3re_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$dev3re_table}" );
}
foreach ( array( 'dev3re_settings', 'dev3re_license', 'dev3re_db_version', 'dev3re_terms_seeded' ) as $dev3re_option ) {
	delete_option( $dev3re_option );
}
delete_transient( 'dev3re_models' );
delete_site_transient( 'dev3re_update_info' );
wp_unschedule_hook( 'dev3re_run_job' );
wp_unschedule_hook( 'dev3re_alerts' );
wp_unschedule_hook( 'dev3re_daily' );
// Listings, agents and agencies (with their meta) and the plugin taxonomies.
foreach ( array( 'dev3re_property', 'dev3re_agent', 'dev3re_agency' ) as $dev3re_type ) {
	$dev3re_ids = get_posts(
		array(
			'post_type'      => $dev3re_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $dev3re_ids as $dev3re_id ) {
		wp_delete_post( $dev3re_id, true );
	}
}
foreach ( array( 'dev3re_type', 'dev3re_location', 'dev3re_feature' ) as $dev3re_tax ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$dev3re_terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $dev3re_tax ) );
	foreach ( $dev3re_terms as $dev3re_term ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$wpdb->term_taxonomy,
			array(
				'term_id'  => (int) $dev3re_term,
				'taxonomy' => $dev3re_tax,
			)
		);
	}
}
delete_metadata( 'user', 0, '_dev3re_favourites', '', true );
delete_metadata( 'user', 0, '_dev3re_agent_id', '', true );
remove_role( 'dev3re_agent' );
