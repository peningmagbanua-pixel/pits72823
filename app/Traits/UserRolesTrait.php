<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use App\Traits\ConstantsTrait;

trait UserRolesTrait
{
    use ConstantsTrait;

    public function callLoggedUser()
    {
        return Auth::guard('web')->user();
    }

    public function checkHasRoleSystemAdministrator()
    {
        return Auth::guard('web')->user()->hasRole([$this->SYSTEM_ADMINISTRATOR]);
    }

    public function checkHasRoleEncoder()
    {
        return Auth::guard('web')->user()->hasRole([$this->ENCODER]);
    }

    public function checkHasRoleCoordinator()
    {
        return Auth::guard('web')->user()->hasRole([$this->COORDINATOR]);
    }
}
