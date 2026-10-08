<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_reports_that_the_application_is_up()
    {
        $response = $this->getJson('/up');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'up');
    }
}
