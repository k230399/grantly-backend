<?php

// SAFE TO REMOVE: Laravel scaffold's stub test. Kept here so deletion is a manual,
// reviewable choice. The real Feature tests live alongside this file.

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
