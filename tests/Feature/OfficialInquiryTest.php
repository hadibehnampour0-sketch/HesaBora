<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OfficialInquiryLog;
use App\Models\User;
use App\Services\Inquiry\Validators\IranianCardValidator;
use App\Services\Inquiry\Validators\IranianNationalCodeValidator;
use App\Services\Inquiry\Validators\IranianPostalCodeValidator;
use App\Services\Inquiry\Validators\IranianShebaValidator;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfficialInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Test Company',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        // Grant permissions
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'inquiries.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
    }

    public function test_iranian_national_code_validator(): void
    {
        // Valid national codes
        $this->assertTrue(IranianNationalCodeValidator::validate('0010359877'));
        $this->assertTrue(IranianNationalCodeValidator::validate('0499370899'));

        // Invalid: repeating digits
        $this->assertFalse(IranianNationalCodeValidator::validate('0000000000'));
        $this->assertFalse(IranianNationalCodeValidator::validate('1111111111'));
        $this->assertFalse(IranianNationalCodeValidator::validate('9999999999'));

        // Invalid: incorrect lengths or non-numeric
        $this->assertFalse(IranianNationalCodeValidator::validate('123456789'));
        $this->assertFalse(IranianNationalCodeValidator::validate('12345678901'));
        $this->assertFalse(IranianNationalCodeValidator::validate('abcdefghij'));

        // Invalid checksum
        $this->assertFalse(IranianNationalCodeValidator::validate('0010359871'));
    }

    public function test_iranian_sheba_validator(): void
    {
        // Bank name resolution from Sheba
        $this->assertSame('بانک ملت', IranianShebaValidator::getBankName('IR810120000000000000000000'));
        $this->assertSame('012', IranianShebaValidator::getBankCode('IR810120000000000000000000'));

        $this->assertSame('بانک ملی ایران', IranianShebaValidator::getBankName('IR810170000000000000000000'));
        $this->assertSame('017', IranianShebaValidator::getBankCode('IR810170000000000000000000'));

        // Invalid format
        $this->assertFalse(IranianShebaValidator::validate('IR123'));
        $this->assertFalse(IranianShebaValidator::validate('INVALID'));
    }

    public function test_iranian_card_validator(): void
    {
        // Valid Luhn card (6037991122334455 is Bank Melli with valid Luhn checksum)
        $card = '6037991122334455';
        $this->assertTrue(IranianCardValidator::validate($card));
        $this->assertSame('بانک ملی ایران', IranianCardValidator::getBankDetails($card)['name']);

        // Invalid Luhn
        $this->assertFalse(IranianCardValidator::validate('6037991122334456'));
        $this->assertFalse(IranianCardValidator::validate('12345'));
    }

    public function test_iranian_postal_code_validator(): void
    {
        $this->assertTrue(IranianPostalCodeValidator::validate('1456789123'));
        $this->assertTrue(IranianPostalCodeValidator::validate('3567891234'));

        // Invalid: starts with 0 or 2
        $this->assertFalse(IranianPostalCodeValidator::validate('0123456789'));
        $this->assertFalse(IranianPostalCodeValidator::validate('2123456789'));

        // Invalid: 5th digit is 0 or 2
        $this->assertFalse(IranianPostalCodeValidator::validate('1456089123'));
        $this->assertFalse(IranianPostalCodeValidator::validate('1456289123'));

        // Invalid length
        $this->assertFalse(IranianPostalCodeValidator::validate('14567'));
    }

    public function test_shahkar_inquiry_endpoint_logs_and_returns_success(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('inquiries.shahkar'), [
            'national_code' => '0010359877',
            'mobile' => '09121234567',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'matched' => true,
                'national_code' => '0010359877',
                'mobile' => '09121234567',
            ],
        ]);

        $this->assertDatabaseHas('official_inquiry_logs', [
            'company_id' => $this->company->id,
            'inquiry_type' => 'shahkar',
            'query_term' => '0010359877',
            'is_matched' => true,
            'status' => 'success',
        ]);
    }

    public function test_card_to_sheba_inquiry_endpoint(): void
    {
        $card = '6037991122334455';

        $response = $this->actingAs($this->user)->postJson(route('inquiries.card'), [
            'card_number' => $card,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'card_number' => $card,
                'bank_name' => 'بانک ملی ایران',
                'status' => 'active',
            ],
        ]);

        $this->assertDatabaseHas('official_inquiry_logs', [
            'company_id' => $this->company->id,
            'inquiry_type' => 'card',
            'query_term' => $card,
            'is_matched' => true,
        ]);
    }

    public function test_postal_code_inquiry_endpoint(): void
    {
        $postalCode = '1456789123';

        $response = $this->actingAs($this->user)->postJson(route('inquiries.postal-code'), [
            'postal_code' => $postalCode,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'postal_code' => $postalCode,
                'province' => 'تهران',
                'city' => 'تهران',
            ],
        ]);

        $this->assertDatabaseHas('official_inquiry_logs', [
            'company_id' => $this->company->id,
            'inquiry_type' => 'postal_code',
            'query_term' => $postalCode,
        ]);
    }

    public function test_inquiry_index_view_and_cross_company_isolation(): void
    {
        // Create inquiry log for company A
        OfficialInquiryLog::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'inquiry_type' => 'shahkar',
            'query_term' => '0010359877',
            'is_matched' => true,
            'status' => 'success',
        ]);

        // Create company B and inquiry log for company B
        $companyB = Company::factory()->create(['fiscal_year' => 1405]);
        OfficialInquiryLog::create([
            'company_id' => $companyB->id,
            'inquiry_type' => 'shahkar',
            'query_term' => '9999999999',
            'is_matched' => false,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->user)->get(route('inquiries.index'));
        $response->assertOk();
        $response->assertSee('0010359877');
        $response->assertDontSee('9999999999');
    }
}
