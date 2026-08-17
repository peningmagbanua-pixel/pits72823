<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\UserRolesTrait;

class RoleMiddleware
{
    use UserRolesTrait;

    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $this->callLoggedUser();

        if (!$user) {
            abort(403);
        }

        if ($user->hasRole([$role])) {
            return $next($request);
        }

        abort(403, 'You do not have the required role.');
    }
}
