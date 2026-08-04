<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The PERSPECTIVE a transfer notification is written from (spec 0081): one
 * transfer produces three different texts, because losing a contact, gaining
 * one and supervising the move are three different pieces of news.
 *
 * The three sets are disjoint by construction in
 * RequestTransferService::dispatchNotifications(): whoever already received
 * the previous/new operator text is excluded from the supervisory copy, so
 * nobody is ever notified twice for the same transfer.
 */
enum TransferRecipientRoleEnum: string
{
    case PreviousOperator = 'PREVIOUS_OPERATOR';

    case NewOperator = 'NEW_OPERATOR';

    case Supervisor = 'SUPERVISOR';
}
