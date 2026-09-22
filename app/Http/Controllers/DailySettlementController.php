<?php

namespace App\Http\Controllers;

use App\Models\DailySettlement;
use App\Services\DailySettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DailySettlementController extends Controller
{
    public function __construct(private readonly DailySettlementService $service) {}

    public function index(Request $request)
    {
        $query = DailySettlement::with(['user', 'approver']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(['settlements' => $query->orderByDesc('settlement_date')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'settlement_date' => ['required', 'date'],
            'cash_collected' => ['required', 'numeric', 'min:0'],
            'pos_collected' => ['required', 'numeric', 'min:0'],
            'cheque_collected' => ['required', 'numeric', 'min:0'],
        ]);

        $settlement = $this->service->createOrUpdateDraft(
            $request->user(),
            Carbon::parse($validated['settlement_date']),
            [
                'cash_collected' => $validated['cash_collected'],
                'pos_collected' => $validated['pos_collected'],
                'cheque_collected' => $validated['cheque_collected'],
            ]
        );

        return response()->json(['settlement' => $settlement]);
    }

    public function submit(DailySettlement $dailySettlement)
    {
        $this->service->submit($dailySettlement);

        return response()->json(['settlement' => $dailySettlement->fresh()]);
    }

    public function approve(Request $request, DailySettlement $dailySettlement)
    {
        $this->service->approve($dailySettlement, $request->user());

        return response()->json(['settlement' => $dailySettlement->fresh()]);
    }
}
