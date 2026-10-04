<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Manages plugin settings — wraps the single autoloaded `bffrm_settings` option.
 */
class Settings
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'enabled'                 => true,
        'notification_email'      => '',
        'rate_limit_max'          => 5,
        'min_time_seconds'        => 3,
        // IPs / CIDR ranges, one per line, whose X-Forwarded-For / X-Real-IP
        // is believed (ClientIp). Empty: only Cloudflare's own header, and
        // only from Cloudflare's ranges.
        'trusted_proxies'         => '',
        'remove_data_on_uninstall' => false,
        'honeypot_enabled'        => true,
        // Days to keep audit-log rows. 0 disables pruning. The table previously
        // had no retention of any kind: one row per submission plus up to two
        // per webhook delivery attempt, growing without bound for the life of
        // the install, with TRUNCATE the only way to shrink it.
        'audit_retention_days'    => 90,
    ];

    public function __construct()
    {
        $saved      = get_option('bffrm_settings', []);
        $this->data = array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);

        // Default notification email to admin email if not set.
        if (empty($this->data['notification_email'])) {
            $this->data['notification_email'] = get_option('admin_email', '');
        }
    }

    /**
     * Get a setting value by key, with optional fallback default.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? (self::DEFAULTS[$key] ?? $default);
    }

    /**
     * Persist a full settings array.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        // Merged over what is stored: the option is shared with add-ons (Forms
        // Pro keeps its CAPTCHA keys, upload size, retention and encryption
        // switch here), and replacing it wholesale from this page's fields
        // wiped them whenever Pro was not active to put them back.
        $saved  = get_option('bffrm_settings', []);
        $merged = array_merge(is_array($saved) ? $saved : [], $data);
        unset($merged['debug_log']);

        update_option('bffrm_settings', $merged);
        $this->data = array_merge(self::DEFAULTS, $merged);
    }

    /**
     * Hook the late sanitiser (see keep_own_keys()).
     */
    public static function register(): void
    {
        add_filter('sanitize_option_bffrm_settings', [self::class, 'keep_own_keys'], PHP_INT_MAX, 3);
    }

    /**
     * Filter sanitize_option_bffrm_settings, last: this plugin's own keys are
     * taken from the value being saved, sanitised here.
     *
     * Forms Pro up to 1.0.6 registers a sanitiser for this shared option that
     * starts from the STORED option and copies back a fixed list of the keys
     * free Forms had at 1.1.8. A key added since (Trusted Proxies, 1.1.9)
     * was therefore dropped on every save while Pro was active, and those
     * installs cannot update Pro by themselves. Running after it, this puts
     * the plugin's own keys back from what was asked to be saved.
     *
     * @param mixed $value    What earlier sanitisers left.
     * @param mixed $option   The option name.
     * @param mixed $original The value passed to update_option().
     * @return mixed
     */
    public static function keep_own_keys(mixed $value, mixed $option = '', mixed $original = null): mixed
    {
        if (! is_array($value) || ! is_array($original)) {
            return $value;
        }

        foreach (self::sanitize_own($original) as $key => $clean) {
            $value[$key] = $clean;
        }
        unset($value['debug_log']); // Removed in 1.1.9; Pro 1.0.6 copies it back.

        return $value;
    }

    /**
     * This plugin's own keys among $raw, each sanitised to its type and range.
     * Keys that are absent stay absent.
     *
     * @param array<mixed> $raw
     * @return array<string, mixed>
     */
    public static function sanitize_own(array $raw): array
    {
        $out = [];

        foreach (['enabled', 'honeypot_enabled', 'remove_data_on_uninstall'] as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = ! empty($raw[$key]);
            }
        }
        if (array_key_exists('notification_email', $raw)) {
            $out['notification_email'] = is_scalar($raw['notification_email']) ? sanitize_email((string) $raw['notification_email']) : '';
        }
        if (array_key_exists('rate_limit_max', $raw)) {
            $out['rate_limit_max'] = min(60, max(1, (int) (is_scalar($raw['rate_limit_max']) ? $raw['rate_limit_max'] : 5)));
        }
        if (array_key_exists('min_time_seconds', $raw)) {
            $out['min_time_seconds'] = min(30, max(1, (int) (is_scalar($raw['min_time_seconds']) ? $raw['min_time_seconds'] : 3)));
        }
        if (array_key_exists('audit_retention_days', $raw)) {
            $out['audit_retention_days'] = min(3650, max(0, (int) (is_scalar($raw['audit_retention_days']) ? $raw['audit_retention_days'] : 90)));
        }
        if (array_key_exists('trusted_proxies', $raw)) {
            $out['trusted_proxies'] = implode("\n", ClientIp::parse_list(is_scalar($raw['trusted_proxies']) ? (string) $raw['trusted_proxies'] : ''));
        }

        return $out;
    }

    /**
     * Get the HMAC key for IP hashing (non-autoloaded option).
     */
    public function get_ip_hmac_key(): string
    {
        $key = get_option('bffrm_ip_hmac_key', '');
        return is_string($key) ? $key : '';
    }

    /**
     * Return a copy of all defaults.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }
}
