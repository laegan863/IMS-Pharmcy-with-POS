<?php

namespace Tests\Feature\Audit;

use App\Models\Terminal;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_record_who_where_and_the_old_and_new_values()
    {
        $user = User::factory()->create();
        $terminal = Terminal::factory()->for($user->branch)->create(['name' => 'Counter 1']);

        $this->actingAs($user);
        $terminal->update(['name' => 'Front counter']);

        $entry = Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $terminal)->sole();

        $this->assertSame('audit', $entry->log_name);
        $this->assertTrue($entry->causer?->is($user));
        $this->assertSame($user->branch_id, $entry->getAttribute('branch_id'));
        $this->assertSame('Counter 1', $entry->attribute_changes['old']['name']);
        $this->assertSame('Front counter', $entry->attribute_changes['attributes']['name']);
    }

    public function test_the_active_terminal_is_recorded()
    {
        $user = User::factory()->create();
        $terminal = Terminal::factory()->for($user->branch)->create();

        $this->actingAs($user)
            ->withSession([AuditContext::TERMINAL_SESSION_KEY => $terminal->id])
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $user->email]);

        $entry = Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $user)->sole();

        $this->assertSame($terminal->id, $entry->getAttribute('terminal_id'));
        $this->assertSame('127.0.0.1', $entry->getAttribute('ip_address'));
    }

    public function test_passwords_are_never_written_to_the_log()
    {
        $user = User::factory()->create();
        $user->update(['password' => 'new-secret-password']);

        $this->assertFalse(Activity::query()
            ->whereMorphedTo('subject', $user)
            ->get()
            ->contains(fn (Activity $entry) => str_contains((string) json_encode($entry->attribute_changes), 'password')));
    }

    public function test_sign_ins_and_failed_sign_ins_are_logged()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['login' => $user->username, 'password' => 'wrong']);
        $this->post(route('login.store'), ['login' => $user->username, 'password' => 'password']);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login-failed']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login', 'causer_id' => $user->id]);
    }
}
