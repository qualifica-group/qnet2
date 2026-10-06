<?php

namespace App\Enums;

/**
 * Which module an EmailTemplate targets (spec 0175, D-10): kept polymorphic
 * on purpose so Quote/Opportunity email templates can be added later without
 * a schema change — WorkOrders is the only case this version ships.
 */
enum EmailTemplateModule: string
{
    case WorkOrders = 'work_orders';

    case Invoices = 'invoices';
}
