<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Requirement extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * Requirement whose answer is completed online instead of uploaded.
     */
    public const DECLARATION_KEY = 'ste_declaration';

    /**
     * field_key values stored on the applicants table rather than in
     * applicant_documents. Anything else (including null and unknown values)
     * uses the generic files.{id} upload path.
     */
    public const LEGACY_FILE_KEYS = ['sf9_file', 'good_moral_file'];

    protected $table = 'requirements';

    protected $fillable = [
        'name',
        'description',
        'notice',
        'field_key',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(\App\Models\Applicant\ApplicantDocument::class);
    }

    /**
     * Programs this requirement is assigned to. An empty list means it is
     * required by every program.
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_requirements')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Requirements meant for a program: those assigned to it, plus those
     * assigned to no program at all (which apply to every program).
     *
     * Reads the pivot directly so the result does not depend on whether a
     * program happens to be soft-deleted at the moment.
     */
    public function scopeApplicableTo($query, ?string $programId)
    {
        return $query->where(function ($query) use ($programId) {
            $query->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')
                    ->from('program_requirements')
                    ->whereColumn('program_requirements.requirement_id', 'requirements.id');
            })->orWhereExists(function ($subquery) use ($programId) {
                $subquery->selectRaw('1')
                    ->from('program_requirements')
                    ->whereColumn('program_requirements.requirement_id', 'requirements.id')
                    ->where('program_requirements.program_id', $programId);
            });
        });
    }

    /**
     * Active requirements applicable to a program, in display order.
     *
     * Shared by the applicant requirements endpoint, the application form's
     * validation/storage, and the documents shown to reviewers so every
     * surface lists the exact same requirements.
     */
    public static function forProgram(?string $programId)
    {
        return static::query()
            ->active()
            ->applicableTo($programId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /**
     * How the applicant fulfills this requirement: an online form or a file.
     */
    public function type(): string
    {
        return $this->field_key === self::DECLARATION_KEY ? 'declaration' : 'file';
    }

    public function isDeclaration(): bool
    {
        return $this->field_key === self::DECLARATION_KEY;
    }

    /**
     * Stored on the applicants table instead of applicant_documents.
     */
    public function usesLegacyColumn(): bool
    {
        return in_array($this->field_key, self::LEGACY_FILE_KEYS, true);
    }

    /**
     * Key the application form posts on the wire (multipart form encoding).
     *
     * Unrecognized field_key values intentionally fall back to the generic
     * files[uuid] path so they are stored instead of silently dropped.
     */
    public function inputKey(): string
    {
        if ($this->isDeclaration()) {
            return self::DECLARATION_KEY;
        }

        if ($this->usesLegacyColumn()) {
            return (string) $this->field_key;
        }

        return 'files['.$this->id.']';
    }

    /**
     * Key the server reads the upload under (dot notation) and reports
     * validation errors with — matches how PHP parses inputKey() on arrival.
     */
    public function errorKey(): string
    {
        if ($this->isDeclaration()) {
            return self::DECLARATION_KEY;
        }

        if ($this->usesLegacyColumn()) {
            return (string) $this->field_key;
        }

        return 'files.'.$this->id;
    }

    /**
     * Applicant-facing shape, shared by every endpoint that lists requirements.
     *
     * @return array<string, mixed>
     */
    public function toDocumentArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'notice' => $this->notice,
            'program_ids' => $this->programs->pluck('id')->values()->all(),
            'field_key' => $this->field_key,
            'type' => $this->type(),
            'input_key' => $this->inputKey(),
            'error_key' => $this->errorKey(),
            'is_required' => $this->is_required,
        ];
    }
}
