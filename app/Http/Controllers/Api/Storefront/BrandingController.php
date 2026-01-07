<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\StoreBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function __construct(
        private StoreBrandingService $brandingService
    ) {}

    /**
     * Get store branding for storefront
     */
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found',
            ], 404);
        }

        $branding = $this->brandingService->getBranding($store);

        // Remove sensitive/backend-only fields
        unset($branding['created_at']);
        unset($branding['updated_at']);

        return response()->json([
            'success' => true,
            'data' => [
                'branding' => $branding,
                'css_url' => '/stores/' . $this->brandingService->getStoreFolderName($store) . '/css/custom.css',
            ],
        ]);
    }
}
