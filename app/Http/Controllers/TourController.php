<?php

namespace App\Http\Controllers;

use App\Enums\Tour;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /**
     * Remembers that the user finished or skipped a tour, so it does not
     * start on its own again. It can still be replayed from the user menu.
     */
    public function complete(Request $request, Tour $tour): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $completed = $user->completed_tours ?? [];

        if (! in_array($tour->value, $completed, true)) {
            $user->forceFill(['completed_tours' => [...$completed, $tour->value]])->save();
        }

        return response()->json(['completed_tours' => $user->completed_tours]);
    }
}
