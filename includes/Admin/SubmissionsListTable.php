<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\SubmissionRepository;

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table for the submissions list screen.
 */
class SubmissionsListTable extends \WP_List_Table
{
    private SubmissionRepository $repo;
    private int $form_id;

    public function __construct(SubmissionRepository $repo, int $form_id = 0)
    {
        parent::__construct([
            'singular' => 'submission',
            'plural'   => 'submissions',
            'ajax'     => false,
        ]);
        $this->repo    = $repo;
        $this->form_id = $form_id;
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return [
            'id'           => __('ID', 'bluefairy-forms-lite'),
            'form_title'   => __('Form', 'bluefairy-forms-lite'),
            'submitted_at' => __('Submitted At', 'bluefairy-forms-lite'),
            'email'        => __('Email', 'bluefairy-forms-lite'),
            'status'       => __('Status', 'bluefairy-forms-lite'),
            'view'         => __('View', 'bluefairy-forms-lite'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function get_sortable_columns(): array
    {
        return [
            'submitted_at' => ['submitted_at', true],
            'id'           => ['id', false],
        ];
    }

    public function prepare_items(): void
    {
        $per_page = 50;
        $page     = $this->get_pagenum();
        $offset   = ($page - 1) * $per_page;

        $this->items = $this->repo->recent($per_page, $offset, $this->form_id);
        $total       = $this->repo->count($this->form_id);

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
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
    public function column_email(mixed $item): string
    {
        $email = (string) ($item['email'] ?? '');
        return $email !== '' ? esc_html($email) : '<em>' . esc_html__('(no email)', 'bluefairy-forms-lite') . '</em>';
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_status(mixed $item): string
    {
        $status = (string) ($item['status'] ?? 'new');
        $class  = 'bffrm-status-' . sanitize_html_class($status);
        return '<span class="' . esc_attr($class) . '">' . esc_html($status) . '</span>';
    }

    /**
     * @param array<string, mixed> $item
     */
    public function column_view(mixed $item): string
    {
        $url = admin_url('tools.php?page=bffrm-submissions&submission_id=' . (int) $item['id']);
        return '<a href="' . esc_url($url) . '">' . esc_html__('View', 'bluefairy-forms-lite') . '</a>';
    }
}
