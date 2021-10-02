<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsAllOfficesInPaginatedWay()
    {
        Office::factory(30)->create();

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'title']]]);
    }

    /**
     * @test
     */
    public function itOnlyListsOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();

        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     */
    public function itFiltersByUserId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $response = $this->get(
            '/api/offices?user_id=' . $user->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /**
     * @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id=' . $user->id
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /**
     * @test
     */
    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonCount(1, 'data.0.tags')
            ->assertJsonCount(1, 'data.0.images')
            ->assertJsonPath('data.0.user.id', $user->id);
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.reservations_count', 1);
    }

    /**
     * @test
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        Office::factory()->create([
            'lat' => '39.74051727562952',
            'lng' => '-8.770375324893696',
            'title' => 'Leiria'
        ]);

        Office::factory()->create([
            'lat' => '39.07753883078113',
            'lng' => '-9.281266331143293',
            'title' => 'Torres Vedras'
        ]);

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Torres Vedras')
            ->assertJsonPath('data.1.title', 'Leiria');

        $response = $this->get('/api/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Leiria')
            ->assertJsonPath('data.1.title', 'Torres Vedras');
    }

    /**
     * @test
     */
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/' . $office->id);

        $response->assertOk()
            ->assertJsonPath('data.reservations_count', 1)
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.user.id', $user->id);
    }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, []);

        $response = $this->postJson('/api/offices');

        $response->assertForbidden();
    }
}
