<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\OperatingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatingHoursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hours = OperatingHour::orderByRaw("FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return response()->json(['operating_hours' => $hours]);
    }

    public function update(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'hours.*.is_open' => 'required|boolean',
            'hours.*.open_time' => 'nullable|date_format:H:i',
            'hours.*.close_time' => 'nullable|date_format:H:i',
        ]);

        foreach ($request->hours as $hour) {
            OperatingHour::updateOrCreate(
                [
                    'day_of_week' => $hour['day_of_week'],
                ],
                [
                    'is_open' => $hour['is_open'],
                    'open_time' => $hour['open_time'],
                    'close_time' => $hour['close_time'],
                ]
            );
        }

        return response()->json([
            'message' => 'Operating hours updated',
            'operating_hours' => OperatingHour::all(),
        ]);
    }
}
