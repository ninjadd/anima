<?php

namespace Anima\Tests;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\ServiceProvider;
use Anima\AnimaServiceProvider;

class PackageTest extends TestCase
{
    #[Test]
    public function it_merges_anima_config(): void
    {
        $this->assertTrue(config('anima.enabled'));
        $this->assertSame('anima', config('anima.path'));
        $this->assertSame(['web'], config('anima.middleware'));
    }

    #[Test]
    public function it_registers_publishable_tags(): void
    {
        $publishes = ServiceProvider::$publishes[AnimaServiceProvider::class] ?? [];
        $publishGroups = ServiceProvider::$publishGroups;

        $this->assertArrayHasKey('anima-config', $publishGroups);
        $this->assertArrayHasKey('anima-assets', $publishGroups);
        $this->assertArrayHasKey('anima-views', $publishGroups);
        $this->assertArrayHasKey('anima-migrations', $publishGroups);

        $this->assertArrayHasKey(
            dirname(__DIR__) . '/config/anima.php',
            $publishGroups['anima-config']
        );

        $migrationKeys = array_keys($publishGroups['anima-migrations']);
        $this->assertCount(1, $migrationKeys);
        $this->assertStringContainsString('create_anima_entries_table.php.stub', $migrationKeys[0]);
    }
}
