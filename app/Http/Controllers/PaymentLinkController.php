<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Payment\PaymentLinkService;
use Illuminate\Http\Request;

class PaymentLinkController extends Controller
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService
    ) {}

    public function store(Invoice $invoice, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'gateway' => 'nullable|string|in:sandbox,zarinpal,idpay',
        ]);

        try {
            $link = $this->paymentLinkService->generateInvoicePaymentLink(
                invoice: $invoice,
                amount: ! empty($validated['amount']) ? (float) $validated['amount'] : null,
                gateway: $validated['gateway'] ?? null,
                user: $request->user()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'url' => $link->public_url,
                    'token' => $link->token,
                    'amount' => (float) $link->amount,
                ]);
            }

            return back()->with('success', 'لینک پرداخت آنلاین با موفقیت تولید شد: ' . $link->public_url);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
