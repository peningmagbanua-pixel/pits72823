<?php

namespace App\Traits;

trait ConstantsTrait
{
    public $SYSTEM_ADMINISTRATOR = 'System Administrator';
    public $ENCODER = 'Encoder';
    public $COORDINATOR = 'Coordinator';
    public $PERMISSIONS_ACTION = ['browse', 'read', 'create', 'update', 'delete'];
    public $ADMIN_YES = 'Yes';
}
