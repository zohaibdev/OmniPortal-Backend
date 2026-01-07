<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $settings = Setting::all()
            ->pluck('value', 'key')
            ->toArray();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            Setting::updateOrCreate(
                [
                    'key' => $key,
                ],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Settings updated',
            'settings' => Setting::all()
                ->pluck('value', 'key')
                ->toArray(),
        ]);
    }
}
