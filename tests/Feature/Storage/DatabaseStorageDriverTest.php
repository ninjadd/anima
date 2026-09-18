<?php

namespace Anima\Tests\Feature\Storage;

use Anima\Drivers\DatabaseStorageDriver;
use Anima\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class DatabaseStorageDriverTest extends TestCase
{
    protected DatabaseStorageDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 3) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();

        $this->driver = new DatabaseStorageDriver(DB::connection(), 'anima_entries');
    }

    #[Test]
    public function it_can_store_and_find_payload_with_strict_types(): void
    {
        $id = $this->driver->store([
            'method' => 'post',
            'uri' => 'https://api.example.com/webhooks/stripe',
            'headers' => ['Content-Type' => 'application/json', 'X-Signature' => 'test_sig'],
            'payload' => ['event' => 'invoice.paid', 'amount' => 5000],
            'response_status' => 200,
            'response_body' => ['received' => true],
            'duration_ms' => 142.55,
            'is_synthetic' => false,
            'tags' => ['stripe', 'finance'],
        ]);

        $this->assertIsString($id);
        $this->assertNotEmpty($id);

        $entry = $this->driver->find($id);

        $this->assertNotNull($entry);
        $this->assertIsArray($entry);
        $this->assertIsString($entry['id']);
        $this->assertSame($id, $entry['id']);
        $this->assertSame('POST', $entry['method']);
        $this->assertSame('https://api.example.com/webhooks/stripe', $entry['uri']);
        $this->assertIsArray($entry['headers']);
        $this->assertSame('application/json', $entry['headers']['Content-Type']);
        $this->assertIsArray($entry['payload']);
        $this->assertSame('invoice.paid', $entry['payload']['event']);
        $this->assertIsInt($entry['response_status']);
        $this->assertSame(200, $entry['response_status']);
        $this->assertIsArray($entry['response_body']);
        $this->assertTrue($entry['response_body']['received']);
        $this->assertIsFloat($entry['duration_ms']);
        $this->assertSame(142.55, $entry['duration_ms']);
        $this->assertIsBool($entry['is_synthetic']);
        $this->assertFalse($entry['is_synthetic']);
        $this->assertIsArray($entry['tags']);
        $this->assertSame(['stripe', 'finance'], $entry['tags']);
        $this->assertIsString($entry['created_at']);
        $this->assertIsString($entry['updated_at']);
    }

    #[Test]
    public function it_returns_null_when_entry_does_not_exist(): void
    {
        $this->assertNull($this->driver->find('non-existent-uuid'));
    }

    #[Test]
    public function it_can_paginate_and_filter_entries(): void
    {
        $this->driver->store([
            'method' => 'POST',
            'uri' => 'https://api.example.com/webhooks/stripe',
            'tags' => ['stripe'],
            'response_status' => 200,
            'is_synthetic' => false,
            'created_at' => now()->subMinutes(10)->toDateTimeString(),
        ]);

        $this->driver->store([
            'method' => 'GET',
            'uri' => 'https://api.example.com/webhooks/github',
            'tags' => ['github'],
            'response_status' => 500,
            'is_synthetic' => true,
            'created_at' => now()->subMinutes(5)->toDateTimeString(),
        ]);

        $this->driver->store([
            'method' => 'POST',
            'uri' => 'https://api.example.com/webhooks/github',
            'tags' => ['github', 'push'],
            'response_status' => 200,
            'is_synthetic' => false,
            'created_at' => now()->subMinutes(1)->toDateTimeString(),
        ]);

        // Default pagination
        $all = $this->driver->paginate(10);
        $this->assertSame(3, $all['total']);
        $this->assertCount(3, $all['data']);
        $this->assertSame(1, $all['current_page']);
        $this->assertSame(1, $all['last_page']);

        // Filter by method
        $postOnly = $this->driver->paginate(10, ['method' => 'POST']);
        $this->assertSame(2, $postOnly['total']);

        // Filter by tag
        $stripeOnly = $this->driver->paginate(10, ['tag' => 'stripe']);
        $this->assertSame(1, $stripeOnly['total']);

        $githubOnly = $this->driver->paginate(10, ['tag' => 'github']);
        $this->assertSame(2, $githubOnly['total']);

        // Filter by is_synthetic
        $syntheticOnly = $this->driver->paginate(10, ['is_synthetic' => true]);
        $this->assertSame(1, $syntheticOnly['total']);

        // Filter by status
        $errorsOnly = $this->driver->paginate(10, ['response_status' => 500]);
        $this->assertSame(1, $errorsOnly['total']);

        // Filter by date range
        $recentOnly = $this->driver->paginate(10, [
            'from' => now()->subMinutes(6)->toDateTimeString(),
        ]);
        $this->assertSame(2, $recentOnly['total']);
    }

    #[Test]
    public function it_can_delete_and_purge_entries(): void
    {
        $id1 = $this->driver->store(['uri' => 'https://api.example.com/1']);
        $id2 = $this->driver->store(['uri' => 'https://api.example.com/2']);

        $this->assertTrue($this->driver->delete($id1));
        $this->assertNull($this->driver->find($id1));
        $this->assertNotNull($this->driver->find($id2));

        $this->assertTrue($this->driver->purge());
        $this->assertNull($this->driver->find($id2));
        $this->assertSame(0, $this->driver->paginate()['total']);
    }

    #[Test]
    public function it_encodes_a_bare_string_tag_as_valid_json_before_storing(): void
    {
        $id = $this->driver->store([
            'uri' => 'https://api.example.com/webhooks/stripe',
            'tags' => 'stripe',
        ]);

        // Read the raw column value directly, bypassing find()/formatRow()'s
        // decode-with-fallback, to prove the driver writes valid JSON (required
        // by PostgreSQL/MySQL's native JSON column type) rather than relying on
        // that fallback to paper over an invalid write. SQLite has no such
        // validation, so this assertion is what actually guards the fix.
        $raw = DB::table('anima_entries')->where('id', $id)->value('tags');

        json_decode($raw);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Stored tags value is not valid JSON.');
        $this->assertSame(['stripe'], json_decode($raw, true));

        $entry = $this->driver->find($id);
        $this->assertSame(['stripe'], $entry['tags']);
    }

    #[Test]
    public function it_passes_through_an_already_json_encoded_tags_string(): void
    {
        $id = $this->driver->store([
            'uri' => 'https://api.example.com/webhooks/stripe',
            'tags' => json_encode(['stripe', 'billing']),
        ]);

        $entry = $this->driver->find($id);
        $this->assertSame(['stripe', 'billing'], $entry['tags']);
    }
}
