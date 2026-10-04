<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * A form whose config has no "fields" list rendered with two PHP warnings
 * per view (Renderer.php, seen in the rig's web log on 2026-10-03).
 */
final class RendererFieldsTest extends TestCase
{
    public function test_the_fields_list_is_read_as_given(): void
    {
        $fields = [['id' => 'name', 'type' => 'text'], ['id' => 'email', 'type' => 'email']];
        $this->assertSame($fields, Renderer::fields(['fields' => $fields]));
    }

    public function test_no_fields_key_or_not_a_list_is_no_fields_without_a_warning(): void
    {
        set_error_handler(static function (int $no, string $msg): bool {
            throw new \ErrorException($msg, 0, $no);
        });
        try {
            $this->assertSame([], Renderer::fields([]));
            $this->assertSame([], Renderer::fields(['fields' => 'name']));
            $this->assertSame([['id' => 'a']], Renderer::fields(['fields' => ['junk', ['id' => 'a'], 7]]), 'entries that are not field definitions are skipped');
        } finally {
            restore_error_handler();
        }
    }

    public function test_render_form_reads_fields_only_through_fields(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/Renderer.php');
        $this->assertSame(1, substr_count($source, "\$config['fields']"), 'read once, inside fields()');
        $this->assertSame(2, substr_count($source, 'self::fields('));
    }
}
