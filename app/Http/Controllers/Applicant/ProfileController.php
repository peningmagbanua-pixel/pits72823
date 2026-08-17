<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Applicant\Applicant;

class ProfileController extends Controller
{
    public function profile()
    {
        $applicant = Auth::guard('applicant')->user();
        
        $name = $this->firstnameLastnameExtFormat($applicant);

        return [
            'name' => $name,
            'email' => $applicant->email,
            'profile' => $applicant->profile,
            'program' => $applicant->program,
        ];
    }

    public function getProfile()
    {
        $applicant = Auth::guard('applicant')->user();

        return response()->json([
            'id' => $applicant->id,
            'lastname' => $applicant->lastname,
            'firstname' => $applicant->firstname,
            'middlename' => $applicant->middlename,
            'extname' => $applicant->extname,
            'email' => $applicant->email,
            'profile' => $applicant->profile,
            'created_at' => date("Y-m-d H:i:s", strtotime($applicant->created_at)),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();

        $validated = $request->validate([
            'lastname' => 'required|string|max:255',
            'firstname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extname' => 'nullable|string|max:255',
            'email' => 'required|email|unique:applicants,email,' . $applicant->id,
        ]);

        $applicant->update([
            'lastname' => $validated['lastname'],
            'firstname' => $validated['firstname'],
            'middlename' => $validated['middlename'],
            'extname' => $validated['extname'],
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'Profile updated successfully!',
        ]);
    }

    public function changePassword(Request $request)
    {
        $applicant = Auth::guard('applicant')->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $applicant->password)) {
            return response()->json([
                'message' => 'Current password is incorrect!',
            ], 422);
        }

        $applicant->update([
            'password' => bcrypt($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully!',
        ]);
    }
}
