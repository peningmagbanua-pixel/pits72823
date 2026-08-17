<?php

namespace App\Http\Controllers;
use Illuminate\Foundation\Auth\Access\Authorizable;
use App\Traits\FormatsTrait;
use App\Traits\UserRolesTrait;
use App\Traits\ConstantsTrait;
abstract class Controller
{
    use Authorizable;
    use ConstantsTrait, UserRolesTrait, FormatsTrait;
}
