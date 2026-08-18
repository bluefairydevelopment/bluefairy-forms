<?php
declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$bffrm_settings = get_option('bffrm_settings', []);
$bffrm_remove   = is_array($bffrm_settings) && ! empty($bffrm_settings['remove_data_on_uninstall']);

if (! $bffrm_remove) {
    return;
}

// Drop tables.
$bffrm_tables = [
    $wpdb->prefix . 'bffrm_audit_log',
    $wpdb->prefix . 'bffrm_rate_limits',
    $wpdb->prefix . 'bffrm_submissions',
    $wpdb->prefix . 'bffrm_forms',
];

foreach ($bffrm_tables as $bffrm_table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$bffrm_table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove options.
delete_option('bffrm_settings');
delete_option('bffrm_ip_hmac_key');
delete_option('bffrm_antispam_notice_dismissed');
