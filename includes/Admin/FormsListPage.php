<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\AuditLogger;
use BlueFairy\Forms\FormRepository;

/**
 * Tools > BlueFairy Forms — forms list and inline create/delete.
 */
class FormsListPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bluefairy-forms-lite'));
        }

        $repo  = new FormRepository();
        $table = new FormsListTable($repo);
        $table->prepare_items();

        // Handle delete action.
        $this->handle_delete();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('BlueFairy Forms', 'bluefairy-forms-lite'); ?></h1>
            <a href="<?php echo esc_url(admin_url('tools.php?page=bffrm-forms&bffrm_action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'bluefairy-forms-lite'); ?>
            </a>
            <hr class="wp-header-end">

            <?php $this->render_notices(); ?>

            <?php
            if (isset($_GET['bffrm_action']) && $_GET['bffrm_action'] === 'new') { // phpcs:ignore WordPress.Security.NonceVerification
                $this->render_new_form();
            } elseif (isset($_GET['bffrm_action']) && $_GET['bffrm_action'] === 'edit' && isset($_GET['form_id'])) { // phpcs:ignore WordPress.Security.NonceVerification
                $this->render_edit_form((int) $_GET['form_id'], $repo); // phpcs:ignore WordPress.Security.NonceVerification
            } else {
                $table->display();
            }
            ?>
        </div>
        <?php
    }

    private function render_notices(): void
    {
        if (! empty($_GET['bffrm_msg'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $msg = sanitize_key((string) $_GET['bffrm_msg']); // phpcs:ignore WordPress.Security.NonceVerification
            $messages = [
                'created' => __('Form created.', 'bluefairy-forms-lite'),
                'updated' => __('Form updated.', 'bluefairy-forms-lite'),
                'deleted' => __('Form deleted.', 'bluefairy-forms-lite'),
            ];
            if (isset($messages[$msg])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$msg]) . '</p></div>';
            }
        }
    }

    private function render_new_form(): void
    {
        if (! empty($_POST['bffrm_save_form'])) {
            check_admin_referer('bffrm_save_form');

            $title  = sanitize_text_field( wp_unslash( (string) ( $_POST['bffrm_title'] ?? '' ) ) );
            $slug   = sanitize_key( wp_unslash( (string) ( $_POST['bffrm_slug'] ?? '' ) ) );
            $config = wp_unslash( (string) ( $_POST['bffrm_config'] ?? '' ) );

            if ($title && $slug) {
                // Validate JSON.
                $decoded = json_decode($config, true);
                if (is_array($decoded)) {
                    $repo = new FormRepository();
                    $id   = $repo->insert([
                        'title'       => $title,
                        'slug'        => $slug,
                        'config_json' => $config,
                    ]);
                    ( new AuditLogger() )->log('form.created', ['id' => $id], get_current_user_id(), 'form', $id);
                    wp_safe_redirect(admin_url('tools.php?page=bffrm-forms&bffrm_msg=created'));
                    exit;
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Invalid JSON in config.', 'bluefairy-forms-lite') . '</p></div>';
                }
            }
        }

        $this->render_form_editor(null, null, null);
    }

    /**
     * @param array<string, mixed>|null $form
     */
    private function render_edit_form(int $form_id, FormRepository $repo): void
    {
        $form = $repo->find($form_id);

        if (null === $form) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Form not found.', 'bluefairy-forms-lite') . '</p></div>';
            return;
        }

        if (! empty($_POST['bffrm_save_form'])) {
            check_admin_referer('bffrm_save_form');

            $title  = sanitize_text_field( wp_unslash( (string) ( $_POST['bffrm_title'] ?? '' ) ) );
            $config = wp_unslash( (string) ( $_POST['bffrm_config'] ?? '' ) );
            $decoded = json_decode($config, true);

            if ($title && is_array($decoded)) {
                $repo->update($form_id, ['title' => $title, 'config_json' => $config]);
                ( new AuditLogger() )->log('form.updated', ['id' => $form_id], get_current_user_id(), 'form', $form_id);
                wp_safe_redirect(admin_url('tools.php?page=bffrm-forms&bffrm_msg=updated'));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid JSON in config.', 'bluefairy-forms-lite') . '</p></div>';
            }
        }

        $this->render_form_editor($form['title'], $form['slug'] ?? '', $form['config_json']);
    }

    /**
     * @param string|null $title
     * @param string|null $slug
     * @param string|null $config_json
     */
    private function render_form_editor(?string $title, ?string $slug, ?string $config_json): void
    {
        // Pretty-print JSON for the editor.
        $config_pretty = '';
        if ($config_json) {
            $decoded = json_decode($config_json, true);
            $config_pretty = is_array($decoded)
                ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : $config_json;
        } else {
            $default = [
                'schema_version'  => '1.0',
                'submit_label'    => 'Send',
                'success_message' => 'Thank you! Your message has been sent.',
                'fields'          => [
                    ['id' => 'name', 'type' => 'text', 'label' => 'Your Name', 'required' => true],
                    ['id' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                    ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
                ],
            ];
            $config_pretty = (string) json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Enqueue WP bundled CodeMirror.
        $cm_settings = wp_enqueue_code_editor(['type' => 'application/json']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        ?>
        <h2><?php echo $title ? esc_html__('Edit Form', 'bluefairy-forms-lite') : esc_html__('New Form', 'bluefairy-forms-lite'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('bffrm_save_form'); ?>
            <input type="hidden" name="bffrm_save_form" value="1">

            <table class="form-table">
                <tr>
                    <th><label for="bffrm_title"><?php esc_html_e('Title', 'bluefairy-forms-lite'); ?></label></th>
                    <td><input type="text" id="bffrm_title" name="bffrm_title" value="<?php echo esc_attr((string) ($title ?? '')); ?>" class="regular-text" required></td>
                </tr>
                <?php if (! $title): // slug only on create ?>
                <tr>
                    <th><label for="bffrm_slug"><?php esc_html_e('Slug', 'bluefairy-forms-lite'); ?></label></th>
                    <td><input type="text" id="bffrm_slug" name="bffrm_slug" value="<?php echo esc_attr((string) ($slug ?? '')); ?>" class="regular-text" required pattern="[a-z0-9\-]+"></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><label for="bffrm_config"><?php esc_html_e('Form Config (JSON)', 'bluefairy-forms-lite'); ?></label></th>
                    <td>
                        <textarea id="bffrm_config" name="bffrm_config" rows="30" class="large-text code"><?php echo esc_textarea($config_pretty); ?></textarea>
                        <p class="description"><?php esc_html_e('Edit the form configuration as JSON. Fields: id, type, label, required, placeholder, pattern, minlength, maxlength, options.', 'bluefairy-forms-lite'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button($title ? __('Update Form', 'bluefairy-forms-lite') : __('Create Form', 'bluefairy-forms-lite')); ?>
        </form>

        <?php if (! empty($cm_settings)): ?>
        <script>
        jQuery(function ($) {
            var editor = wp.codeEditor.initialize(document.getElementById('bffrm_config'), <?php echo wp_json_encode($cm_settings); ?>);
        });
        </script>
        <?php endif; ?>
        <?php
    }

    private function handle_delete(): void
    {
        if (
            ! isset($_GET['bffrm_action'], $_GET['form_id'], $_GET['_wpnonce']) // phpcs:ignore WordPress.Security.NonceVerification
            || (string) $_GET['bffrm_action'] !== 'delete' // phpcs:ignore WordPress.Security.NonceVerification
        ) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        if (! wp_verify_nonce((string) $_GET['_wpnonce'], 'bffrm_delete_form')) {
            wp_die(esc_html__('Security check failed.', 'bluefairy-forms-lite'));
        }

        $form_id = (int) $_GET['form_id'];
        $repo    = new FormRepository();
        $repo->delete($form_id);

        ( new AuditLogger() )->log('form.deleted', ['id' => $form_id], get_current_user_id(), 'form', $form_id);

        wp_safe_redirect(admin_url('tools.php?page=bffrm-forms&bffrm_msg=deleted'));
        exit;
    }
}
