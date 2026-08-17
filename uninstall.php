<?php
declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$settings = get_option('bffrm_settings', []);
$remove   = is_array($settings) && ! empty($settings['remove_data_on_uninstall']);

if (! $remove) {
    return;
}

// Drop tables.
$tables = [
    $wpdb->prefix . 'bffrm_audit_log',
    $wpdb->prefix . 'bffrm_rate_limits',
    $wpdb->prefix . 'bffrm_submissions',
    $wpdb->prefix . 'bffrm_forms',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove options.
delete_option('bffrm_settings');
delete_option('bffrm_ip_hmac_key');
delete_option('bffrm_antispam_notice_dismissed');
