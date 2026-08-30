<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\FormRepository;

if ( ! defined( 'ABSPATH' ) ) exit;

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for the forms list screen.
 */
class FormsListTable extends \WP_List_Table
{
    private FormRepository $repo;

    public function __construct(FormRepository $repo)
    {
        parent::__construct([
            'singular' => 'form',
            'plural'   => 'forms',
            'ajax'     => false,
        ]);
        $this->repo = $repo;
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return [
            'id'         => __('ID', 'bluefairy-forms'),
            'title'      => __('Title', 'bluefairy-forms'),
            'shortcode'  => __('Shortcode', 'bluefairy-forms'),
            'slug'       => __('Slug', 'bluefairy-forms'),
            'status'     => __('Status', 'bluefairy-forms'),
            'created_at' => __('Created', 'bluefairy-forms'),
            'actions'    => __('Actions', 'bluefairy-forms'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function get_sortable_columns(): array
    {
        return [
            'id'         => ['id', true],
            'title'      => ['title', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', false],
        ];
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->items = $this->repo->all();
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_default(mixed $item, mixed $column_name): string
    {
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_title(mixed $item): string
    {
        $edit_url   = admin_url('tools.php?page=bffrm-forms&bffrm_action=edit&form_id=' . (int) $item['id']);
        $delete_url = wp_nonce_url(
            admin_url('tools.php?page=bffrm-forms&bffrm_action=delete&form_id=' . (int) $item['id']),
            'bffrm_delete_form'
        );

        $title  = '<strong><a href="' . esc_url($edit_url) . '">' . esc_html((string) $item['title']) . '</a></strong>';
        $title .= $this->row_actions([
            'edit'   => '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'bluefairy-forms') . '</a>',
            'delete' => '<a href="' . esc_url($delete_url) . '" class="submitdelete" onclick="return confirm(\'' . esc_js(__('Delete this form?', 'bluefairy-forms')) . '\')">' . esc_html__('Delete', 'bluefairy-forms') . '</a>',
        ]);

        return $title;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_shortcode(mixed $item): string
    {
        return '<code>[bluefairy_form id="' . (int) ($item['id'] ?? 0) . '"]</code>';
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_actions(mixed $item): string
    {
        $sub_url = admin_url('tools.php?page=bffrm-submissions&form_id=' . (int) $item['id']);
        return '<a href="' . esc_url($sub_url) . '">' . esc_html__('View Submissions', 'bluefairy-forms') . '</a>';
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_status(mixed $item): string
    {
        $status = (string) ($item['status'] ?? '');
        $class  = $status === 'active' ? 'bffrm-status-active' : 'bffrm-status-inactive';
        return '<span class="' . esc_attr($class) . '">' . esc_html($status) . '</span>';
    }
}
