<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * ProductionHardeningTest (US-H-08, NFR-16):
 *   - .env.example present
 *   - .gitignore excludes .env
 *   - APP_DEBUG defaults to false in .env.example
 */
final class ProductionHardeningTest extends TestCase
{
    public function test_env_example_exists(): void
    {
        $this->assertFileExists(base_path('.env.example'));
    }

    public function test_gitignore_excludes_env(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        $this->assertStringContainsString('.env', $gitignore);
    }

    public function test_env_example_sets_debug_false_in_production(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^APP_DEBUG\s*=\s*false\s*$/m', $env);
    }
}
