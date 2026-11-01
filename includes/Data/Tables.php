<?php
/**
 * Plugin tables (added to the core dbDelta run through dev3re_schema_sql).
 *
 * @package Dev3\AiRealEstate
 */

namespace Dev3\AiRealEstate\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Table names + SQL.
 */
class Tables {

	/**
	 * Table name.
	 *
	 * @param string $name index|leads|searches.
	 * @return string
	 */
	public static function name( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'dev3re_' . $name;
	}

	/**
	 * All plugin table names.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array( self::name( 'index' ), self::name( 'leads' ), self::name( 'searches' ) );
	}

	/**
	 * Adds CREATE TABLE statements.
	 *
	 * @param array  $sql     Statements.
	 * @param string $charset Charset collate.
	 * @return array
	 */
	public static function sql( $sql, $charset ) {
		$index    = self::name( 'index' );
		$leads    = self::name( 'leads' );
		$searches = self::name( 'searches' );
		$sql[]    = "CREATE TABLE {$index} (
			post_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			listing_type varchar(10) NOT NULL DEFAULT 'sale',
			market varchar(20) NOT NULL DEFAULT 'available',
			price decimal(16,2) NOT NULL DEFAULT 0,
			beds smallint(5) unsigned NOT NULL DEFAULT 0,
			baths decimal(5,1) NOT NULL DEFAULT 0,
			area_m2 decimal(12,2) NOT NULL DEFAULT 0,
			year_built smallint(5) unsigned NOT NULL DEFAULT 0,
			lat decimal(10,7) DEFAULT NULL,
			lng decimal(10,7) DEFAULT NULL,
			type_ids varchar(191) NOT NULL DEFAULT '',
			location_ids varchar(191) NOT NULL DEFAULT '',
			feature_ids text NOT NULL,
			featured tinyint(1) unsigned NOT NULL DEFAULT 0,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			search_text text NOT NULL,
			published_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY status_type (status,listing_type),
			KEY price (price),
			KEY geo (lat,lng),
			KEY agent (agent_id)
		) {$charset};";
		$sql[]    = "CREATE TABLE {$leads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 0,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			message text NOT NULL,
			kind varchar(20) NOT NULL DEFAULT 'enquiry',
			viewing_at varchar(40) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'new',
			notes text NOT NULL,
			source varchar(40) NOT NULL DEFAULT '',
			ip_hash char(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY agent_status (agent_id,status),
			KEY property (property_id),
			KEY created (created_at)
		) {$charset};";
		$sql[]    = "CREATE TABLE {$searches} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			label varchar(190) NOT NULL DEFAULT '',
			query longtext NOT NULL,
			frequency varchar(10) NOT NULL DEFAULT 'daily',
			token char(32) NOT NULL DEFAULT '',
			confirmed tinyint(1) unsigned NOT NULL DEFAULT 0,
			last_sent datetime DEFAULT NULL,
			last_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sent_count int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY frequency (frequency,confirmed)
		) {$charset};";
		return $sql;
	}
}
