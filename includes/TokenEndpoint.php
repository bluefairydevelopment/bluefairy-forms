<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * GET /wp-json/bffrm/v1/tokens/{form_id}: a fresh nonce and time token for a
 * form (WPMAP-152). The form's script calls it on load, so a page served from
 * any cache (one that ignores DONOTCACHEPAGE, a CDN) still submits with
 * tokens from now.
 *
 * For visitors (user 0): a logged-in user's nonce cannot be made here, as a
 * REST request without its own nonce runs as a visitor; logged-in pages are
 * not page-cached anyway, and the script leaves them alone.
 *
 * Uncached (no-store) and rate-limited per address: 30 a minute.
 */
final class TokenEndpoint
{
    public const NAMESPACE = 'bffrm/v1';
    public const LIMIT     = 30;

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route(self::NAMESPACE, '/tokens/(?P<form_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'tokens'],
            'permission_callback' => '__return_true',
            'args'                => [
                'form_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
            ],
        ]);
    }

    public static function url(int $form_id): string
    {
        return rest_url(self::NAMESPACE . '/tokens/' . $form_id);
    }

    /**
     * @param \WP_REST_Request $request
     */
    public function tokens($request): \WP_REST_Response
    {
        PageCache::bypass();
        $form_id = (int) $request['form_id'];

        if (self::over_limit(ClientIp::current($this->settings), $this->settings->get_ip_hmac_key())) {
            return self::no_store(new \WP_REST_Response(['code' => 'rate_limited'], 429));
        }

        if (null === (new FormRepository())->find($form_id)) {
            return self::no_store(new \WP_REST_Response(['code' => 'not_found'], 404));
        }

        return self::no_store(new \WP_REST_Response([
            'nonce'  => wp_create_nonce('bffrm_submit_' . $form_id),
            'fields' => (new SpamChecker($this->settings))->fresh_fields('bffrm_' . $form_id),
        ], 200));
    }

    /**
     * Count this address's requests in the current minute; true past LIMIT.
     */
    public static function over_limit(string $ip, string $key): bool
    {
        $hash  = '' !== $key ? hash_hmac('sha256', $ip, $key) : hash('sha256', $ip);
        $name  = 'bffrm_tok_' . substr($hash, 0, 32) . '_' . gmdate('YmdHi');
        $count = (int) get_transient($name) + 1;
        set_transient($name, $count, 120);

        return $count > self::LIMIT;
    }

    private static function no_store(\WP_REST_Response $response): \WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
    }
}
