<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Store;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class DomainController extends Controller
{
    public function __construct(
        private DomainService $domainService
    ) {}

    /**
     * List all domains for a store.
     */
    public function index(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $domains = $this->domainService->getStoreDomains($store);

        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        
        // Also include the subdomain
        $subdomain = [
            'id' => 'subdomain',
            'domain' => $store->slug . '.' . $baseDomain,
            'type' => 'subdomain',
            'status' => 'active',
            'is_primary' => $store->domains()->count() === 0,
            'verified_at' => $store->created_at->toIso8601String(),
            'ssl_enabled' => true,
            'verification_error' => null,
            'dns_instructions' => null,
        ];

        return response()->json([
            'domains' => array_merge([$subdomain], $domains),
            'subdomain' => $store->slug . '.' . $baseDomain,
        ]);
    }

    /**
     * Add a new custom domain.
     */
    public function store(Request $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $request->validate([
            'domain' => 'required|string|max:253',
        ]);

        try {
            $domain = $this->domainService->addDomain($store, $request->domain);

            return response()->json([
                'message' => 'Domain added successfully. Please configure your DNS settings.',
                'domain' => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'type' => $domain->type,
                    'status' => $domain->status,
                    'is_primary' => $domain->is_primary,
                    'verification_token' => $domain->verification_token,
                    'dns_instructions' => [
                        'txt' => $domain->getDnsInstructions(),
                        'cname' => $domain->getCnameInstructions(),
                    ],
                ],
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify domain ownership.
     */
    public function verify(Store $store, Domain $domain): JsonResponse
    {
        $this->authorize('update', $store);

        if ($domain->store_id !== $store->id) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        $verified = $this->domainService->verifyDomain($domain);

        $domain->refresh();

        return response()->json([
            'verified' => $verified,
            'status' => $domain->status,
            'message' => $verified 
                ? 'Domain verified successfully!' 
                : ($domain->verification_error ?? 'Verification failed. Please check your DNS settings.'),
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'status' => $domain->status,
                'verified_at' => $domain->verified_at?->toIso8601String(),
                'verification_error' => $domain->verification_error,
            ],
        ]);
    }

    /**
     * Set a domain as primary.
     */
    public function setPrimary(Store $store, Domain $domain): JsonResponse
    {
        $this->authorize('update', $store);

        if ($domain->store_id !== $store->id) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        if ($domain->status !== Domain::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Only verified domains can be set as primary',
            ], 422);
        }

        $this->domainService->setPrimary($domain);

        return response()->json([
            'message' => 'Domain set as primary successfully',
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'is_primary' => true,
            ],
        ]);
    }

    /**
     * Remove a custom domain.
     */
    public function destroy(Store $store, Domain $domain): JsonResponse
    {
        $this->authorize('update', $store);

        if ($domain->store_id !== $store->id) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        if ($domain->type === Domain::TYPE_SUBDOMAIN) {
            return response()->json([
                'message' => 'Cannot remove the default subdomain',
            ], 422);
        }

        $this->domainService->removeDomain($domain);

        return response()->json([
            'message' => 'Domain removed successfully',
        ]);
    }

    /**
     * Get DNS configuration instructions.
     */
    public function dnsInstructions(Store $store, Domain $domain): JsonResponse
    {
        $this->authorize('view', $store);

        if ($domain->store_id !== $store->id) {
            return response()->json(['message' => 'Domain not found'], 404);
        }

        return response()->json([
            'domain' => $domain->domain,
            'instructions' => [
                'step1' => [
                    'title' => 'Add TXT Record for Verification',
                    'description' => 'Add this TXT record to verify domain ownership',
                    'record' => $domain->getDnsInstructions(),
                ],
                'step2' => [
                    'title' => 'Add CNAME Record',
                    'description' => 'Point your domain to OmniPortal servers',
                    'record' => $domain->getCnameInstructions(),
                ],
                'step3' => [
                    'title' => 'Wait for DNS Propagation',
                    'description' => 'DNS changes can take up to 48 hours to propagate. Usually it takes 15-30 minutes.',
                ],
                'step4' => [
                    'title' => 'Verify Domain',
                    'description' => 'Click the "Verify Domain" button to confirm your DNS settings are correct.',
                ],
            ],
        ]);
    }
}
