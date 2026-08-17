<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    /**
     * Return the current applicant's application data (or null if not yet submitted).
     */
    public function show()
    {
        $applicant = Auth::guard('applicant')->user();

        if (!$applicant->sf9_file) {
            return response()->json(['submitted' => false]);
        }

        return response()->json([
            'submitted' => true,
            'program'                   => $applicant->program,
            'lastname'                  => $applicant->lastname,
            'firstname'                 => $applicant->firstname,
            'middlename'                => $applicant->middlename,
            'extname'                   => $applicant->extname,
            'date_of_birth'             => $applicant->date_of_birth,
            'place_of_birth'            => $applicant->place_of_birth,
            'citizenship'               => $applicant->citizenship,
            'sex'                       => $applicant->sex,
            'complete_address'          => $applicant->complete_address,
            'parent_guardian_name'      => $applicant->parent_guardian_name,
            'contact_number'            => $applicant->contact_number,
            'email'                     => $applicant->email,
            'sf9_file'                  => $applicant->sf9_file   ? route('applicant.file.serve', ['path' => $applicant->sf9_file])   : null,
            'good_moral_file'           => $applicant->good_moral_file          ? route('applicant.file.serve', ['path' => $applicant->good_moral_file])          : null,
            'health_declaration_file'   => $applicant->health_declaration_file  ? route('applicant.file.serve', ['path' => $applicant->health_declaration_file])  : null,
            'ste_declaration_file'      => $applicant->ste_declaration_file     ? route('applicant.file.serve', ['path' => $applicant->ste_declaration_file])     : null,
            'ste_declaration'           => $applicant->ste_declaration,
            'application_status'        => $applicant->application_status ?? 'pending',
        ]);
    }

    /**
     * Handle the enrollment form submission.
     */
    public function submit(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();

        // Prevent duplicate submission
        if ($applicant->sf9_file) {
            return response()->json([
                'message' => 'You have already submitted an application.',
            ], 422);
        }

        $program = $request->input('program');

        // --- Base validation rules (both programs) ---
        $rules = [
            'program'                   => ['required', 'in:SPICT,STE'],
            'lastname'                  => ['required', 'string', 'max:255'],
            'firstname'                 => ['required', 'string', 'max:255'],
            'middlename'                => ['nullable', 'string', 'max:255'],
            'extname'                   => ['nullable', 'string', 'max:255'],
            'date_of_birth'             => ['required', 'date', 'before:today'],
            'place_of_birth'            => ['required', 'string', 'max:255'],
            'citizenship'               => ['required', 'string', 'max:100'],
            'sex'                       => ['required', 'in:Male,Female'],
            'complete_address'          => ['required', 'string', 'max:500'],
            'parent_guardian_name'      => ['required', 'string', 'max:255'],
            'contact_number'            => ['required', 'string', 'max:20'],
            'elementary_school'         => ['required', 'string', 'max:255'],
            'elementary_school_address' => ['required', 'string', 'max:500'],
            'sf9_file'                  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        // --- STE-only additional documents ---
        if ($program === 'STE') {
            $rules['good_moral_file']           = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

            // Online STE Declaration Form
            $rules['ste_declaration']                       = ['required', 'array'];
            $rules['ste_declaration.acknowledged_name']    = [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($applicant) {
                    if (!$this->namesMatch($value, $this->registeredName($applicant))) {
                        $fail('The acknowledged name does not match your registered name.');
                    }
                },
            ];
            $rules['ste_declaration.parent_guardian_name'] = [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$this->namesMatch($value, $request->input('parent_guardian_name', ''))) {
                        $fail('The parent/guardian name does not match the parent/guardian name entered in the form.');
                    }
                },
            ];
            $rules['ste_declaration.agreements']            = ['required', 'array', 'size:5'];
            $rules['ste_declaration.agreements.*']          = ['accepted'];
            $rules['ste_declaration.parent_agreement']      = ['accepted'];
        }

        $validated = $request->validate($rules);

        $folder = 'applications/' . $applicant->id;

        // Store SF9 on the private disk (not publicly accessible)
        $sf9Path = $request->file('sf9_file')->store($folder, 'local');

        $updateData = [
            'program'                   => $validated['program'],
            'lastname'                  => $validated['lastname'],
            'firstname'                 => $validated['firstname'],
            'middlename'                => $validated['middlename'] ?? null,
            'extname'                   => $validated['extname'] ?? null,
            'date_of_birth'             => $validated['date_of_birth'],
            'place_of_birth'            => $validated['place_of_birth'],
            'citizenship'               => $validated['citizenship'],
            'sex'                       => $validated['sex'],
            'complete_address'          => $validated['complete_address'],
            'parent_guardian_name'      => $validated['parent_guardian_name'],
            'contact_number'            => $validated['contact_number'],
            'elementary_school'         => $validated['elementary_school'],
            'elementary_school_address' => $validated['elementary_school_address'],
            'sf9_file'                  => $sf9Path,
            'application_status'        => 'pending',
        ];

        // Store STE-only documents (private disk)
        if ($program === 'STE') {
            $updateData['good_moral_file']          = $request->file('good_moral_file')->store($folder, 'local');

            $declaration = $validated['ste_declaration'];

            $updateData['ste_declaration'] = [
                'acknowledged_name'    => $declaration['acknowledged_name'],
                'parent_guardian_name' => $declaration['parent_guardian_name'],
                'agreements'           => array_map(fn ($v) => (bool) $v, $declaration['agreements']),
                'parent_agreement'     => (bool) $declaration['parent_agreement'],
                'signed_at'            => now()->format('Y-m-d H:i:s'),
                'applicant_snapshot'   => [
                    'full_name'            => $this->registeredName($applicant),
                    'date_of_birth'        => $validated['date_of_birth'],
                    'sex'                  => $validated['sex'],
                    'complete_address'     => $validated['complete_address'],
                    'parent_guardian_name' => $validated['parent_guardian_name'],
                    'contact_number'       => $validated['contact_number'],
                    'email'                => $applicant->email,
                    'elementary_school'    => $validated['elementary_school'],
                ],
            ];
        }

        $applicant->update($updateData);

        return response()->json([
            'message' => 'Your application has been submitted successfully and is currently under review.',
        ]);
    }

    /**
     * The applicant's registered full name (mirrors FormatsTrait::firstnameLastnameExtFormat).
     */
    private function registeredName($applicant): string
    {
        $fullname = trim(($applicant->firstname ?? '') . ' ' . ($applicant->lastname ?? ''));
        $fullname .= $applicant->extname ? ' ' . $applicant->extname : '';

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

        $typedTokens    = $tokens($typed);
        $expectedTokens = $tokens($expected);

        if (count($typedTokens) === 0 || count($expectedTokens) === 0) {
            return false;
        }

        return count(array_intersect($typedTokens, $expectedTokens)) > 0;
    }
}
