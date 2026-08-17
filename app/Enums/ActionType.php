<?php

namespace App\Enums;

enum ActionType: string
{
    case Created = 'Created';
    case Updated = 'Updated';
    case Deleted = 'Deleted';
}
