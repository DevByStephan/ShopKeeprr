<?php

namespace App\Enums;

enum ModuleType: string
{
    case Plugin = 'plugin';
    case Payment = 'payment';
    case Shipping = 'shipping';
    case Marketing = 'marketing';
    case Vendor = 'vendor';
    case Utility = 'utility';
    case Theme = 'theme';
    case Component = 'component';
    case BusinessModel = 'business_model';

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }
}
