<?php
declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * One site's data, when that site chose Remove Data on Uninstall.
 *
 * Run for every site of a network: the tables are per site (wp_initialize_site
 * creates them for each new site), and until 1.1.9 only the site the
 * plugin was deleted from was cleaned.
 */
function bffrm_uninstall_site(): void
{
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
    // The plugin writes THREE options and this removed two of them, so a
    // "Remove Data on Uninstall" run left bffrm_db_version behind while the
    // docblock above promised it removed all plugin data. Caught 2026-08-30 by
    // T-14-7, the first time that test had ever been allowed to execute.
    delete_option('bffrm_db_version');
    // A TRANSIENT, not an option — AdminMenu sets it with set_transient(), so it
    // lives under _transient_bffrm_antispam_notice_dismissed and the matching
    // _transient_timeout_ row. delete_option() on the bare key matched nothing, so
    // both rows survived a "Remove Data on Uninstall" run that promised otherwise.
    delete_transient('bffrm_antispam_notice_dismissed');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $bffrm_site_id) {
        switch_to_blog((int) $bffrm_site_id);
        bffrm_uninstall_site();
        restore_current_blog();
    }
} else {
    bffrm_uninstall_site();
}
