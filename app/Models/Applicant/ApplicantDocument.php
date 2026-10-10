<?php

namespace App\Models\Applicant;

use App\Models\Admin\Requirement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantDocument extends Model
{
    use HasUuids;

    /**
     * Fallback labels for legacy-column uploads whose requirement row is gone.
     */
    private const LEGACY_LABELS = [
        'sf9_file' => 'SF9 / Report Card',
        'good_moral_file' => 'Certificate of Good Moral Character',
        'ste_declaration' => 'Health Declaration Form',
    ];

    protected $table = 'applicant_documents';

    protected $fillable = [
        'applicant_id',
        'requirement_id',
        'requirement_name',
        'file_path',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * Build the list of requirement documents for an applicant.
     *
     * Mirrors the application form exactly: every active requirement
     * applicable to the applicant's program, in the same order, with the same
     * type and input keys. On top of that, uploads whose requirement was
     * deactivated or deleted afterwards are appended so files never become
     * invisible to reviewers.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildFor(Applicant $applicant, string $routeName): array
    {
        $requirements = Requirement::forProgram($applicant->program_id)->get();

        $uploads = ApplicantDocument::where('applicant_id', $applicant->id)
            ->get()
            ->keyBy('requirement_id');

        $documents = [];
        $coveredFieldKeys = [];

        foreach ($requirements as $requirement) {
            $coveredFieldKeys[] = $requirement->field_key;

            $path = null;
            $completed = false;

            if ($requirement->isDeclaration()) {
                $completed = (bool) $applicant->ste_declaration;
            } elseif ($requirement->usesLegacyColumn()) {
                $path = $requirement->field_key === 'sf9_file'
                    ? $applicant->sf9_file
                    : $applicant->good_moral_file;
            } else {
                $path = $uploads->get($requirement->id)?->file_path;
            }

            $documents[] = [
                'requirement_id' => $requirement->id,
                'name' => $requirement->name,
                'field_key' => $requirement->field_key,
                'type' => $requirement->type(),
                'input_key' => $requirement->inputKey(),
                'error_key' => $requirement->errorKey(),
                'is_required' => $requirement->is_required,
                'url' => $path ? route($routeName, ['path' => $path]) : null,
                'completed' => $completed,
                'is_orphan' => false,
            ];
        }

        // Uploads whose requirement no longer applies (deactivated/renamed/deleted)
        $extraUploads = ApplicantDocument::where('applicant_id', $applicant->id)
            ->whereNotIn('requirement_id', $requirements->pluck('id'))
            ->get();

        foreach ($extraUploads as $upload) {
            $documents[] = [
                'requirement_id' => $upload->requirement_id,
                'name' => $upload->requirement_name
                    ?? Requirement::withTrashed()->whereKey($upload->requirement_id)->value('name')
                    ?? 'Document',
                'field_key' => null,
                'type' => 'file',
                'input_key' => null,
                'error_key' => null,
                'is_required' => false,
                'url' => route($routeName, ['path' => $upload->file_path]),
                'completed' => false,
                'is_orphan' => true,
            ];
        }

        // Legacy column uploads (SF9 / Good Moral / Health Declaration) whose
        // requirement row was deleted — resolve the label from the trashed row.
        foreach (array_keys(self::LEGACY_LABELS) as $fieldKey) {
            if (in_array($fieldKey, $coveredFieldKeys, true)) {
                continue;
            }

            $legacy = self::legacyValue($applicant, $fieldKey);
            if (! $legacy) {
                continue;
            }

            $former = Requirement::withTrashed()->where('field_key', $fieldKey)->first(['id', 'name']);
            $name = $former?->name ?? self::LEGACY_LABELS[$fieldKey];

            $isDeclaration = $fieldKey === Requirement::DECLARATION_KEY;

            $documents[] = [
                'requirement_id' => $former?->id ?? $fieldKey,
                'name' => $name,
                'field_key' => $fieldKey,
                'type' => $isDeclaration ? 'declaration' : 'file',
                'input_key' => null,
                'error_key' => null,
                'is_required' => false,
                'url' => $isDeclaration ? null : route($routeName, ['path' => $legacy]),
                'completed' => $isDeclaration,
                'is_orphan' => true,
            ];
        }

        return $documents;
    }

    /**
     * Stored value of a legacy applicants column for a requirement field key.
     */
    private static function legacyValue(Applicant $applicant, string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'sf9_file' => $applicant->sf9_file,
            'good_moral_file' => $applicant->good_moral_file,
            'ste_declaration' => $applicant->ste_declaration ? 'declaration' : null,
            default => null,
        };
    }
}
