<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Admin\Program;
use App\Models\Admin\Requirement;
use Illuminate\Http\Request;

class RequirementsController extends Controller
{
    /**
     * Active requirements shown to applicants, each with the ids of the
     * programs it applies to (empty = every program). Serialized through
     * Requirement::toDocumentArray() so the form posts and validates each
     * requirement under exactly the key the server expects.
     *
     * When a public `program` initials parameter is given, the program is
     * resolved and validated server-side and only the requirements applicable
     * to it are returned, so the client never has to identify the program
     * itself.
     */
    public function index(Request $request)
    {
        if ($request->filled('program')) {
            $validated = $request->validate([
                'program' => ['required', 'string', 'max:10'],
            ]);

            $program = Program::where('initials', strtoupper(trim($validated['program'])))
                ->where('is_active', true)
                ->first();

            if (! $program) {
                return response()->json([
                    'message' => 'The selected program is invalid.',
                    'errors' => ['program' => ['The selected program is invalid.']],
                ], 422);
            }

            $requirements = Requirement::forProgram($program->id)
                ->with('programs')
                ->get();
        } else {
            $requirements = Requirement::query()
                ->active()
                ->with('programs')
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();
        }

        return response()->json(
            $requirements
                ->map(fn (Requirement $requirement) => $requirement->toDocumentArray())
                ->values()
        );
    }
}
