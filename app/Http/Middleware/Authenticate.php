<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {

            if ($request->is('applicant/*')) {
                return route('applicant.login');
            }

            if ($request->is('admin/*')) {
                return route('admin.login');
            }

            return route('admin.login');
        }
    }
}
