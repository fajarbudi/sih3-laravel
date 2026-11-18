<?php

namespace App\Enums;

enum TipePos: string
{
    case Hidrologi = 'hidrologi';
    case Hidrometeorologi = 'hidrometeorologi';
    case Hidrogeologi = 'hidrogeologi';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
