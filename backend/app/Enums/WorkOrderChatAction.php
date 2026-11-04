<?php

namespace App\Enums;

/**
 * What a broadcast chat event reports about its message (spec 0352).
 */
enum WorkOrderChatAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
