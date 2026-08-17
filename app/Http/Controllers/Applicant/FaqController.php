<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = \App\Models\Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'question', 'answer']);

        return response()->json($faqs);
    }
}
