<?php

namespace App\Console\Commands;

use App\Models\Cheque;
use App\Models\Company;
use App\Services\Sms\SmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendChequeDueRemindersCommand extends Command
{
    protected $signature = 'dakhl:send-cheque-reminders {--days=3 : Days threshold ahead of due date}';
    protected $description = 'Send automatic SMS reminders for upcoming cheque due dates';

    public function handle(SmsService $smsService): int
    {
        $days = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($days)->toDateString();
        $today = Carbon::today()->toDateString();

        $this->info("Scanning cheques due between {$today} and {$targetDate}...");

        $companies = Company::all();
        $totalSent = 0;

        foreach ($companies as $company) {
            $cheques = Cheque::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereBetween('due_date', [$today, $targetDate])
                ->whereHas('customer')
                ->with('customer')
                ->get();

            foreach ($cheques as $cheque) {
                $recipientPhone = $cheque->customer?->mobile ?: $cheque->customer?->phone;
                if (!$recipientPhone) {
                    continue;
                }

                $tokens = [
                    'customer_name' => $cheque->customer->name,
                    'cheque_number' => $cheque->cheque_number ?? $cheque->serial ?? '',
                    'sayad_number' => $cheque->sayad_number ?? '',
                    'amount' => (string) $cheque->amount,
                    'due_date' => $cheque->due_date?->format('Y/m/d') ?? '',
                ];

                $log = $smsService->sendEventSms('cheque_due', $recipientPhone, $tokens, $company->id);
                if ($log) {
                    $totalSent++;
                }
            }
        }

        $this->info("Successfully enqueued {$totalSent} cheque reminder SMS messages.");
        return Command::SUCCESS;
    }
}
