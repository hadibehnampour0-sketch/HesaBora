<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConvertEnumColumnsToIntegerMigrationTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::factory()->create()->id;
    }

    public function test_sqlite_converts_subjects_type_enum_to_integer(): void
    {
        $migration = require database_path('migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php');
        $migration->up();

        $this->assertSame('integer', Schema::getColumnType('subjects', 'type'));

        DB::table('subjects')->insert([
            'code' => '010',
            'name' => 'بانکها',
            'parent_id' => null,
            'type' => 3,
            'company_id' => $this->companyId,
        ]);

        $this->assertSame(3, (int) DB::table('subjects')->where('code', '010')->value('type'));
    }

    public function test_sqlite_converts_legacy_string_enum_values_to_integer(): void
    {
        Schema::table('subjects', function ($table) {
            $table->enum('type', ['debtor', 'creditor', 'both'])->default('both')->change();
        });

        DB::table('subjects')->insert([
            'code' => '010',
            'name' => 'بانکها',
            'parent_id' => null,
            'type' => 'both',
            'company_id' => $this->companyId,
        ]);

        $migration = require database_path('migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php');
        $migration->up();

        $type = DB::table('subjects')->where('code', '010')->value('type');
        $this->assertEquals(3, (int) $type);
        $this->assertSame('integer', Schema::getColumnType('subjects', 'type'));
    }

    public function test_sqlite_down_restores_string_enum_values(): void
    {
        $migration = require database_path('migrations/2026_07_08_000001_convert_enum_columns_to_integer_values.php');
        $migration->up();

        DB::table('subjects')->insert([
            'code' => '010',
            'name' => 'بانکها',
            'parent_id' => null,
            'type' => 1,
            'company_id' => $this->companyId,
        ]);

        $migration->down();

        $type = DB::table('subjects')->where('code', '010')->value('type');
        $this->assertSame('debtor', $type);
    }
}
