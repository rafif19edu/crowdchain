<?php

namespace App\Services\Organization;

use App\Enums\DocumentType;
use App\Enums\DocumentVisibility;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationReviewed;
use App\Services\Audit\AuditLogger;
use App\Services\Files\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Organisation verification workflow (W1, FR-UM-06 to FR-UM-12). */
class OrganizationService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Creates the application, or updates a pending/rejected one, and sets it to pending.
     *
     * @param  array<string, mixed>  $data  validated fields
     * @param  array<string, UploadedFile|array<int, UploadedFile>|null>  $files
     */
    public function submit(User $fundraiser, array $data, array $files): Organization
    {
        return DB::transaction(function () use ($fundraiser, $data, $files) {
            $organization = $fundraiser->organization()->first() ?? new Organization;
            $organization->user_id = $fundraiser->id;
            $organization->fill($data);
            $organization->bank_holder_matches = Organization::namesMatch($data['name'], $data['bank_account_holder']);
            $organization->status = OrganizationStatus::Pending;
            $organization->rejection_reason = null;
            $organization->submitted_at = now();
            $organization->save();

            $this->replaceSingle($organization, $fundraiser, DocumentType::RegistrationCertificate, $files['registration_certificate'] ?? null);
            $this->replaceSingle($organization, $fundraiser, DocumentType::BankProof, $files['bank_proof'] ?? null);
            $this->replaceSingle($organization, $fundraiser, DocumentType::PicLetter, $files['pic_letter'] ?? null);

            if (! empty($files['premises_photos'])) {
                $organization->documentsOfType(DocumentType::PremisesPhoto)->delete();
                foreach ($files['premises_photos'] as $photo) {
                    $this->storage->store($photo, $organization, DocumentType::PremisesPhoto, DocumentVisibility::Public, $fundraiser);
                }
            }

            return $organization->fresh(['documents']);
        });
    }

    /** FR-UM-12: a new certificate and expiry date send the organisation back for review. */
    public function renew(User $fundraiser, Organization $organization, string $expiryDate, UploadedFile $certificate): Organization
    {
        return DB::transaction(function () use ($fundraiser, $organization, $expiryDate, $certificate) {
            $organization->expiry_date = $expiryDate;
            $organization->status = OrganizationStatus::Pending;
            $organization->submitted_at = now();
            $organization->expiry_reminder_sent_at = null;
            $organization->save();

            $this->replaceSingle($organization, $fundraiser, DocumentType::RegistrationCertificate, $certificate);

            return $organization->fresh(['documents']);
        });
    }

    public function approve(User $admin, Organization $organization): Organization
    {
        $this->assertPending($organization);

        $organization->forceFill([
            'status' => OrganizationStatus::Verified,
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ])->save();

        $this->audit->record($admin, 'organization.approved', $organization);
        $organization->user->notify(new OrganizationReviewed($organization));

        return $organization;
    }

    public function reject(User $admin, Organization $organization, string $reason): Organization
    {
        $this->assertPending($organization);

        $organization->forceFill([
            'status' => OrganizationStatus::Rejected,
            'rejection_reason' => $reason,
        ])->save();

        $this->audit->record($admin, 'organization.rejected', $organization, $reason);
        $organization->user->notify(new OrganizationReviewed($organization));

        return $organization;
    }

    private function assertPending(Organization $organization): void
    {
        if ($organization->status !== OrganizationStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending applications can be reviewed.']);
        }
    }

    private function replaceSingle(Organization $organization, User $uploader, DocumentType $type, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        // Old versions stay on disk for the audit trail; only the record link is replaced.
        $organization->documentsOfType($type)->delete();
        $this->storage->store($file, $organization, $type, DocumentVisibility::Private, $uploader);
    }
}
