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
        'debug_log'               => false,
        'remove_data_on_uninstall' => false,
        'honeypot_enabled'        => true,
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
        update_option('bffrm_settings', $data);
        $this->data = array_merge(self::DEFAULTS, $data);
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
