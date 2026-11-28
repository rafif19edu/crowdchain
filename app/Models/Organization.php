<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\InstitutionCategory;
use App\Enums\OrganizationStatus;
use App\Enums\RegistrationAuthority;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A JKM- or MAIN-registered organisation run by one fundraiser (D-13, D-14).
 * IC number and bank account number are encrypted at rest (T7).
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'registration_authority', 'main_state', 'name', 'registration_no', 'registration_date', 'expiry_date',
        'institution_category', 'ros_ssm_no', 'address', 'postcode', 'district', 'state', 'phone', 'email',
        'beneficiaries_count', 'bank_name', 'bank_account_no', 'bank_account_holder',
        'pic_name', 'pic_ic_no', 'pic_position', 'pic_phone', 'pic_email',
    ];

    protected function casts(): array
    {
        return [
            'registration_authority' => RegistrationAuthority::class,
            'institution_category' => InstitutionCategory::class,
            'status' => OrganizationStatus::class,
            'registration_date' => 'date',
            'expiry_date' => 'date',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'bank_holder_matches' => 'boolean',
            'bank_account_no' => 'encrypted',
            'pic_ic_no' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function documentsOfType(DocumentType $type): MorphMany
    {
        return $this->documents()->where('type', $type->value);
    }

    public function isVerified(): bool
    {
        return $this->status === OrganizationStatus::Verified;
    }

    /** FR-UM-07: compare names ignoring case, punctuation and spacing. */
    public static function namesMatch(string $a, string $b): bool
    {
        $normalise = fn (string $s) => preg_replace('/[^a-z0-9]+/', '', strtolower($s));

        return $normalise($a) !== '' && $normalise($a) === $normalise($b);
    }
}
