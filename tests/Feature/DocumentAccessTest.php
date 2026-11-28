<?php

use App\Enums\DocumentType;
use App\Enums\DocumentVisibility;
use App\Models\Organization;
use App\Services\Files\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local', ['serve' => true]);
    $this->owner = fundraiser();
    $this->organization = Organization::factory()->for($this->owner)->create();
    $store = fn (DocumentVisibility $v) => app(DocumentStorage::class)->store(
        UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'), $this->organization, DocumentType::BankProof, $v, $this->owner,
    );
    $this->private = $store(DocumentVisibility::Private);
    $this->public = $store(DocumentVisibility::Public);
});

it('gives anyone a signed link to a public file', function () {
    $this->getJson("/api/v1/documents/{$this->public->ulid}/url")
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'expires_in_minutes', 'sha256']]);
});

it('hides private files from guests and other users', function () {
    $this->getJson("/api/v1/documents/{$this->private->ulid}/url")->assertNotFound();
    $this->actingAs(fundraiser())->getJson("/api/v1/documents/{$this->private->ulid}/url")->assertNotFound();
    $this->actingAs(donor())->getJson("/api/v1/documents/{$this->private->ulid}/url")->assertNotFound();
});

it('lets the owner and the admin open private files', function () {
    $this->actingAs($this->owner)->getJson("/api/v1/documents/{$this->private->ulid}/url")->assertOk();
    $this->actingAs(admin())->getJson("/api/v1/documents/{$this->private->ulid}/url")->assertOk();
});
