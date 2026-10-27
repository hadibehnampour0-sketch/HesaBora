<?php

namespace Tests\Unit;

use App\Support\Moadian\Facades\Moadian;
use App\Support\Moadian\InvoiceHeader;
use App\Support\Moadian\InvoiceItem;
use App\Support\Moadian\MoadianClient;
use App\Support\Moadian\MoadianInvoice;
use App\Support\Moadian\Payment;
use App\Support\Moadian\TaxIdGenerator;
use App\Support\Moadian\Verhoeff;
use Carbon\Carbon;
use Tests\TestCase;

class MoadianCleanRoomTest extends TestCase
{
    public function test_verhoeff_checksum_and_validation(): void
    {
        // Known test numbers and their valid checksums
        // 236 -> checksum 3 (2363 is valid)
        $cs = Verhoeff::checkSum('236');
        $this->assertSame(3, $cs);
        $this->assertTrue(Verhoeff::validate('2363'));

        // 142857 -> checksum 0
        $cs2 = Verhoeff::checkSum('142857');
        $this->assertTrue(Verhoeff::validate('142857'.$cs2));

        // Invalid numbers should fail
        $this->assertFalse(Verhoeff::validate('2364'));
    }

    public function test_tax_id_generator_produces_exact_22_char_compliant_id(): void
    {
        $clientId = 'A12345'; // 6 chars
        $date = Carbon::parse('2024-05-15 12:00:00');
        $serial = 42;

        $taxId = TaxIdGenerator::generate($clientId, $date, $serial);

        $this->assertSame(22, strlen($taxId));
        $this->assertStringStartsWith('A12345', $taxId);

        // Check date part (5 hex chars)
        $daysPastEpoch = (int) floor($date->getTimestamp() / 86400);
        $hexDays = strtoupper(str_pad(dechex($daysPastEpoch), 5, '0', STR_PAD_LEFT));
        $this->assertSame($hexDays, substr($taxId, 6, 5));

        // Check serial part (10 hex chars)
        $hexSerial = strtoupper(str_pad(dechex($serial), 10, '0', STR_PAD_LEFT));
        $this->assertSame($hexSerial, substr($taxId, 11, 10));

        // Final char is checksum
        $checksum = substr($taxId, 21, 1);
        $this->assertTrue(is_numeric($checksum));
    }

    public function test_moadian_invoice_structures_serialize_cleanly(): void
    {
        $header = new InvoiceHeader('B98765');
        $header->setTaxID(Carbon::parse('2025-01-01'), 1001);
        $header->indatim = 1735689600000;
        $header->inty = 1;
        $header->inp = 1;
        $header->ins = 1;
        $header->tins = '12345678901';

        $item = new InvoiceItem;
        $item->sstid = '2720000123456';
        $item->sstt = 'Goods item 1';
        $item->am = 10;
        $item->fee = 1000;
        $item->prdis = 10000;
        $item->dis = 500;
        $item->adis = 9500;
        $item->vra = 10;
        $item->vam = 950;
        $item->tsstam = 10450;

        $payment = new Payment;
        $payment->trn = 'REF-BANK-999';
        $payment->pdt = 1735689700000;

        $invoice = new MoadianInvoice($header);
        $invoice->addItem($item);
        $invoice->addPayment($payment);

        $array = $invoice->toArray();

        $this->assertArrayHasKey('header', $array);
        $this->assertArrayHasKey('body', $array);
        $this->assertArrayHasKey('payments', $array);

        $this->assertSame($header->taxid, $array['header']['taxid']);
        $this->assertSame(1, count($array['body']));
        $this->assertSame('2720000123456', $array['body'][0]['sstid']);
        $this->assertSame(1, count($array['payments']));
        $this->assertSame('REF-BANK-999', $array['payments'][0]['trn']);
    }

    public function test_moadian_facade_resolves_and_creates_client(): void
    {
        $client = Moadian::for('fake-key', 'fake-cert', 'A00000');
        $this->assertInstanceOf(MoadianClient::class, $client);
    }
}
