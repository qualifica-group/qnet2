<?php

namespace App\Enums;

/**
 * What to do with the residual of a partially collected installment (spec 0196, D-5).
 */
enum ResidualMode: string
{
    case Spread = 'spread';
    case NewInstallment = 'new_installment';
}
