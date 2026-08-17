<?php

namespace App\Enums;

enum BadgeColor: string
{
    case Primary = 'badge bg-primary-subtle text-primary';
    case Secondary = 'badge bg-secondary-subtle text-secondary';
    case Success = 'badge bg-success-subtle text-success';
    case Danger = 'badge bg-danger-subtle text-danger';
    case Warning = 'badge bg-warning-subtle text-warning';
    case Info = 'badge bg-info-subtle text-info';
    case Light = 'badge bg-light text-dark';
    case Dark = 'badge bg-dark-subtle text-dark';
}
