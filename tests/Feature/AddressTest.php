<?php
namespace Tests\Feature;
use App\Models\{Admin, Borrower};
use App\Services\PhilippineAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
class AddressTest extends TestCase
{
    use RefreshDatabase;
    private function signIn(): void
    {
        $this->actingAs(Admin::create(['username'=>'address_admin','password_hash'=>Hash::make('Test-password-123!')]));
    }
    private function address(): array
    {
        return ['province_code'=>'0102800000','city_code'=>'0102801000','barangay_code'=>'0102801001'];
    }
    public function test_local_lists_require_authentication_and_validate_parents(): void
    {
        $this->getJson('/api/addresses/cities?province_code=0102800000')->assertUnauthorized();
        $this->signIn();
        $this->getJson('/api/addresses/cities?province_code=0102800000')->assertOk()->assertJsonFragment(['code'=>'0102801000','name'=>'Adams']);
        $this->getJson('/api/addresses/barangays?province_code=0102800000&city_code=0102801000')->assertOk()->assertJsonFragment(['code'=>'0102801001','name'=>'Adams']);
        $this->getJson('/api/addresses/cities?province_code=NCR')->assertOk()->assertJsonFragment(['code'=>'1380600000','name'=>'City of Manila']);
        $this->getJson('/api/addresses/cities?province_code=unknown')->assertNotFound();
        $this->getJson('/api/addresses/barangays?province_code=NCR&city_code=0102801000')->assertNotFound();
        $this->getJson('/api/addresses/cities?province_code[]=bad')->assertUnprocessable();
    }
    public function test_borrower_saves_codes_and_server_generated_location_and_restores_edit(): void
    {
        $this->signIn();
        $data = ['submission_key'=>(string) Str::uuid(),'name'=>'Address Person'] + $this->address();
        $this->post('/borrowers', $data)->assertRedirect();
        $person = Borrower::firstOrFail();
        $this->assertSame('Adams, Adams, Ilocos Norte', $person->location);
        foreach ($this->address() as $field=>$value) $this->assertSame($value, $person->$field);
        $this->get('/borrowers/'.$person->id.'/edit')->assertOk()->assertSee('value="0102800000" selected', false)->assertSee('value="0102801000" selected', false)->assertSee('value="0102801001" selected', false);
        $this->put('/borrowers/'.$person->id, ['name'=>'Address Person'])->assertRedirect();
        $this->assertNull($person->fresh()->location);
        $this->assertNull($person->fresh()->barangay_code);
    }
    public function test_incomplete_unknown_and_mismatched_addresses_are_rejected(): void
    {
        $this->signIn();
        foreach ([['province_code'=>'NCR'], ['city_code'=>'1380600000'], ['province_code'=>'unknown'], ['city_code'=>'1380600000'], ['barangay_code'=>'1380601001'], ['province_code'=>['bad']]] as $change) {
            $data = ['submission_key'=>(string) Str::uuid(),'name'=>'Invalid Address'] + $this->address();
            $data = array_replace($data, $change);
            if (count($change) === 1 && $change === ['province_code'=>'NCR']) { unset($data['city_code'],$data['barangay_code']); }
            $this->postJson('/borrowers', $data)->assertUnprocessable();
        }
        $this->postJson('/borrowers', ['submission_key'=>(string) Str::uuid(),'name'=>'Invalid Address','location'=>'Forged address'] + $this->address())->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertDatabaseCount('borrowers', 0);
        $this->assertDatabaseCount('submission_receipts', 0);
    }
    public function test_validation_redirect_retains_address_and_legacy_address_is_preserved(): void
    {
        $this->signIn();
        $this->from('/borrowers/create')->post('/borrowers', ['submission_key'=>(string) Str::uuid(),'name'=>'','contact_number'=>'123'] + $this->address())->assertRedirect('/borrowers/create');
        $this->get('/borrowers/create')->assertOk()->assertSee('value="0102801001" selected', false);
        $person = Borrower::create(['name'=>'Legacy Person','location'=>'Previous address']);
        $this->put('/borrowers/'.$person->id, ['name'=>'Legacy Person'])->assertRedirect();
        $this->assertSame('Previous address', $person->fresh()->location);
    }
    public function test_dataset_has_unique_codes_and_complete_selectable_hierarchy(): void
    {
        $addresses = app(PhilippineAddress::class); $cities = []; $barangays = []; $maximumLength = 0;
        foreach ($addresses->provinces() as $province) {
            $children = $addresses->cities($province['code']);
            $this->assertNotEmpty($children, $province['name']);
            foreach ($children as $city) {
                $cities[] = $city['code'];
                $children = $addresses->barangays($city['code']);
                $this->assertNotEmpty($children, $city['name']);
                foreach ($children as $barangay) {
                    $barangays[] = $barangay['code'];
                    $maximumLength = max($maximumLength, mb_strlen(implode(', ', [$barangay['name'],$city['name'],$province['name']])));
                }
            }
        }
        $this->assertLessThanOrEqual(255, $maximumLength);
        $this->assertCount(count($cities), array_unique($cities));
        $this->assertCount(count($barangays), array_unique($barangays));
        $this->assertCount(42027, $barangays);
        $this->assertCount(1642, $cities);
    }
}
