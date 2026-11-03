<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Mentions;

use RuntimeException;

/**
 * The mentioned module is unknown or the record does not exist: the caller maps
 * it to a 422 on `mentions.N` (spec 0347, AC-010).
 */
final class AiMentionNotFoundException extends RuntimeException {}
