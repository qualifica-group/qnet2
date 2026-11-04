<?php

use App\Broadcasting\WorkOrderChatChannel;
use Illuminate\Support\Facades\Broadcast;

// Private channel authorization rules live here.
Broadcast::channel(WorkOrderChatChannel::PATTERN, WorkOrderChatChannel::class);
