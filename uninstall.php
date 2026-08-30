<?php
/**
 * Remove everything AI Agent Activity Log stored.
 *
 * The log table and the two options that track it. On a network the plugin can
 * have run on any subset of sites, each with its own table, so every site is
 * visited - and only this plugin's own data is touched in each.
 *
 * @package AI_Agent_Activity_Log
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop this site's log table and forget the options that describe it.
 *
 * A closure rather than a named function: an uninstall file is included, and
 * a name declared at the top level of an included file cannot be included
 * twice - which the tests do, once for a single site and once for a network.
 *
 * @var callable $ai_agent_activity_log_uninstall_site
 */
$ai_agent_activity_log_uninstall_site = function () {
	global $wpdb;

	// The table name is built here, never taken from input, so it cannot be
	// prepared as a value - identifiers are not parameters in MySQL.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'ai_agent_activity_log`' );

	delete_option( 'ai_agent_activity_log_schema' );
	delete_option( 'ai_agent_activity_log_last_prune' );
};

if ( is_multisite() ) {
	$ai_agent_activity_log_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $ai_agent_activity_log_sites as $ai_agent_activity_log_site_id ) {
		switch_to_blog( (int) $ai_agent_activity_log_site_id );
		$ai_agent_activity_log_uninstall_site();
		restore_current_blog();
	}
} else {
	$ai_agent_activity_log_uninstall_site();
}
