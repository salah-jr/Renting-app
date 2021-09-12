<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TagsControllerTest extends TestCase
{
    /**
     * @test
     */
    public function getListedTags()
    {
        $response = $this->get('/api/tags');
        $this->assertNotNull($response->json('data')[0]['id']);
        $response->assertOk();
    }
}
