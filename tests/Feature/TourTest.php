<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_finished_tour_is_remembered_once_and_shared_with_the_pages()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('tours.complete', 'main'))->assertOk();
        $this->actingAs($user)->postJson(route('tours.complete', 'main'))->assertOk();
        $this->actingAs($user)->postJson(route('tours.complete', 'pos'))->assertOk();

        $this->assertSame(['main', 'pos'], $user->fresh()?->completed_tours);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.completedTours', ['main', 'pos']));
    }

    public function test_unknown_tours_are_rejected()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('tours.complete', 'nonsense'))
            ->assertNotFound();
    }

    public function test_guests_cannot_mark_tours()
    {
        $this->postJson(route('tours.complete', 'main'))->assertUnauthorized();
    }
}
