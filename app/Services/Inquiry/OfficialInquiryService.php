<?php

namespace App\Services\Inquiry;

use App\Models\OfficialInquiryLog;
use App\Models\User;
use App\Services\Inquiry\Contracts\InquiryProviderInterface;
use App\Services\Inquiry\Providers\SandboxInquiryProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OfficialInquiryService
{
    protected InquiryProviderInterface $provider;

    public function __construct(?InquiryProviderInterface $provider = null)
    {
        $this->provider = $provider ?? app(InquiryProviderInterface::class, ['provider' => null] ?? []) ?? new SandboxInquiryProvider;
    }

    public function setProvider(InquiryProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProvider(): InquiryProviderInterface
    {
        return $this->provider;
    }

    /**
     * Perform Shahkar inquiry (National Code vs Mobile).
     */
    public function inquireShahkar(string $nationalCode, string $mobile, int $companyId, ?User $user = null): array
    {
        $cacheKey = "inquiry:shahkar:{$nationalCode}:" . preg_replace('/\D/', '', $mobile);

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($nationalCode, $mobile) {
                return $this->provider->verifyShahkar($nationalCode, $mobile);
            });

            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'shahkar',
                'query_term' => $nationalCode,
                'query_payload' => ['national_code' => $nationalCode, 'mobile' => $mobile],
                'is_matched' => (bool) ($result['matched'] ?? false),
                'status' => $result['status'] ?? 'success',
                'response_data' => $result,
            ]);

            return $result;
        } catch (Throwable $e) {
            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'shahkar',
                'query_term' => $nationalCode,
                'query_payload' => ['national_code' => $nationalCode, 'mobile' => $mobile],
                'is_matched' => false,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform Sheba inquiry.
     */
    public function inquireSheba(string $sheba, int $companyId, ?User $user = null): array
    {
        $cleanSheba = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $sheba));
        $cacheKey = "inquiry:sheba:{$cleanSheba}";

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($cleanSheba) {
                return $this->provider->verifySheba($cleanSheba);
            });

            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'sheba',
                'query_term' => $cleanSheba,
                'query_payload' => ['sheba' => $cleanSheba],
                'is_matched' => true,
                'status' => 'success',
                'response_data' => $result,
            ]);

            return $result;
        } catch (Throwable $e) {
            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'sheba',
                'query_term' => $cleanSheba,
                'query_payload' => ['sheba' => $cleanSheba],
                'is_matched' => false,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform Card-to-Sheba and cardholder inquiry.
     */
    public function inquireCard(string $cardNumber, int $companyId, ?User $user = null): array
    {
        $cleanCard = preg_replace('/\D/', '', $cardNumber);
        $cacheKey = "inquiry:card:{$cleanCard}";

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($cleanCard) {
                return $this->provider->convertCardToSheba($cleanCard);
            });

            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'card',
                'query_term' => $cleanCard,
                'query_payload' => ['card_number' => $cleanCard],
                'is_matched' => true,
                'status' => 'success',
                'response_data' => $result,
            ]);

            return $result;
        } catch (Throwable $e) {
            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'card',
                'query_term' => $cleanCard,
                'query_payload' => ['card_number' => $cleanCard],
                'is_matched' => false,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform Postal code inquiry.
     */
    public function inquirePostalCode(string $postalCode, int $companyId, ?User $user = null): array
    {
        $cleanCode = preg_replace('/\D/', '', $postalCode);
        $cacheKey = "inquiry:postal:{$cleanCode}";

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($cleanCode) {
                return $this->provider->verifyPostalCode($cleanCode);
            });

            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'postal_code',
                'query_term' => $cleanCode,
                'query_payload' => ['postal_code' => $cleanCode],
                'is_matched' => true,
                'status' => 'success',
                'response_data' => $result,
            ]);

            return $result;
        } catch (Throwable $e) {
            OfficialInquiryLog::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'inquiry_type' => 'postal_code',
                'query_term' => $cleanCode,
                'query_payload' => ['postal_code' => $cleanCode],
                'is_matched' => false,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
