<?php

namespace Tests\Feature;

use App\Http\Middleware\CompressSyncPayload;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\Payroll\DbfFileWriter;
use App\Services\Payroll\TaminInsuranceDisketteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;
use ZipArchive;

class TaminInsuranceDisketteAndEnhancementsTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'شرکت آزمایشی نپتون',
            'national_code' => '10103568974',
            'phone_number' => '02188880000',
            'address' => 'تهران، خیابان ولیعصر',
        ]);
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->user->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $site = WorkSite::firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'WS-10100012'],
            ['name' => 'کارگاه مرکزی', 'is_active' => true]
        );
        $shift = WorkShift::firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'شیفت اداری'],
            ['start_time' => '08:00:00', 'end_time' => '16:00:00', 'paid_leave' => 1200]
        );

        $this->employee = Employee::firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'EMP-TAMIN-01'],
            [
                'first_name' => 'امیرحسین',
                'last_name' => 'مرادی',
                'national_code' => '0012345678',
                'insurance_number' => '1234567890',
                'work_site_id' => $site->id,
                'work_shift_id' => $shift->id,
                'is_active' => true,
            ]
        );
    }

    public function test_dbf_file_writer_generates_valid_dbase3_binary_header_and_records(): void
    {
        $writer = new DbfFileWriter;
        $writer->addField('CODE', 'C', 10);
        $writer->addField('TITLE', 'C', 50);
        $writer->addField('AMOUNT', 'N', 12, 0);

        $writer->addRecord([
            'CODE' => '1001',
            'TITLE' => 'حقوق ماهانه',
            'AMOUNT' => 45000000,
        ]);

        $bytes = $writer->build();

        // 1. Verify minimum header length (32 bytes header + 3 * 32 field descriptors + 1 byte terminator)
        $this->assertGreaterThan(129, strlen($bytes));

        // 2. Verify dBASE III version signature byte (0x03)
        $this->assertSame(0x03, ord($bytes[0]));

        // 3. Verify record count in header bytes 4..7 (32-bit uint little endian = 1)
        $recordCount = unpack('V', substr($bytes, 4, 4))[1];
        $this->assertSame(1, $recordCount);

        // 4. Verify EOF terminator (0x1A) at the very end
        $this->assertSame(0x1A, ord($bytes[strlen($bytes) - 1]));
    }

    public function test_tamin_insurance_diskette_service_generates_complete_zip_package(): void
    {
        // Seed a sample payroll
        Payroll::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'year' => 1404,
            'month' => 5,
            'total_earnings' => 120000000, // 120M Rials gross
            'total_deductions' => 8400000, // 7% worker insurance
            'net_payment' => 111600000,
            'employer_insurance' => 27600000, // 23% employer
            'status' => 1,
        ]);

        $service = app(TaminInsuranceDisketteService::class);
        $package = $service->generatePackage($this->company, 1404, 5);

        $this->assertNotEmpty($package['zip_content']);
        $this->assertSame(1, $package['summary']['worker_count']);
        $this->assertGreaterThan(0, $package['summary']['total_insurance_payable_irr']);

        // Inspect ZIP structure
        $tempZip = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tempZip, $package['zip_content']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempZip));
        $this->assertNotFalse($zip->locateName('DSKKAR00.dbf'));
        $this->assertNotFalse($zip->locateName('DSKWOR00.dbf'));
        $this->assertNotFalse($zip->locateName('Summary_Control.txt'));
        $this->assertNotFalse($zip->locateName('Bank_Salary_Transfer.txt'));
        $zip->close();
        @unlink($tempZip);
    }

    public function test_payroll_controller_downloads_insurance_diskette_zip(): void
    {
        $response = $this->get(route('salary.payrolls.insurance-diskette', ['year' => 1404, 'month' => 5]));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Tamin_Insurance_Diskette', $response->headers->get('Content-Disposition'));
    }

    public function test_compress_sync_payload_middleware_decompresses_gzip_request_and_compresses_response(): void
    {
        // Define a test route using compress.sync
        Route::middleware([CompressSyncPayload::class])
            ->post('/test-sync-compression', function (Request $request) {
                return response()->json([
                    'status' => 'success',
                    'echo_key' => $request->input('test_key'),
                    'large_data' => str_repeat('Neptune ERP Sync Payload ', 50),
                ]);
            });

        // 1. Send Gzip compressed request payload
        $rawPayload = json_encode(['test_key' => 'verified_branch_payload_123']);
        $compressedRequest = gzencode($rawPayload, 6);

        $response = $this->call(
            'POST',
            '/test-sync-compression',
            [],
            [],
            [],
            [
                'HTTP_CONTENT_ENCODING' => 'gzip',
                'HTTP_ACCEPT_ENCODING' => 'gzip',
                'CONTENT_TYPE' => 'application/json',
            ],
            $compressedRequest
        );

        $response->assertOk();
        // 2. Verify incoming decompressed payload was read correctly by controller
        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertNotNull($response->headers->get('X-Compression-Saved'));

        // 3. Decompress the response body and verify content
        $decompressedResponse = gzdecode($response->getContent());
        $json = json_decode($decompressedResponse, true);
        $this->assertSame('success', $json['status']);
        $this->assertSame('verified_branch_payload_123', $json['echo_key']);
    }
}
