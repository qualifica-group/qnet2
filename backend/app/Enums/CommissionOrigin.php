<?php

namespace App\Enums;

enum CommissionOrigin: string
{
    case Product = 'PRODUCT';
    case ProductCategory = 'PRODUCT_CATEGORY';
    case ManualOverride = 'MANUAL_OVERRIDE';
}
