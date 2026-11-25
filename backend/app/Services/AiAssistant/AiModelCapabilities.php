<?php

declare(strict_types=1);

namespace App\Services\AiAssistant;

/**
 * What the active model can take in (spec 0433). The single place that answers
 * "does the model accept attachments?": today read from configuration, to be
 * replaced by the model settings when they exist.
 */
final class AiModelCapabilities
{
    public function acceptsAttachments(): bool
    {
        return (bool) config('ai-assistant.model_accepts_attachments');
    }
}
