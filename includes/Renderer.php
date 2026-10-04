<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Renders the HTML form output.
 * Used by both the Gutenberg block (via render.php) and the shortcode callback.
 */
class Renderer
{
    /**
     * Returns the list of supported field types.
     * Pro uses this to register 'file' via the bffrm_field_types filter.
     *
     * @return string[]
     */
    public static function get_supported_types(): array
    {
        return apply_filters(
            'bffrm_field_types',
            [ 'text', 'email', 'tel', 'url', 'number', 'date', 'textarea', 'select', 'radio', 'checkbox' ]
        );
    }

    /**
     * The config's field definitions: a list of arrays, or none. A config with
     * no "fields" key (or not a list) rendered with two PHP warnings per page
     * view, "Undefined array key" and "foreach() argument must be of type
     * array" (seen on the rig, 2026-10-03). Validator and SubmissionHandler
     * already default it to an empty list.
     *
     * @param array<mixed> $config
     * @return array<int, array<string, mixed>>
     */
    public static function fields(array $config): array
    {
        $fields = $config['fields'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, 'is_array'));
    }

    /**
     * Render a form by its ID, returning the complete HTML string.
     */
    public static function render_form(int $form_id): string
    {
        $repo = new FormRepository();
        $form = $repo->find($form_id);

        if (null === $form) {
            return '';
        }

        $config = json_decode((string) $form['config_json'], true);
        if (! is_array($config)) {
            return '';
        }

        // Allow Pro to replace the form with a scheduling/limit message.
        $pre_html = apply_filters( 'bffrm_before_render_form', '', $form_id, $config );
        if ( '' !== $pre_html ) {
            return $pre_html;
        }

        // WPMAP-152: never page-cache a response carrying a form's tokens,
        // and refresh them on load where a cache serves the page anyway.
        PageCache::bypass();
        if (! \wp_doing_ajax() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            wp_enqueue_script('bffrm-form-tokens', BFFRM_URL . 'assets/js/form-tokens.js', [], BFFRM_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        }

        $settings    = new Settings();
        $spam        = new SpamChecker($settings);
        $form_type   = 'bffrm_' . $form_id;
        $action_url  = esc_url(admin_url('admin-post.php'));
        $referer_url = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Use multipart/form-data when any field is of type 'file'.
        $has_file_field = false;
        foreach ( self::fields( $config ) as $fdef ) {
            if ( isset( $fdef['type'] ) && $fdef['type'] === 'file' ) {
                $has_file_field = true;
                break;
            }
        }
        // Error / success state from query string.
        $error   = isset($_GET['bffrm_error']) ? sanitize_key((string) $_GET['bffrm_error']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $field   = isset($_GET['field']) ? sanitize_key((string) $_GET['field']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $success = ! empty($_GET['bffrm_success']); // phpcs:ignore WordPress.Security.NonceVerification

        // Success message.
        if ($success) {
            $msg = ! empty($config['success_message'])
                ? esc_html($config['success_message'])
                : esc_html__('Thank you! Your message has been sent.', 'bluefairy-forms');
            return '<div class="bffrm-success" role="alert">' . $msg . '</div>';
        }

        ob_start();
        ?>
        <div class="bffrm-wrap" id="bffrm-<?php echo esc_attr((string) $form_id); ?>">
            <?php if ($error): ?>
                <div class="bffrm-error" role="alert">
                    <?php echo esc_html(self::error_message($error, $field)); ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?php echo esc_url( $action_url ); ?>"
                class="bffrm-form"
                novalidate
                data-bffrm-id="<?php echo esc_attr((string) $form_id); ?>"
                data-bffrm-tokens="<?php echo esc_url(TokenEndpoint::url($form_id)); ?>"
                <?php if ( $has_file_field ) : ?>enctype="multipart/form-data"<?php endif; ?>
            >
                <input type="hidden" name="action" value="bffrm_submit">
                <input type="hidden" name="bffrm_form_id" value="<?php echo esc_attr((string) $form_id); ?>">
                <?php wp_nonce_field('bffrm_submit_' . $form_id, 'bffrm_nonce'); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( $referer_url ); ?>">

                <?php foreach (self::fields($config) as $field_def): ?>
                    <?php self::render_field($field_def); ?>
                <?php endforeach; ?>

                <?php if (! empty($config['consent_text'])): ?>
                    <div class="bffrm-field bffrm-consent">
                        <label>
                            <input type="checkbox" name="bffrm_consent" value="1" required>
                            <?php echo wp_kses_post($config['consent_text']); ?>
                        </label>
                    </div>
                <?php endif; ?>

                <?php
                // Spam protection fields (honeypot + timetrap).
                $spam->render_fields($form_type);
                ?>

                <div class="bffrm-field bffrm-submit">
                    <button type="submit" class="bffrm-btn-submit">
                        <?php echo esc_html($config['submit_label'] ?? __('Send', 'bluefairy-forms')); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single form field.
     *
     * @param array<string, mixed> $field
     */
    private static function render_field(array $field): void
    {
        $id          = sanitize_key((string) ($field['id'] ?? ''));
        $type        = sanitize_key((string) ($field['type'] ?? 'text'));
        $label       = esc_html((string) ($field['label'] ?? ''));
        $placeholder = esc_attr((string) ($field['placeholder'] ?? ''));
        $required    = (bool) ($field['required'] ?? false);
        $req_attr    = $required ? ' required aria-required="true"' : '';
        $field_id    = 'bffrm_field_' . $id;

        echo '<div class="bffrm-field bffrm-field-' . esc_attr($type) . '">';
        echo '<label for="' . esc_attr($field_id) . '">' . esc_html( $label );
        if ($required) {
            echo '<span class="bffrm-required" aria-hidden="true"> *</span>';
        }
        echo '</label>';

        if ($type === 'textarea') {
            $minlength = isset($field['minlength']) ? ' minlength="' . (int) $field['minlength'] . '"' : '';
            $maxlength = isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '';
            echo '<textarea'
                . ' id="' . esc_attr($field_id) . '"'
                . ' name="' . esc_attr($id) . '"'
                . ' placeholder="' . esc_attr( $placeholder ) . '"'
                . ( $required ? ' required aria-required="true"' : '' )
                . ( isset($field['minlength']) ? ' minlength="' . (int) $field['minlength'] . '"' : '' )
                . ( isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '' )
                . '></textarea>';
        } elseif ($type === 'select') {
            echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($id) . '"'
                . ( $required ? ' required aria-required="true"' : '' ) . '>';
            echo '<option value="">' . esc_html__('— Select —', 'bluefairy-forms') . '</option>';
            foreach ((array) ($field['options'] ?? []) as $opt) {
                echo '<option value="' . esc_attr((string) $opt) . '">' . esc_html((string) $opt) . '</option>';
            }
            echo '</select>';
        } elseif ($type === 'radio') {
            foreach ((array) ($field['options'] ?? []) as $i => $opt) {
                $radio_id = $field_id . '_' . (int) $i;
                echo '<label class="bffrm-radio-label">';
                echo '<input type="radio" id="' . esc_attr($radio_id) . '" name="' . esc_attr($id) . '" value="' . esc_attr((string) $opt) . '"'
                    . ( $required ? ' required aria-required="true"' : '' ) . '>';
                echo ' ' . esc_html((string) $opt);
                echo '</label>';
            }
        } elseif ($type === 'checkbox') {
            echo '<label class="bffrm-checkbox-label">';
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($id) . '" value="1"'
                . ( $required ? ' required aria-required="true"' : '' ) . '>';
            echo ' ' . esc_html( $label );
            echo '</label>';
        } elseif ($type === 'file') {
            $accept = ! empty($field['accept']) ? ' accept="' . esc_attr((string) $field['accept']) . '"' : '';
            echo '<input type="file" id="' . esc_attr($field_id) . '" name="' . esc_attr($id) . '"'
                . $accept . ( $required ? ' required aria-required="true"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $accept is built with esc_attr() on the line above
        } else {
            // text, email, tel, url, number, date.
            $attrs  = ' id="' . esc_attr($field_id) . '"';
            $attrs .= ' type="' . esc_attr($type) . '"';
            $attrs .= ' name="' . esc_attr($id) . '"';
            $attrs .= ' placeholder="' . esc_attr( $placeholder ) . '"';
            if (isset($field['pattern'])) {
                $attrs .= ' pattern="' . esc_attr((string) $field['pattern']) . '"';
            }
            if (isset($field['minlength'])) {
                $attrs .= ' minlength="' . (int) $field['minlength'] . '"';
            }
            if (isset($field['maxlength'])) {
                $attrs .= ' maxlength="' . (int) $field['maxlength'] . '"';
            }
            if ( $required ) {
                $attrs .= ' required aria-required="true"';
            }

            echo '<input' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is built entirely from esc_attr() and (int) casts above
        }

        echo '</div>';
    }

    /**
     * Human-readable error message for a given error code.
     */
    private static function error_message(string $error, string $field = ''): string
    {
        return match ($error) {
            'nonce_fail'   => __('This form had expired, so your message was not sent. The form is ready again: please send it once more.', 'bluefairy-forms'),
            'expired'      => __('This page was open for too long, so your message was not sent. The form is ready again: please send it once more.', 'bluefairy-forms'),
            'rate_limit'   => __('Too many submissions. Please wait a minute and try again.', 'bluefairy-forms'),
            'spam'         => __('Your submission was flagged as spam. Please try again.', 'bluefairy-forms'),
            'consent'      => __('Please tick the consent box to send this form.', 'bluefairy-forms'),
            'closed'       => __('This form is not accepting submissions at the moment.', 'bluefairy-forms'),
            'validation'   => $field
                ? sprintf(
                    /* translators: %s: field label */
                    __('Please check the "%s" field and try again.', 'bluefairy-forms'),
                    esc_html($field)
                )
                : __('Please check the form fields and try again.', 'bluefairy-forms'),
            default => (string) apply_filters(
                'bffrm_error_message',
                __( 'An error occurred. Please try again.', 'bluefairy-forms' ),
                $error,
                $field
            ),
        };
    }
}
