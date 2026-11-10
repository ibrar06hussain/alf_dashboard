<?php

namespace Tests\Feature;

use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictTehsilUnionCouncilCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $districtOfficer;
    private District $gilgit;
    private District $skardu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();

        $this->gilgit = District::firstOrCreate(['code' => 'GLT'], ['name' => 'Gilgit', 'is_active' => true]);
        $this->skardu = District::firstOrCreate(['code' => 'SKD'], ['name' => 'Skardu', 'is_active' => true]);

        $this->districtOfficer = User::factory()->create([
            'email' => 'do.locations@alf.gb.gov.pk',
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->districtOfficer->syncRoles(['District Officer']);
    }

    public function test_admin_can_view_districts_page_with_tehsils_and_union_councils(): void
    {
        $tehsil = Tehsil::create(['district_id' => $this->gilgit->id, 'name' => 'Danyore Tehsil']);
        $uc = UnionCouncil::create(['tehsil_id' => $tehsil->id, 'name' => 'UC Danyore']);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.districts.index'));

        $response->assertOk();
        $response->assertSee('Districts, Tehsils &amp; Union Councils', false);
        $response->assertSee('Danyore Tehsil');
        $response->assertSee('UC Danyore');
    }

    public function test_non_admin_without_permission_cannot_manage_districts_tehsils_or_ucs(): void
    {
        $this->actingAs($this->districtOfficer)
            ->get(route('admin.districts.index'))
            ->assertForbidden();

        $this->actingAs($this->districtOfficer)
            ->post(route('admin.districts.tehsils.store', $this->gilgit), ['name' => 'Unauthorized Tehsil'])
            ->assertForbidden();

        $tehsil = Tehsil::create(['district_id' => $this->gilgit->id, 'name' => 'Gilgit City']);
        $this->actingAs($this->districtOfficer)
            ->post(route('admin.tehsils.union-councils.store', $tehsil), ['name' => 'Unauthorized UC'])
            ->assertForbidden();
    }

    public function test_admin_can_create_edit_and_delete_tehsil_against_district(): void
    {
        // Create via district-scoped route
        $res1 = $this->actingAs($this->superAdmin)->post(route('admin.districts.tehsils.store', $this->gilgit), [
            'name' => 'Bagrote',
        ]);
        $res1->assertRedirect(route('admin.districts.index'));
        $res1->assertSessionHas('expanded_district_id', $this->gilgit->id);

        $this->assertDatabaseHas('tehsils', [
            'district_id' => $this->gilgit->id,
            'name' => 'Bagrote',
        ]);

        // Create via sidebar general route
        $res2 = $this->actingAs($this->superAdmin)->post(route('admin.tehsils.store'), [
            'district_id' => $this->skardu->id,
            'name' => 'Gultari',
        ]);
        $res2->assertRedirect(route('admin.districts.index'));
        $this->assertDatabaseHas('tehsils', [
            'district_id' => $this->skardu->id,
            'name' => 'Gultari',
        ]);

        $tehsil = Tehsil::where('district_id', $this->gilgit->id)->where('name', 'Bagrote')->firstOrFail();

        // Duplicate in same district fails
        $dup = $this->actingAs($this->superAdmin)->post(route('admin.districts.tehsils.store', $this->gilgit), [
            'name' => 'Bagrote',
        ]);
        $dup->assertSessionHasErrors('name');

        // Same name in different district is allowed
        $sameNameOther = $this->actingAs($this->superAdmin)->post(route('admin.districts.tehsils.store', $this->skardu), [
            'name' => 'Bagrote',
        ]);
        $sameNameOther->assertSessionHasNoErrors();

        // Update tehsil
        $upd = $this->actingAs($this->superAdmin)->put(route('admin.tehsils.update', $tehsil), [
            'name' => 'Bagrote Valley Tehsil',
        ]);
        $upd->assertRedirect(route('admin.districts.index'));
        $this->assertDatabaseHas('tehsils', [
            'id' => $tehsil->id,
            'name' => 'Bagrote Valley Tehsil',
        ]);

        // Attach a UC and a CollectionCenter to test safe deletion
        $uc = UnionCouncil::create(['tehsil_id' => $tehsil->id, 'name' => 'UC Sinaker']);
        $center = CollectionCenter::create([
            'name' => 'Bagrote Center',
            'code' => 'CC-BAG-1',
            'level' => 'tehsil',
            'district_id' => $this->gilgit->id,
            'tehsil_id' => $tehsil->id,
            'is_active' => true,
        ]);

        // Delete tehsil
        $del = $this->actingAs($this->superAdmin)->delete(route('admin.tehsils.destroy', $tehsil));
        $del->assertRedirect(route('admin.districts.index'));

        $this->assertDatabaseMissing('tehsils', ['id' => $tehsil->id]);
        $this->assertDatabaseMissing('union_councils', ['id' => $uc->id]);
        $this->assertDatabaseHas('collection_centers', [
            'id' => $center->id,
            'tehsil_id' => null,
        ]);
    }

    public function test_admin_can_create_edit_and_delete_union_council_against_tehsil(): void
    {
        $tehsil = Tehsil::create(['district_id' => $this->gilgit->id, 'name' => 'Juglot']);

        // Create UC via tehsil-scoped route
        $res1 = $this->actingAs($this->superAdmin)->post(route('admin.tehsils.union-councils.store', $tehsil), [
            'name' => 'UC Sai Bala',
        ]);
        $res1->assertRedirect(route('admin.districts.index'));
        $res1->assertSessionHas('expanded_district_id', $this->gilgit->id);
        $res1->assertSessionHas('expanded_tehsil_id', $tehsil->id);

        $this->assertDatabaseHas('union_councils', [
            'tehsil_id' => $tehsil->id,
            'name' => 'UC Sai Bala',
        ]);

        // Create UC via general sidebar route
        $res2 = $this->actingAs($this->superAdmin)->post(route('admin.union-councils.store'), [
            'tehsil_id' => $tehsil->id,
            'name' => 'UC Chakarkot',
        ]);
        $res2->assertRedirect(route('admin.districts.index'));
        $this->assertDatabaseHas('union_councils', [
            'tehsil_id' => $tehsil->id,
            'name' => 'UC Chakarkot',
        ]);

        // Duplicate UC in same tehsil fails
        $dup = $this->actingAs($this->superAdmin)->post(route('admin.tehsils.union-councils.store', $tehsil), [
            'name' => 'UC Sai Bala',
        ]);
        $dup->assertSessionHasErrors('name');

        $uc = UnionCouncil::where('tehsil_id', $tehsil->id)->where('name', 'UC Sai Bala')->firstOrFail();

        // Update UC
        $upd = $this->actingAs($this->superAdmin)->put(route('admin.union-councils.update', $uc), [
            'name' => 'UC Sai Bala Updated',
        ]);
        $upd->assertRedirect(route('admin.districts.index'));
        $this->assertDatabaseHas('union_councils', [
            'id' => $uc->id,
            'name' => 'UC Sai Bala Updated',
        ]);

        // Delete UC
        $del = $this->actingAs($this->superAdmin)->delete(route('admin.union-councils.destroy', $uc));
        $del->assertRedirect(route('admin.districts.index'));
        $this->assertDatabaseMissing('union_councils', ['id' => $uc->id]);
    }
}

