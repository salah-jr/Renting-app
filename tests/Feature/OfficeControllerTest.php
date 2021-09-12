<?php

namespace Tests\Feature;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @test
     */
    public function getOffices()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');
        $response->assertOk()->dump();
        $response->assertJsonCount(3, 'data'); 
    }
}
