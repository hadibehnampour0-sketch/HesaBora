<?php

namespace Tests\Feature;

use App\Enums\StockReservationStatus;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** The stock-reservations screen: previously this index had no route/view at all (JSON-only availability endpoint). */
class StockReservationScreenTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;
        $this->user = User::factory()->create();
        $company->users()->syncWithoutDetaching($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->warehouse = Warehouse::create(['company_id' => $this->companyId, 'name' => 'Main', 'code' => 'MAIN']);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->companyId, 'quantity' => 0, 'average_cost' => 100]);
        WarehouseProductStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 50, 'average_cost' => 100]);

        $this->actingAs($this->user);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_the_index_needs_its_own_permission(): void
    {
        $this->get(route('stock-reservations.index'))->assertForbidden();
    }

    public function test_a_reservation_can_be_made_and_released_from_the_screen(): void
    {
        $this->grant('stock-reservations.index', 'stock-reservations.store', 'stock-reservations.release');

        $this->post(route('stock-reservations.store'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => '20',
        ])->assertSessionHasNoErrors();

        $reservation = StockReservation::firstOrFail();
        $this->get(route('stock-reservations.index'))->assertOk()->assertSee($this->product->name);

        $this->post(route('stock-reservations.release', $reservation))->assertSessionHasNoErrors();
        // The product itself still legitimately appears (e.g. in the "new reservation" picker);
        // what must disappear is the released row, i.e. the list of ACTIVE reservations is empty again.
        $this->get(route('stock-reservations.index'))->assertOk()->assertSee(__('No active reservations.'));
        $this->assertSame(StockReservationStatus::RELEASED, $reservation->fresh()->status);
    }

    public function test_the_warehouse_menu_lists_it_for_a_permitted_user(): void
    {
        $this->grant('home', 'home.summary', 'invoices.index', 'stock-reservations.index');

        $this->get(route('home'))->assertOk()->assertSee(route('stock-reservations.index'), false);
    }
}
