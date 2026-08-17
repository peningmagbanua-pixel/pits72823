<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\UserStatus;
use App\Enums\BadgeColor;
use App\Traits\FormatsTrait;
use App\Traits\UserRolesTrait;
class UsersResource extends JsonResource
{
    use FormatsTrait, UserRolesTrait;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $status = $this->is_active;
        $status_text = $status === UserStatus::Active->value ? UserStatus::Active->name : UserStatus::Inactive->name;
        $status_color = $status === UserStatus::Active->value ? BadgeColor::Success->value : BadgeColor::Danger->value;

        $roles = $this->roles->pluck('name')->toArray();
        $role_ids = $this->roles->pluck('id')->toArray();
        $is_system_administrator = in_array($this->SYSTEM_ADMINISTRATOR, $roles);

        return [
            'created_at' => date("Y-m-d H:i:s", strtotime($this->created_at)),
            'id' => $this->id,
            'name' => $this->firstnameLastnameExtFormat($this),
            'lastname' => $this->lastname,
            'firstname' => $this->firstname,
            'middlename' => $this->middlename,
            'email' => $this->email,
            'extname' => $this->extname,
            'roles' => $roles,
            'role_ids' => $role_ids,
            'avatar' => $this->avatar,
            'is_system_administrator' => $is_system_administrator,
            'is_active' => $status,
            'last_login' => $this->last_login_at ? date("Y-m-d H:i:s", strtotime($this->last_login_at)) : '-',
            'status_color' => $status_color,
            'status_text' => $status_text,
        ];
    }
}
