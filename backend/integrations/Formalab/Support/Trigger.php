<?php

declare(strict_types=1);

namespace Integrations\Formalab\Support;

/**
 * What started a Formalab call or sync run.
 */
enum Trigger: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Webhook = 'webhook';
    case WorkOrder = 'work_order';
    case Health = 'health';
}
