<?php

declare(strict_types=1);

namespace App\Services\AiAssistant;

/**
 * What the active model can take in (spec 0433). The single place that answers
 * "does the model accept attachments?".
 */
class AiModelCapabilities
{
    public function acceptsAttachments(): bool
    {
        // shortcut: the list of models allows only text-only models (decision Q15, Q17), so no file ever reaches the model; read the capability of the chosen model here when the list admits one that takes files.
        return false;
    }
}
