<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use App\Services\Payment\PaymentLinkService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService,
        protected PaymentService $paymentService
    ) {}

    public function showInvoice(string $token)
    {
        $link = PaymentLink::withoutGlobalScopes()
            ->with(['invoice.customer', 'invoice.items.product', 'company', 'payment'])
            ->where('token', $token)
            ->firstOrFail();

        $invoice = $link->invoice;
        $remainingAmount = $invoice ? $this->paymentService->remainingAmount($invoice) : 0;

        return view('portal.invoice', compact('link', 'invoice', 'remainingAmount'));
    }

    public function initiatePayment(string $token, Request $request)
    {
        $link = PaymentLink::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        if ($link->isPaid()) {
            return redirect()->route('portal.invoice.show', ['token' => $token])
                ->with('error', 'این فاکتور قبلاً با موفقیت پرداخت شده است.');
        }

        if ($link->isExpired()) {
            return redirect()->route('portal.invoice.show', ['token' => $token])
                ->with('error', 'مهلت پرداخت این لینک منقضی شده است.');
        }

        $callbackUrl = route('portal.invoice.callback', ['token' => $token]);
        $response = $this->paymentLinkService->initiatePayment($link, $callbackUrl);

        if (! $response->successful) {
            return redirect()->route('portal.invoice.show', ['token' => $token])
                ->with('error', $response->errorMessage ?: 'خطا در اتصال به درگاه پرداخت شاپرک');
        }

        return redirect()->away($response->redirectUrl);
    }

    public function paymentCallback(string $token, Request $request)
    {
        $link = PaymentLink::withoutGlobalScopes()
            ->with(['invoice', 'company'])
            ->where('token', $token)
            ->firstOrFail();

        $result = $this->paymentLinkService->processCallback($link, $request->all());

        return view('portal.callback', compact('link', 'result'));
    }

    public function printInvoice(string $token)
    {
        $link = PaymentLink::withoutGlobalScopes()
            ->with(['invoice.customer', 'invoice.items.product', 'company'])
            ->where('token', $token)
            ->firstOrFail();

        $invoice = $link->invoice;

        return view('portal.print', compact('link', 'invoice'));
    }
}
