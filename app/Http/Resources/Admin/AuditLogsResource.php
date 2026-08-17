<?php

namespace App\Http\Resources\Admin;

use App\Enums\ActionType;
use App\Enums\BadgeColor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\FormatsTrait;
use App\Traits\UserRolesTrait;
class AuditLogsResource extends JsonResource
{
    use FormatsTrait, UserRolesTrait;
    
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if($this->checkHasRoleSystemAdministrator()) {
            $old_values = $this->old_values;
            $new_values = $this->new_values;
        } else {
            $old_values = '';
            $new_values = '';
        }

        if ($this->action === ActionType::Created->value) {
            $action_text = ActionType::Created->value;
            $action_color = BadgeColor::Primary->value;
        } else if ($this->action === ActionType::Updated->value) {
            $action_text = ActionType::Updated->value;
            $action_color = BadgeColor::Secondary->value;
        } else {
            $action_text = ActionType::Deleted->value;
            $action_color = BadgeColor::Danger->value;
        }

        return [
            'id' => $this->id,
            'user' => $this->user ? $this->firstnameLastnameExtFormat($this->user) : '',
            'avatar' => $this->user->avatar,
            'action_text' => $action_text,
            'action_color' => $action_color,
            'module' => $this->module,
            'description' => $this->description,
            'old_values' => $old_values,
            'new_values' => $new_values,
            'created_at' => date("Y-m-d H:i:s", strtotime($this->created_at)),
        ];
    }
}
