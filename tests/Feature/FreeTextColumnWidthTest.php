<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression for 2026-10-11: free-text columns were VARCHAR(255) with no cap
 * on the way in (notably `status_msg`, which takes SES's raw bounce
 * diagnostic), so a long value was a 500 on MySQL. SQLite does not enforce
 * VARCHAR length, hence asserting the column type directly.
 */
class FreeTextColumnWidthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function freeTextColumns(): array
    {
        return [
            'voter list title' => ['voterlists', 'title'],
            'verification subject' => ['verifications', 'subject'],
            'verification redirect url' => ['verifications', 'redirect_url'],
            'sent message status' => ['sent_messages', 'status_msg'],
            'block list status' => ['global_email_block_lists', 'status_msg'],
        ];
    }

    #[DataProvider('freeTextColumns')]
    public function test_free_text_column_is_not_length_capped(string $table, string $column): void
    {
        $this->assertSame('text', Schema::getColumnType($table, $column));
    }
}
