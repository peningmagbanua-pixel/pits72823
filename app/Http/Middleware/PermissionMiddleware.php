<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ConstantsTrait;
use App\Traits\UserRolesTrait;

class PermissionMiddleware
{
    use ConstantsTrait, UserRolesTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $this->callLoggedUser();

        if (!$user) {
            abort(403);
        }

        if ($user->hasRole([$this->SYSTEM_ADMINISTRATOR])) {
            return $next($request);
        }

        $has_permissions = $user->roles
            ->flatMap(function ($role) {
                return $role->permissions;
            })->pluck('name')->contains($permission);

        if (!$has_permissions) {
            abort(403, 'You do not have permission.');
        }

        return $next($request);
    }
}
