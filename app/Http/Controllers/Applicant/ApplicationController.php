<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use App\Models\Admin\SchoolYear;
use App\Models\Applicant\ActivityLog;
use App\Models\Applicant\ApplicantDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    /**
     * Session key holding the program the applicant selected on the program
     * selection page. The resolved id only ever lives server-side; the
     * frontend only ever sees the public program initials.
     */
    private const SESSION_PROGRAM_KEY = 'selected_program_id';

    /**
     * Return the current applicant's application data (or null if not yet submitted).
     */
    public function show()
    {
        $applicant = Auth::guard('applicant')->user();

        ActivityLog::logActivity(
            $applicant->id,
            'viewed_application',
            'Viewed application information/status.',
            [],
            request()->ip(),
            request()->userAgent()
        );

        if (! $applicant->submitted_at) {
            return response()->json([
                'submitted' => false,
                'application_period' => SchoolYear::applicationPeriod(),
                'selected_program' => $this->selectedProgramPayload(),
            ]);
        }

        return response()->json([
            'submitted' => true,
            'application_period' => SchoolYear::applicationPeriod(),
            'selected_program' => $this->selectedProgramPayload(),
            'program' => $applicant->program?->initials,
            'program_id' => $applicant->program_id,
            'lastname' => $applicant->lastname,
            'firstname' => $applicant->firstname,
            'middlename' => $applicant->middlename,
            'extname' => $applicant->extname,
            'date_of_birth' => $applicant->date_of_birth,
            'place_of_birth' => $applicant->place_of_birth,
            'citizenship' => $applicant->citizenship,
            'sex' => $applicant->sex,
            'complete_address' => $applicant->complete_address,
            'parent_guardian_name' => $applicant->parent_guardian_name,
            'contact_number' => $applicant->contact_number,
            'email' => $applicant->email,
            'sf9_file' => $applicant->sf9_file ? route('applicant.file.serve', ['path' => $applicant->sf9_file]) : null,
            'good_moral_file' => $applicant->good_moral_file ? route('applicant.file.serve', ['path' => $applicant->good_moral_file]) : null,
            'health_declaration_file' => $applicant->health_declaration_file ? route('applicant.file.serve', ['path' => $applicant->health_declaration_file]) : null,
            'ste_declaration_file' => $applicant->ste_declaration_file ? route('applicant.file.serve', ['path' => $applicant->ste_declaration_file]) : null,
            'ste_declaration' => $applicant->ste_declaration,
            'application_status' => $applicant->application_status ?? 'pending',
            'rejection_reason' => $applicant->rejection_reason,
            'documents' => ApplicantDocument::buildFor($applicant, 'applicant.file.serve'),
        ]);
    }

    /**
     * Resolve and remember the program the applicant selected.
     *
     * The client only ever sends the public program initials; the actual
     * program id is resolved and stored server-side so it can never be
     * manipulated through the URL or form data.
     */
    public function selectProgram(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();

        // Same access rule as the form itself: only applicants who have not
        // submitted yet (or whose application was rejected) may pick a program.
        if ($applicant->submitted_at && $applicant->application_status !== 'rejected') {
            return response()->json([
                'message' => 'You have already submitted an application.',
            ], 422);
        }

        $validated = $request->validate([
            'program' => ['required', 'string', 'max:10'],
        ]);

        $program = $this->findSelectableProgram($validated['program']);

        if (! $program) {
            return response()->json([
                'message' => 'The selected program is invalid.',
                'errors' => ['program' => ['The selected program is invalid.']],
            ], 422);
        }

        $request->session()->put(self::SESSION_PROGRAM_KEY, $program->id);

        return response()->json([
            'selected_program' => [
                'initials' => $program->initials,
                'name' => $program->name,
            ],
        ]);
    }

    /**
     * Handle the enrollment form submission.
     */
    public function submit(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();

        // Prevent duplicate submission, except when the applicant is
        // resubmitting after a rejection (reapplication).
        if ($applicant->submitted_at && $applicant->application_status !== 'rejected') {
            return response()->json([
                'message' => 'You have already submitted an application.',
            ], 422);
        }

        // Only accept submissions while the online application period of the
        // active school year is open.
        $application_period = SchoolYear::applicationPeriod();

        if ($application_period['status'] === 'not_open') {
            return response()->json([
                'message' => 'The online application is not yet open. It opens on '
                    . date('F j, Y', strtotime($application_period['application_start'])) . '.',
            ], 422);
        }

        if ($application_period['status'] !== 'open') {
            return response()->json([
                'message' => 'The online application period has ended. Applications are no longer accepted.',
            ], 422);
        }

        // The program is identified server-side from the selection made on the
        // program selection page; the request itself never carries an id.
        $program = $this->selectedProgram();

        if (! $program) {
            return response()->json([
                'message' => 'The selected program is invalid. Please select a program and try again.',
                'errors' => ['program' => ['The selected program is invalid.']],
            ], 422);
        }

        $school_year = SchoolYear::where('is_active', true)->first();

        // Active requirements applicable to the selected program
        $requirements = Requirement::forProgram($program->id)->get();

        // --- Base validation rules (all programs) ---
        $rules = [
            'lastname' => ['required', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'extname' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'citizenship' => ['required', 'string', 'max:100'],
            'sex' => ['required', 'in:Male,Female'],
            'complete_address' => ['required', 'string', 'max:500'],
            'parent_guardian_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'regex:/^[0-9]{1,11}$/'],
            'elementary_school' => ['required', 'string', 'max:255'],
            'elementary_school_address' => ['required', 'string', 'max:500'],
        ];

        // --- Requirement-driven document rules ---
        foreach ($requirements as $requirement) {
            if ($requirement->isDeclaration()) {
                // Online Health Declaration Form
                if ($requirement->is_required) {
                    $rules['ste_declaration'] = ['required', 'array'];
                    $rules['ste_declaration.learner_name'] = ['required', 'string', 'max:255'];
                    $rules['ste_declaration.health_agreed'] = ['required', 'accepted'];
                    $rules['ste_declaration.conditions'] = ['required', 'array', 'min:1'];
                    $rules['ste_declaration.conditions.*'] = ['required', 'string', 'max:100'];
                    $rules['ste_declaration.others_text'] = ['nullable', 'string', 'max:1000'];
                    $rules['ste_declaration.guardian_name'] = ['required', 'string', 'max:255', 'same:parent_guardian_name'];
                    $rules['ste_declaration.date_signed'] = ['required', 'date'];
                    // Drawn signature (PNG/JPEG data URL captured by the signature pad).
                    $rules['ste_declaration.signature'] = [
                        'required',
                        'string',
                        'regex:/^data:image\/(png|jpeg);base64,/',
                        'max:200000',
                    ];
                    $rules['ste_declaration.cert_agreed'] = ['required', 'accepted'];
                } else {
                    $rules['ste_declaration'] = ['nullable', 'array'];
                }
            } else {
                $rules[$requirement->errorKey()] = [
                    $requirement->is_required ? 'required' : 'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                ];
            }
        }

        $validated = $request->validate($rules);

        $folder = 'applications/'.$applicant->id;

        $updateData = [
            'program_id' => $program->id,
            'lastname' => $validated['lastname'],
            'firstname' => $validated['firstname'],
            'middlename' => $validated['middlename'] ?? null,
            'extname' => $validated['extname'] ?? null,
            'date_of_birth' => $validated['date_of_birth'],
            'place_of_birth' => $validated['place_of_birth'],
            'citizenship' => $validated['citizenship'],
            'sex' => $validated['sex'],
            'complete_address' => $validated['complete_address'],
            'parent_guardian_name' => $validated['parent_guardian_name'],
            'contact_number' => $validated['contact_number'],
            'elementary_school' => $validated['elementary_school'],
            'elementary_school_address' => $validated['elementary_school_address'],
            'application_status' => 'pending',
            // Resubmission (reapplication) resets the rejection outcome and
            // any stale exam assignment from the previous application.
            'rejection_reason' => null,
            'schedule_id' => null,
            'exam_status' => null,
            'submitted_at' => now(),
            'school_year_id' => $school_year?->id,
        ];

        $genericPaths = [];

        foreach ($requirements as $requirement) {
            if ($requirement->isDeclaration()) {
                continue;
            }

            // The server reads nested upload keys with dot notation; the
            // bracketed input_key is what the form posts on the wire.
            $fileKey = $requirement->errorKey();

            if (! $request->hasFile($fileKey)) {
                continue;
            }

            // Legacy requirements live on the applicants table (private disk);
            // everything else is stored as a per-requirement applicant document.
            if ($requirement->usesLegacyColumn()) {
                $updateData[$requirement->field_key] = $request->file($fileKey)->store($folder, 'local');
            } else {
                $genericPaths[$requirement->id] = [
                    'path' => $request->file($fileKey)->store($folder, 'local'),
                    'name' => $requirement->name,
                ];
            }
        }

        // Store the online Health Declaration Form (STE only)
        $ste_requirement = $requirements->first(fn (Requirement $requirement) => $requirement->isDeclaration());
        if ($ste_requirement && is_array($validated['ste_declaration'] ?? null)) {
            $declaration = $validated['ste_declaration'];

            $updateData['ste_declaration'] = [
                'learner_name' => $declaration['learner_name'],
                'health_agreed' => true,
                'conditions' => $declaration['conditions'],
                'others_text' => $declaration['others_text'] ?? null,
                'guardian_name' => $declaration['guardian_name'],
                'date_signed' => $declaration['date_signed'],
                'signature' => $declaration['signature'],
                'cert_agreed' => (bool) $declaration['cert_agreed'],
                'signed_at' => now()->format('Y-m-d H:i:s'),
                'applicant_snapshot' => [
                    'full_name' => $this->registeredName($applicant),
                    'date_of_birth' => $validated['date_of_birth'],
                    'sex' => $validated['sex'],
                    'complete_address' => $validated['complete_address'],
                    'parent_guardian_name' => $validated['parent_guardian_name'],
                    'contact_number' => $validated['contact_number'],
                    'email' => $applicant->email,
                    'elementary_school' => $validated['elementary_school'],
                ],
            ];
        }

        $applicant->update($updateData);

        // Store requirement-specific files (private disk)
        foreach ($genericPaths as $requirement_id => $file) {
            ApplicantDocument::updateOrCreate(
                ['applicant_id' => $applicant->id, 'requirement_id' => $requirement_id],
                ['file_path' => $file['path'], 'requirement_name' => $file['name']]
            );
        }

        ActivityLog::logActivity(
            $applicant->id,
            'application_submitted',
            'Application form submitted for program '.$program->initials.'.',
            ['program_id' => $program->id],
            request()->ip(),
            request()->userAgent()
        );

        return response()->json([
            'message' => 'Your application has been submitted successfully and is currently under review.',
        ]);
    }

    /**
     * The program selected in this applicant's session, re-validated on every
     * read: it must still exist, be active, and not be soft-deleted.
     */
    private function selectedProgram(): ?Program
    {
        $programId = request()->session()->get(self::SESSION_PROGRAM_KEY);

        if (! $programId) {
            return null;
        }

        $program = Program::find($programId);

        return $program && $program->is_active ? $program : null;
    }

    /**
     * Id-free view of the selected program for the frontend.
     *
     * @return array<string, string>|null
     */
    private function selectedProgramPayload(): ?array
    {
        $program = $this->selectedProgram();

        if (! $program) {
            return null;
        }

        return [
            'initials' => $program->initials,
            'name' => $program->name,
        ];
    }

    /**
     * Look up a selectable program by its public initials (case-insensitive).
     * Only active programs may be selected, mirroring the selection page.
     */
    private function findSelectableProgram(string $initials): ?Program
    {
        return Program::where('initials', strtoupper(trim($initials)))
            ->where('is_active', true)
            ->first();
    }

    /**
     * The applicant's registered full name (mirrors FormatsTrait::firstnameLastnameExtFormat).
     */
    private function registeredName($applicant): string
    {
        $fullname = trim(($applicant->firstname ?? '').' '.($applicant->lastname ?? ''));
        $fullname .= $applicant->extname ? ' '.$applicant->extname : '';

        return ucwords(strtolower($fullname));
    }

    /**
     * Lenient name comparison: normalized (lowercase, collapsed whitespace, punctuation stripped)
     * and accepted when at least one word overlaps. Only completely unrelated input is rejected.
     */
    private function namesMatch(string $typed, string $expected): bool
    {
        $tokens = function (string $value): array {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value);

            return array_values(array_filter(preg_split('/\s+/u', $value)));
        };

        $typedTokens = $tokens($typed);
        $expectedTokens = $tokens($expected);

        if (count($typedTokens) === 0 || count($expectedTokens) === 0) {
            return false;
        }

        return count(array_intersect($typedTokens, $expectedTokens)) > 0;
    }
}
