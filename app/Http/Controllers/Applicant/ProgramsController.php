<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProgramResource;
use App\Models\Admin\Program;

class ProgramsController extends Controller
{
    public function index()
    {
        $programs = Program::where('is_active', true)
            ->orderBy('name')
            ->get();

        return ProgramResource::collection($programs);
    }
}
