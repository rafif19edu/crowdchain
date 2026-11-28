<?php

use App\Enums\DocumentVisibility;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function organizationPayload(array $overrides = []): array
{
    return array_merge([
        'registration_authority' => 'main',
        'main_state' => 'Perak',
        'name' => 'Maahad Tahfiz Al-Ikhlas',
        'registration_no' => 'MAIP/TFZ/0412',
        'registration_date' => '2019-05-12',
        'expiry_date' => now()->addYear()->toDateString(),
        'institution_category' => 'tahfiz',
        'address' => 'Lot 233, Jalan Kampung Baru, Sitiawan',
        'postcode' => '32000',
        'district' => 'Manjung',
        'state' => 'Perak',
        'phone' => '05-6912233',
        'email' => 'admin@alikhlas.example',
        'beneficiaries_count' => 60,
        'bank_name' => 'Bank Islam',
        'bank_account_no' => '08012010012345',
        'bank_account_holder' => 'MAAHAD TAHFIZ AL-IKHLAS',
        'pic_name' => 'Ustaz Hafiz Rahman',
        'pic_ic_no' => '820304-08-5521',
        'pic_position' => 'Mudir',
        'pic_phone' => '013-4455667',
        'pic_email' => 'hafiz@alikhlas.example',
        'registration_certificate' => UploadedFile::fake()->create('sijil.pdf', 300, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 120, 'application/pdf'),
        'pic_letter' => UploadedFile::fake()->create('lantikan.pdf', 80, 'application/pdf'),
        'premises_photos' => [UploadedFile::fake()->image('premis.jpg')],
    ], $overrides);
}

it('lets a verified fundraiser submit an application', function () {
    $user = fundraiser();

    $response = $this->actingAs($user)->postJson('/api/v1/organization', organizationPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.bank.holder_matches_name', true)
        ->assertJsonCount(4, 'data.documents');

    $organization = Organization::first();
    expect($organization->user_id)->toBe($user->id)
        ->and($organization->documents->firstWhere('type.value', 'premises_photo')->visibility)->toBe(DocumentVisibility::Public)
        ->and($organization->documents->firstWhere('type.value', 'bank_proof')->visibility)->toBe(DocumentVisibility::Private)
        ->and($organization->documents->first()->sha256)->toHaveLength(64);

    Storage::disk('local')->assertExists($organization->documents->first()->path);
    expect($response->json('data.pic.ic_no'))->toBe('820304-08-5521');
});

it('encrypts IC and bank account numbers at rest', function () {
    $this->actingAs(fundraiser())->postJson('/api/v1/organization', organizationPayload())->assertCreated();

    $raw = DB::table('organizations')->first();
    expect($raw->pic_ic_no)->not->toContain('820304')
        ->and($raw->bank_account_no)->not->toContain('08012010012345');
});

it('flags a bank holder name that does not match', function () {
    $this->actingAs(fundraiser())->postJson('/api/v1/organization', organizationPayload(['bank_account_holder' => 'AHMAD BIN ALI']))
        ->assertCreated()
        ->assertJsonPath('data.bank.holder_matches_name', false);
});

it('validates required documents, MAIN state and expiry', function () {
    $this->actingAs(fundraiser())->postJson('/api/v1/organization', organizationPayload([
        'main_state' => null,
        'expiry_date' => now()->subDay()->toDateString(),
        'registration_certificate' => null,
        'premises_photos' => [],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['main_state', 'expiry_date', 'registration_certificate', 'premises_photos']);
});

it('rejects files that are not PDF/JPG/PNG or larger than 5 MB', function () {
    $this->actingAs(fundraiser())->postJson('/api/v1/organization', organizationPayload([
        'registration_certificate' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        'bank_proof' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['registration_certificate', 'bank_proof']);
});

it('refuses a registration number already used', function () {
    Organization::factory()->create(['registration_authority' => 'main', 'main_state' => 'Perak', 'registration_no' => 'MAIP/TFZ/0412']);

    $this->actingAs(fundraiser())->postJson('/api/v1/organization', organizationPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('registration_no');
});

it('lets a rejected application be edited and resubmitted without re-uploading files', function () {
    $user = fundraiser();
    $this->actingAs($user)->postJson('/api/v1/organization', organizationPayload())->assertCreated();
    Organization::first()->forceFill(['status' => OrganizationStatus::Rejected, 'rejection_reason' => 'Wrong number'])->save();

    $this->actingAs($user)->postJson('/api/v1/organization', organizationPayload([
        'registration_no' => 'MAIP/TFZ/0413',
        'registration_certificate' => null,
        'bank_proof' => null,
        'pic_letter' => null,
        'premises_photos' => null,
    ]))->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rejection_reason', null);

    expect(Organization::first()->documents)->toHaveCount(4);
});

it('does not let a verified organisation be edited (renewal only)', function () {
    $user = fundraiser();
    Organization::factory()->verified()->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/organization', organizationPayload())->assertForbidden();
});

it('sends a renewed certificate back for review', function () {
    $user = fundraiser();
    $organization = Organization::factory()->for($user)->create();
    $organization->forceFill(['status' => OrganizationStatus::Expired])->save();

    $this->actingAs($user)->postJson('/api/v1/organization/renewal', [
        'expiry_date' => now()->addYears(2)->toDateString(),
        'registration_certificate' => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'),
    ])->assertOk()->assertJsonPath('data.status', 'pending');
});

it('refuses renewal while still pending', function () {
    $user = fundraiser();
    Organization::factory()->for($user)->create();

    $this->actingAs($user)->postJson('/api/v1/organization/renewal', [
        'expiry_date' => now()->addYears(2)->toDateString(),
        'registration_certificate' => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('only fundraisers with a verified email can submit', function () {
    $this->actingAs(donor())->postJson('/api/v1/organization', organizationPayload())->assertForbidden();
    $this->actingAs(User::factory()->fundraiser()->unverified()->create())
        ->postJson('/api/v1/organization', organizationPayload())->assertForbidden();
});

it('shows a limited public profile for verified organisations only', function () {
    $verified = Organization::factory()->verified()->create();
    $pending = Organization::factory()->create();

    $this->getJson("/api/v1/organizations/{$verified->ulid}")
        ->assertOk()
        ->assertJsonPath('data.name', $verified->name)
        ->assertJsonMissingPath('data.bank')
        ->assertJsonMissingPath('data.pic')
        ->assertJsonMissingPath('data.address');

    $this->getJson("/api/v1/organizations/{$pending->ulid}")->assertNotFound();
});
