<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Adds where-and-from-what to every audit entry (branch, terminal, IP) and
 * records sign-ins, sign-outs and failed sign-ins. BIR expects the user, time,
 * action and values for each event; the activity log supplies the rest.
 */
class AuditContext
{
    /** Session key holding the terminal a cashier is working on. */
    public const TERMINAL_SESSION_KEY = 'pos.terminal_id';

    public static function register(): void
    {
        LogActivityAction::beforeLogging(function (Activity $activity): void {
            if (! $activity instanceof Model) {
                return;
            }

            $user = Auth::user();

            $activity->setAttribute('branch_id', $activity->getAttribute('branch_id') ?? ($user instanceof User ? $user->branch_id : null));
            $activity->setAttribute('terminal_id', $activity->getAttribute('terminal_id') ?? self::terminalId());
            $activity->setAttribute('ip_address', $activity->getAttribute('ip_address') ?? self::ipAddress());
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                activity('auth')->causedBy($event->user)->event('login')->log('Signed in');
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                activity('auth')->causedBy($event->user)->event('logout')->log('Signed out');
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            activity('auth')
                ->event('login-failed')
                ->withProperty('login', (string) ($event->credentials['login'] ?? ''))
                ->log('Failed sign-in');
        });
    }

    /**
     * The terminal of the current web request, if one was chosen.
     */
    public static function terminalId(): ?int
    {
        if (! request()->hasSession()) {
            return null;
        }

        $id = request()->session()->get(self::TERMINAL_SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The client IP of the current web request; null for console commands and jobs.
     */
    private static function ipAddress(): ?string
    {
        return request()->hasSession() ? request()->ip() : null;
    }
}
