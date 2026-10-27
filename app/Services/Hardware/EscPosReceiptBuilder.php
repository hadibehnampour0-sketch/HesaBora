<?php

namespace App\Services\Hardware;

class EscPosReceiptBuilder
{
    public const ESC = "\x1B";

    public const GS = "\x1D";

    public const LF = "\x0A";

    protected string $buffer = '';

    protected int $paperWidthChars = 42; // Standard 80mm printer = 42 or 48 characters per line

    public function __construct(int $paperWidthChars = 42)
    {
        $this->paperWidthChars = $paperWidthChars;
        $this->initialize();
    }

    public function initialize(): self
    {
        $this->buffer .= self::ESC.'@'; // Initialize printer

        return $this;
    }

    public function alignCenter(): self
    {
        $this->buffer .= self::ESC."a\x01";

        return $this;
    }

    public function alignLeft(): self
    {
        $this->buffer .= self::ESC."a\x00";

        return $this;
    }

    public function alignRight(): self
    {
        $this->buffer .= self::ESC."a\x02";

        return $this;
    }

    public function bold(bool $enable = true): self
    {
        $this->buffer .= self::ESC.'E'.($enable ? "\x01" : "\x00");

        return $this;
    }

    public function doubleHeight(bool $enable = true): self
    {
        $this->buffer .= self::GS.'!'.($enable ? "\x01" : "\x00");

        return $this;
    }

    public function sanitizeText(string $text): string
    {
        // Security (D3): Strip control characters (ESC, GS, NUL, etc.) to prevent ESC/POS injection
        return preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $text);
    }

    public function addLine(string $text = ''): self
    {
        $this->buffer .= $this->sanitizeText($text).self::LF;

        return $this;
    }

    public function addDivider(string $char = '-'): self
    {
        $safeChar = $this->sanitizeText($char) ?: '-';
        $this->buffer .= str_repeat(mb_substr($safeChar, 0, 1), $this->paperWidthChars).self::LF;

        return $this;
    }

    public function addTwoColumnRow(string $leftText, string $rightText): self
    {
        $cleanLeft = $this->sanitizeText($leftText);
        $cleanRight = $this->sanitizeText($rightText);
        $totalLen = mb_strlen($cleanLeft) + mb_strlen($cleanRight);
        $spacesNeeded = max(1, $this->paperWidthChars - $totalLen);
        $this->buffer .= $cleanLeft.str_repeat(' ', $spacesNeeded).$cleanRight.self::LF;

        return $this;
    }

    public function addHeader(string $storeName, ?string $subtitle = null, ?string $invoiceNumber = null, ?string $date = null): self
    {
        $this->alignCenter();
        $this->bold(true);
        $this->doubleHeight(true);
        $this->addLine($storeName);
        $this->doubleHeight(false);
        $this->bold(false);

        if ($subtitle) {
            $this->addLine($subtitle);
        }

        $this->addDivider('=');

        $this->alignRight();
        if ($invoiceNumber) {
            $this->addTwoColumnRow('شماره فاکتور: '.$invoiceNumber, 'تاریخ: '.($date ?: now()->toDateString()));
        }

        $this->addDivider('-');

        return $this;
    }

    public function addItemRow(string $title, float $quantity, float $unitPrice, float $totalPrice): self
    {
        $this->alignRight();
        $this->addLine($title);

        $detailsLeft = number_format($totalPrice).' ریال';
        $detailsRight = $quantity.' × '.number_format($unitPrice);

        $this->addTwoColumnRow($detailsLeft, $detailsRight);

        return $this;
    }

    public function addSummary(float $totalAmount, ?float $discount = null, ?float $tax = null, ?float $payable = null): self
    {
        $this->addDivider('-');
        $this->addTwoColumnRow(number_format($totalAmount).' ریال', 'جمع کل اقلام:');

        if ($discount && $discount > 0) {
            $this->addTwoColumnRow(number_format($discount).' ریال', 'تخفیف:');
        }

        if ($tax && $tax > 0) {
            $this->addTwoColumnRow(number_format($tax).' ریال', 'مالیات بر ارزش افزوده:');
        }

        $finalAmount = $payable ?? $totalAmount;
        $this->addDivider('=');
        $this->bold(true);
        $this->addTwoColumnRow(number_format($finalAmount).' ریال', 'مبلغ قابل پرداخت:');
        $this->bold(false);
        $this->addDivider('-');

        return $this;
    }

    public function addFooter(?string $thankYouMessage = 'از خرید شما سپاسگزاریم'): self
    {
        $this->alignCenter();
        if ($thankYouMessage) {
            $this->addLine($thankYouMessage);
        }
        $this->addLine('دخل - سیستم حسابداری و صندوق فروشگاهی');
        $this->addLine('');
        $this->addLine('');

        return $this;
    }

    public function openCashDrawer(): self
    {
        // Standard ESC/POS kick pulse to cash drawer pin 2: ESC p 0 25 250
        $this->buffer .= self::ESC."p\x00\x19\xFA";

        return $this;
    }

    public function cutPaper(bool $partial = false): self
    {
        // Feed 3 lines then cut
        $this->buffer .= self::LF.self::LF.self::LF;
        $this->buffer .= self::GS.'V'.($partial ? "\x01" : "\x00");

        return $this;
    }

    public function getRawBytes(): string
    {
        return $this->buffer;
    }

    public function getBase64(): string
    {
        return base64_encode($this->buffer);
    }
}
