<?php

namespace App\Services\Telephony;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\TelephonyCallLog;

class CallerIdService
{
    /**
     * Clean and normalize phone numbers (e.g., +989123456789 -> 09123456789).
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);

        if (str_starts_with($clean, '98') && strlen($clean) === 12) {
            $clean = '0'.substr($clean, 2);
        } elseif (str_starts_with($clean, '0098') && strlen($clean) === 14) {
            $clean = '0'.substr($clean, 4);
        }

        return $clean;
    }

    /**
     * Lookup customer by phone/mobile and build financial & loyalty snapshot.
     */
    public function resolveCustomerProfile(string $rawNumber, int $companyId): array
    {
        $normalized = $this->normalizePhoneNumber($rawNumber);
        $withoutLeadingZero = ltrim($normalized, '0');

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($normalized, $withoutLeadingZero) {
                $query->where('mobile', $normalized)
                    ->orWhere('mobile', $withoutLeadingZero)
                    ->orWhere('phone', $normalized)
                    ->orWhere('phone', $withoutLeadingZero)
                    ->orWhere('phone', 'like', "%{$withoutLeadingZero}%");
            })
            ->first();

        if (! $customer) {
            return [
                'found' => false,
                'caller_number' => $normalized ?: $rawNumber,
                'customer' => null,
                'balance' => 0,
                'loyalty' => [
                    'tier' => 'BRONZE',
                    'points' => 0,
                ],
                'open_invoices_count' => 0,
                'open_invoices_amount' => 0,
            ];
        }

        // Loyalty profile
        $wallet = CustomerWallet::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->first();

        // Open unpaid Sell invoices
        $openInvoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', [InvoiceStatus::APPROVED, InvoiceStatus::PARTIALLY_PAID])
            ->get();

        $openInvoicesAmount = (int) $openInvoices->sum('amount');

        return [
            'found' => true,
            'caller_number' => $normalized,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'phone' => $customer->phone,
                'group' => $customer->group?->name,
                'address' => $customer->address,
            ],
            'balance' => (int) $customer->balance,
            'loyalty' => [
                'tier' => $wallet?->tier ?? 'BRONZE',
                'points' => (int) ($wallet?->points ?? 0),
            ],
            'open_invoices_count' => $openInvoices->count(),
            'open_invoices_amount' => $openInvoicesAmount,
        ];
    }

    /**
     * Process an incoming call event from VoIP or USB hardware.
     */
    public function handleIncomingCall(
        string $callerNumber,
        int $companyId,
        string $channel = 'voip',
        ?string $extension = null
    ): TelephonyCallLog {
        $profile = $this->resolveCustomerProfile($callerNumber, $companyId);

        return TelephonyCallLog::create([
            'company_id' => $companyId,
            'caller_number' => $profile['caller_number'],
            'customer_id' => $profile['customer']['id'] ?? null,
            'channel' => $channel,
            'extension' => $extension,
            'call_status' => 'RINGING',
            'customer_snapshot' => $profile,
        ]);
    }

    /**
     * Update status of call (e.g. ANSWERED, MISSED).
     */
    public function updateCallStatus(TelephonyCallLog $log, string $status): void
    {
        $log->update(['call_status' => $status]);
    }
}
