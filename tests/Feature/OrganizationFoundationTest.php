<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_company_can_have_many_branches(): void
    {
        $company = Company::factory()->create();
        Branch::factory()->count(2)->create(['company_id' => $company->id]);

        $this->assertCount(2, $company->fresh()->branches);
    }

    #[Test]
    public function a_company_can_have_many_warehouses(): void
    {
        $company = Company::factory()->create();
        Warehouse::factory()->count(2)->create(['company_id' => $company->id]);

        $this->assertCount(2, $company->fresh()->warehouses);
    }

    #[Test]
    public function a_branch_belongs_to_a_company(): void
    {
        $branch = Branch::factory()->create();

        $this->assertInstanceOf(Company::class, $branch->company);
    }

    #[Test]
    public function a_branch_can_have_many_warehouses(): void
    {
        $branch = Branch::factory()->create();
        Warehouse::factory()->count(2)->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
        ]);

        $this->assertCount(2, $branch->fresh()->warehouses);
    }

    #[Test]
    public function a_warehouse_belongs_to_a_branch(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->assertInstanceOf(Branch::class, $warehouse->branch);
    }

    #[Test]
    public function a_warehouse_belongs_to_a_company(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->assertInstanceOf(Company::class, $warehouse->company);
    }

    #[Test]
    public function a_user_can_belong_to_a_company(): void
    {
        $user = User::factory()->forCompany()->create();

        $this->assertInstanceOf(Company::class, $user->company);
    }

    #[Test]
    public function a_user_can_belong_to_a_branch(): void
    {
        $user = User::factory()->forBranch()->create();

        $this->assertInstanceOf(Branch::class, $user->branch);
    }

    #[Test]
    public function a_user_can_belong_to_a_warehouse(): void
    {
        $user = User::factory()->forWarehouse()->create();

        $this->assertInstanceOf(Warehouse::class, $user->warehouse);
    }

    #[Test]
    public function a_user_can_have_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $user->assignRole('manager');

        $this->assertTrue($user->hasRole('manager'));
    }

    #[Test]
    public function a_user_can_have_permissions_through_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        $user->assignRole('admin');

        $this->assertTrue($user->can('users.update'));
    }

    #[Test]
    public function default_roles_and_permissions_are_seeded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
        $this->assertDatabaseHas('permissions', ['name' => 'warehouses.manage']);
    }

    #[Test]
    public function duplicate_branch_codes_are_rejected_within_the_same_company(): void
    {
        $company = Company::factory()->create();
        Branch::factory()->create(['company_id' => $company->id, 'code' => 'MAIN']);

        $this->expectException(QueryException::class);

        Branch::factory()->create(['company_id' => $company->id, 'code' => 'MAIN']);
    }

    #[Test]
    public function the_same_branch_code_may_exist_in_different_companies(): void
    {
        Branch::factory()->create(['code' => 'MAIN']);
        Branch::factory()->create(['code' => 'MAIN']);

        $this->assertSame(2, Branch::query()->where('code', 'MAIN')->count());
    }

    #[Test]
    public function duplicate_warehouse_codes_are_rejected_within_the_same_company(): void
    {
        $company = Company::factory()->create();
        Warehouse::factory()->create(['company_id' => $company->id, 'code' => 'MAIN-WH']);

        $this->expectException(QueryException::class);

        Warehouse::factory()->create(['company_id' => $company->id, 'code' => 'MAIN-WH']);
    }

    #[Test]
    public function invalid_warehouse_company_and_branch_combinations_are_rejected(): void
    {
        $branch = Branch::factory()->create();
        $otherCompany = Company::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        Warehouse::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $branch->id,
        ]);
    }

    #[Test]
    public function demo_seeder_creates_the_expected_organization(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->first();
        $branch = Branch::query()->where('code', 'MAIN')->first();
        $warehouse = Warehouse::query()->where('code', 'MAIN-WH')->first();

        $this->assertNotNull($company);
        $this->assertNotNull($branch);
        $this->assertNotNull($warehouse);
        $this->assertSame($company->id, $branch->company_id);
        $this->assertSame($company->id, $warehouse->company_id);
        $this->assertSame($branch->id, $warehouse->branch_id);
    }

    #[Test]
    public function admin_user_is_created_and_can_authenticate_with_the_seeded_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Auth::attempt([
            'email' => 'admin@islandthrift.local',
            'password' => 'password',
        ]));
    }

    #[Test]
    public function the_admin_user_has_the_super_admin_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'admin@islandthrift.local')->firstOrFail();

        $this->assertTrue($user->hasRole('super-admin'));
    }

    #[Test]
    public function seeded_admin_user_has_a_default_pos_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'admin@islandthrift.local')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $user->company_id)->where('code', 'MAIN-WH')->firstOrFail();

        $this->assertSame($warehouse->id, $user->warehouse_id);
        $this->assertNotNull($user->branch_id);
    }
}
