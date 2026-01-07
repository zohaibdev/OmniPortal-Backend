<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Store;
use Illuminate\Support\Str;
use Exception;

class DomainService
{
    public function __construct(
        private ?ForgeApiService $forgeApi = null
    ) {
        // ForgeApiService is optional - only used in production
        if ($this->forgeApi === null && app()->environment('production')) {
            $this->forgeApi = app(ForgeApiService::class);
        }
    }

    /**
     * Add a custom domain to a store.
     */
    public function addDomain(Store $store, string $domain): Domain
    {
        // Normalize domain
        $domain = $this->normalizeDomain($domain);
        
        // Validate domain format
        if (!$this->isValidDomain($domain)) {
            throw new Exception('Invalid domain format');
        }

        // Check if domain already exists
        if (Domain::where('domain', $domain)->exists()) {
            throw new Exception('This domain is already registered');
        }

        // Generate verification token
        $verificationToken = 'omniportal-verify-' . Str::random(32);

        // Create the domain
        $domainModel = Domain::create([
            'store_id' => $store->id,
            'domain' => $domain,
            'type' => Domain::TYPE_CUSTOM,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => $verificationToken,
            'is_primary' => $store->domains()->count() === 0,
        ]);

        return $domainModel;
    }

    /**
     * Verify domain ownership via DNS TXT record.
     */
    public function verifyDomain(Domain $domain): bool
    {
        $domain->update(['status' => Domain::STATUS_VERIFYING]);

        try {
            // Get TXT records for the verification subdomain
            $txtHost = '_omniportal-verification.' . $domain->domain;
            $txtRecords = @dns_get_record($txtHost, DNS_TXT);

            if (!$txtRecords) {
                // Try without the underscore prefix as a fallback
                $txtRecords = @dns_get_record('omniportal-verification.' . $domain->domain, DNS_TXT);
            }

            if ($txtRecords) {
                foreach ($txtRecords as $record) {
                    if (isset($record['txt']) && $record['txt'] === $domain->verification_token) {
                        // Verification successful!
                        $domain->update([
                            'status' => Domain::STATUS_ACTIVE,
                            'verified_at' => now(),
                            'verification_error' => null,
                        ]);
                        return true;
                    }
                }
            }

            // Check if CNAME is properly configured (optional additional check)
            $cnameValid = $this->verifyCname($domain->domain);

            $domain->update([
                'status' => Domain::STATUS_PENDING,
                'verification_error' => 'TXT record not found or does not match. Please ensure you have added the correct DNS record.',
            ]);

            return false;

        } catch (Exception $e) {
            $domain->update([
                'status' => Domain::STATUS_FAILED,
                'verification_error' => 'Verification failed: ' . $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify CNAME is properly configured.
     */
    public function verifyCname(string $domain): bool
    {
        $cnameRecords = @dns_get_record($domain, DNS_CNAME);
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        
        if ($cnameRecords) {
            foreach ($cnameRecords as $record) {
                if (isset($record['target']) && 
                    (str_contains($record['target'], $baseDomain) || 
                     str_contains($record['target'], 'omniportal.com'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set a domain as primary.
     */
    public function setPrimary(Domain $domain): void
    {
        // Remove primary from all other domains for this store
        Domain::where('store_id', $domain->store_id)
            ->where('id', '!=', $domain->id)
            ->update(['is_primary' => false]);

        // Set this domain as primary
        $domain->update(['is_primary' => true]);

        // Update store's custom_domain field
        if ($domain->status === Domain::STATUS_ACTIVE) {
            $store = $domain->store;
            $store->update(['custom_domain' => $domain->domain]);
            
            // Add custom domain to Forge site
            if ($this->forgeApi && $store->forge_site_id) {
                try {
                    $this->forgeApi->addCustomDomain($store->forge_site_id, $domain->domain);
                    
                    // Install SSL certificate
                    $this->forgeApi->installSslCertificate($store->forge_site_id, $domain->domain);
                } catch (Exception $e) {
                    \Log::warning('Failed to add custom domain to Forge: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Remove a domain.
     */
    public function removeDomain(Domain $domain): void
    {
        $store = $domain->store;
        $wasPrimary = $domain->is_primary;
        
        $domain->delete();

        // If this was the primary domain, set another one as primary
        if ($wasPrimary) {
            $newPrimary = $store->domains()->active()->first();
            if ($newPrimary) {
                $this->setPrimary($newPrimary);
            } else {
                $store->update(['custom_domain' => null]);
            }
        }
    }

    /**
     * Normalize a domain (remove protocol, www, trailing slash).
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');
        return $domain;
    }

    /**
     * Validate domain format.
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        if (strlen($domain) > 253) {
            return false;
        }

        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        
        // Don't allow base domain subdomains to be added as custom domains
        if (str_ends_with($domain, '.' . $baseDomain) || $domain === $baseDomain) {
            return false;
        }
        
        // Legacy: Don't allow omniportal.com subdomains either
        if (str_ends_with($domain, '.omniportal.com') || $domain === 'omniportal.com') {
            return false;
        }

        // Regex for valid domain
        $pattern = '/^(?!-)([A-Za-z0-9-]{1,63}(?<!-)\.)+[A-Za-z]{2,}$/';
        return (bool) preg_match($pattern, $domain);
    }

    /**
     * Get all domains for a store.
     */
    public function getStoreDomains(Store $store): array
    {
        $domains = $store->domains()->orderBy('is_primary', 'desc')->get();
        
        return $domains->map(function ($domain) {
            return [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'type' => $domain->type,
                'status' => $domain->status,
                'is_primary' => $domain->is_primary,
                'verified_at' => $domain->verified_at?->toIso8601String(),
                'ssl_enabled' => $domain->ssl_enabled,
                'verification_error' => $domain->verification_error,
                'dns_instructions' => $domain->isPending() ? [
                    'txt' => $domain->getDnsInstructions(),
                    'cname' => $domain->getCnameInstructions(),
                ] : null,
            ];
        })->toArray();
    }
}
