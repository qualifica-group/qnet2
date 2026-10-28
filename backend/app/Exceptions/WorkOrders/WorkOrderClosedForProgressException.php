<?php

declare(strict_types=1);

namespace App\Exceptions\WorkOrders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A force-closed commessa refuses progress entries to be added or removed
 * (spec 0321, D-8). An HttpException so BaseApiController::handleControllerException
 * answers 409 with the translated message and no error-log noise.
 */
final class WorkOrderClosedForProgressException extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, __('work_orders.progress.work_order_closed'));
    }
}
