<?php

namespace App\Traits;

trait FormatsTrait
{
    public function firstnameLastnameExtFormat($item)
    {
        if (!$item) {
            return '';
        }

        $fullname = $item->firstname . " " . $item->lastname;
        $fullname .= $item->extname ? ' ' . $item->extname : '';

        return $this->Propercase($fullname);
    }

    public function Propercase($string)
    {
        return ucwords(strtolower($string));
    }
}
