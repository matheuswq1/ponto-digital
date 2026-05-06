<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_placeholder_skipped_until_home_route_exists(): void
    {
        $this->markTestSkipped('Rota GET / não definida neste projeto.');
    }
}
