<?php

namespace App\Services\Sms;

use App\Jobs\SendSmsJob;
use App\Models\SmsConfiguration;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Gateways\FarazSmsGateway;
use App\Services\Sms\Gateways\KavenegarGateway;
use App\Services\Sms\Gateways\LogGateway;
use App\Services\Sms\Gateways\MelipayamakGateway;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Resolve the concrete SMS gateway instance for the given company or configuration.
     */
    public function resolveGateway(?SmsConfiguration $config = null): SmsGatewayInterface
    {
        if (!$config || !$config->is_active) {
            return new LogGateway();
        }

        return match ($config->provider) {
            'kavenegar' => new KavenegarGateway($config->api_key ?? '', $config->sender_number),
            'melipayamak' => new MelipayamakGateway($config->api_key ?? '', $config->sender_number),
            'farazsms' => new FarazSmsGateway($config->api_key ?? '', $config->sender_number),
            default => new LogGateway(),
        };
    }

    /**
     * Dispatch an event-driven SMS asynchronously.
     */
    public function sendEventSms(string $eventType, string $recipient, array $tokens, int $companyId): ?SmsLog
    {
        $cleanPhone = self::normalizePhone($recipient);
        if (!$cleanPhone) {
            Log::warning("[Dakhl SMS] Invalid recipient phone: {$recipient} for event {$eventType}");
            return null;
        }

        $config = SmsConfiguration::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        if (!$config || !$config->is_active) {
            return null;
        }

        // Check if event toggle is enabled
        $isEventEnabled = match ($eventType) {
            'invoice_approved' => $config->auto_invoice_approved,
            'payment_received' => $config->auto_payment_received,
            'cheque_due' => $config->auto_cheque_due_reminder,
            'debt_reminder' => $config->auto_debt_reminder,
            default => true,
        };

        if (!$isEventEnabled) {
            return null;
        }

        $template = SmsTemplate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->first();

        $message = $template
            ? self::renderTemplate($template->template_text, $tokens)
            : self::defaultMessageForEvent($eventType, $tokens);

        $patternCode = $template?->pattern_code;

        return $this->dispatchAsync($cleanPhone, $message, $eventType, $patternCode, $tokens, $companyId);
    }

    /**
     * Enqueue SMS for asynchronous delivery via job after transaction commit.
     */
    public function dispatchAsync(
        string $recipient,
        string $message,
        ?string $eventType = null,
        ?string $patternCode = null,
        array $tokens = [],
        ?int $companyId = null
    ): SmsLog {
        $companyId ??= getActiveCompany();

        $config = SmsConfiguration::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $smsLog = SmsLog::create([
            'company_id' => $companyId,
            'recipient' => $recipient,
            'event_type' => $eventType,
            'message' => $message,
            'provider' => $config?->provider ?? 'log',
            'status' => 'pending',
            'response_payload' => [
                'pattern_code' => $patternCode,
                'tokens' => $tokens,
            ],
        ]);

        SendSmsJob::dispatch($smsLog->id, $patternCode, $tokens)->afterCommit();

        return $smsLog;
    }

    /**
     * Execute direct sending of an SMS log.
     */
    public function executeSend(SmsLog $smsLog, ?string $patternCode = null, array $tokens = []): bool
    {
        $config = SmsConfiguration::withoutGlobalScopes()
            ->where('company_id', $smsLog->company_id)
            ->first();

        $gateway = $this->resolveGateway($config);
        $result = $gateway->send($smsLog->recipient, $smsLog->message, $patternCode, $tokens);

        if ($result->success) {
            $smsLog->update([
                'status' => 'sent',
                'sent_at' => now(),
                'response_payload' => array_merge($smsLog->response_payload ?? [], [
                    'message_id' => $result->messageId,
                    'raw' => $result->rawResponse,
                ]),
                'error_message' => null,
            ]);
            return true;
        }

        $smsLog->update([
            'status' => 'failed',
            'error_message' => $result->error,
            'response_payload' => array_merge($smsLog->response_payload ?? [], [
                'raw' => $result->rawResponse,
            ]),
        ]);

        return false;
    }

    /**
     * Replace template tokens like {name}, {number}, {amount}, {date}.
     */
    public static function renderTemplate(string $templateText, array $tokens): string
    {
        $search = [];
        $replace = [];

        foreach ($tokens as $key => $val) {
            $search[] = "{" . $key . "}";
            $replace[] = (string) $val;
        }

        return str_replace($search, $replace, $templateText);
    }

    /**
     * Clean and format Iranian phone numbers (09xxxxxxxxx).
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Convert Persian & Arabic digits
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $num     = range(0, 9);
        $converted = str_replace($persian, $num, str_replace($arabic, $num, $phone));

        // Strip non-digits
        $digits = preg_replace('/[^\d]/', '', $converted);

        if (str_starts_with($digits, '0098') && strlen($digits) === 14) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        return null;
    }

    /**
     * Fallback standard messages when custom template is not authored.
     */
    protected static function defaultMessageForEvent(string $eventType, array $tokens): string
    {
        return match ($eventType) {
            'invoice_approved' => "مشتری گرامی " . ($tokens['customer_name'] ?? '') . "، فاکتور شماره " . ($tokens['invoice_number'] ?? '') . " به مبلغ " . number_format((float) ($tokens['amount'] ?? 0)) . " ریال صادر گردید.",
            'payment_received' => "رسید دریافت: مبلغ " . number_format((float) ($tokens['amount'] ?? 0)) . " ریال با موفقیت ثبت شد. با تشکر.",
            'cheque_due' => "یادآوری: چک شماره " . ($tokens['cheque_number'] ?? '') . " به سررسید " . ($tokens['due_date'] ?? '') . " به مبلغ " . number_format((float) ($tokens['amount'] ?? 0)) . " ریال.",
            'debt_reminder' => "طرف حساب محترم، مانده حساب بدهی شما مبلغ " . number_format((float) ($tokens['amount'] ?? 0)) . " ریال می‌باشد.",
            default => "پیام ارسالی از نرم‌افزار دخل",
        };
    }
}
