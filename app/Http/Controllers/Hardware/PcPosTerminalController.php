<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PosTerminal;
use App\Models\PosTransaction;
use App\Models\Subject;
use App\Services\Hardware\Pos\PcPosManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PcPosTerminalController extends Controller
{
    public function __construct(
        protected PcPosManager $posManager
    ) {}

    public function index(Request $request): View
    {
        $companyId = (int) getActiveCompany();

        $terminals = PosTerminal::where('company_id', $companyId)
            ->with('bankAccountSubject')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $transactions = PosTransaction::where('company_id', $companyId)
            ->with(['terminal', 'invoice', 'user'])
            ->latest('id')
            ->paginate(15);

        $bankSubjects = Subject::where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('name', 'like', '%بانک%')
                    ->orWhere('name', 'like', '%پوز%')
                    ->orWhere('name', 'like', '%کارتخوان%')
                    ->orWhere('name', 'like', '%صندوق%');
            })
            ->get();

        if ($bankSubjects->isEmpty()) {
            $bankSubjects = Subject::where('company_id', $companyId)->limit(20)->get();
        }

        return view('hardware.pos-terminals.index', compact('terminals', 'transactions', 'bankSubjects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'psp' => ['required', 'string', 'in:behpardakht,saman_kish,sadad,iran_kish,asan_pardakht,pasargad,simulator'],
            'connection_type' => ['required', 'string', 'in:lan,serial,bridge'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'serial_port' => ['nullable', 'string', 'max:32'],
            'baud_rate' => ['nullable', 'integer'],
            'terminal_id' => ['nullable', 'string', 'max:32'],
            'merchant_id' => ['nullable', 'string', 'max:32'],
            'bank_account_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $companyId = (int) getActiveCompany();

        if (! empty($validated['is_default'])) {
            PosTerminal::where('company_id', $companyId)->update(['is_default' => false]);
        }

        $terminal = PosTerminal::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'psp' => $validated['psp'],
            'connection_type' => $validated['connection_type'],
            'ip_address' => $validated['ip_address'] ?? '192.168.1.150',
            'port' => $validated['port'] ?? 8888,
            'serial_port' => $validated['serial_port'] ?? null,
            'baud_rate' => $validated['baud_rate'] ?? 115200,
            'terminal_id' => $validated['terminal_id'] ?? null,
            'merchant_id' => $validated['merchant_id'] ?? null,
            'bank_account_subject_id' => $validated['bank_account_subject_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
            'last_status' => 'unknown',
        ]);

        return redirect()->route('pc-pos.index')
            ->with('success', __('پایانه کارتخوان با موفقیت افزوده شد.'));
    }

    public function update(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'psp' => ['required', 'string', 'in:behpardakht,saman_kish,sadad,iran_kish,asan_pardakht,pasargad,simulator'],
            'connection_type' => ['required', 'string', 'in:lan,serial,bridge'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'serial_port' => ['nullable', 'string', 'max:32'],
            'baud_rate' => ['nullable', 'integer'],
            'terminal_id' => ['nullable', 'string', 'max:32'],
            'merchant_id' => ['nullable', 'string', 'max:32'],
            'bank_account_subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            PosTerminal::where('company_id', $terminal->company_id)
                ->where('id', '!=', $terminal->id)
                ->update(['is_default' => false]);
        }

        $terminal->update([
            'name' => $validated['name'],
            'psp' => $validated['psp'],
            'connection_type' => $validated['connection_type'],
            'ip_address' => $validated['ip_address'] ?? $terminal->ip_address,
            'port' => $validated['port'] ?? $terminal->port,
            'serial_port' => $validated['serial_port'] ?? null,
            'baud_rate' => $validated['baud_rate'] ?? 115200,
            'terminal_id' => $validated['terminal_id'] ?? null,
            'merchant_id' => $validated['merchant_id'] ?? null,
            'bank_account_subject_id' => $validated['bank_account_subject_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ]);

        return redirect()->route('pc-pos.index')
            ->with('success', __('اطلاعات کارتخوان به‌روزرسانی شد.'));
    }

    public function destroy(PosTerminal $terminal): RedirectResponse
    {
        $terminal->delete();

        return redirect()->route('pc-pos.index')
            ->with('success', __('پایانه کارتخوان با موفقیت حذف گردید.'));
    }

    public function ping(PosTerminal $terminal): JsonResponse
    {
        $isOnline = $this->posManager->ping($terminal);

        return response()->json([
            'success' => true,
            'is_online' => $isOnline,
            'status' => $terminal->fresh()->last_status,
            'message' => $isOnline
                ? __('ارتباط با کارتخوان برقرار است.')
                : __('پاسخی از کارتخوان در آدرس شبکه دریافت نشد.'),
        ]);
    }

    public function payInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'pos_terminal_id' => ['required', 'integer', 'exists:pos_terminals,id'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'simulate_scenario' => ['nullable', 'string', 'in:success,cancel,timeout,declined'],
        ]);

        $terminal = PosTerminal::findOrFail($validated['pos_terminal_id']);
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : null;

        try {
            $transaction = $this->posManager->initiateInvoicePayment(
                user: $request->user(),
                terminal: $terminal,
                invoice: $invoice,
                amount: $amount,
                options: [
                    'simulate_scenario' => $validated['simulate_scenario'] ?? 'success',
                ]
            );

            return response()->json([
                'success' => $transaction->isSuccess(),
                'status' => $transaction->status,
                'message' => $transaction->response_message,
                'rrn' => $transaction->rrn,
                'terminal_trace_no' => $transaction->terminal_trace_no,
                'card_number' => $transaction->card_number_masked,
                'payment_id' => $transaction->payment_id,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
