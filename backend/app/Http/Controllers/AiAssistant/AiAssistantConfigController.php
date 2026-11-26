<?php

namespace App\Http\Controllers\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\AiAssistant\Agent\ConnectorClient;
use App\Services\AiAssistant\Agent\OpenRouterAgentClient;
use App\Services\AiAssistant\AiModelCapabilities;
use App\Services\AiAssistant\Mentions\AiMentionRegistry;
use App\Services\AiAssistant\Settings\AiAssistantSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/ai-assistant/config — what the UI needs to render the assistant.
 * Never exposes provider keys, base URLs or the connector secret (spec 0347 AC-002, spec 0433).
 */
class AiAssistantConfigController extends BaseApiController
{
    public function __construct(
        private readonly AiMentionRegistry $registry,
        private readonly AiModelCapabilities $capabilities,
        private readonly AiAssistantSettingsService $settings,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $settings = $this->settings->effective();
            $configured = OpenRouterAgentClient::isConfigured() && ConnectorClient::isConfigured();

            return $this->ok([
                'floating_enabled' => (bool) config('ai-assistant.floating_enabled'),
                'page_enabled' => (bool) config('ai-assistant.page_enabled'),
                'enabled' => $settings->enabled,
                'configured' => $configured,
                'driver' => $configured ? 'openrouter' : null,
                'model' => $configured ? $settings->modelId : null,
                'limits' => [
                    'max_message_chars' => (int) config('ai-assistant.max_message_chars'),
                    'guide_max_chars' => (int) config('ai-assistant.guide_max_chars'),
                    'max_mentions' => (int) config('ai-assistant.max_mentions'),
                    'max_files' => (int) config('ai-assistant.max_files'),
                    'max_file_size_kb' => (int) config('ai-assistant.max_file_size_kb'),
                    'accepts_attachments' => $this->capabilities->acceptsAttachments(),
                    'accepted_mimes' => array_values((array) config('ai-assistant.accepted_mimes')),
                    'poll_interval_ms' => (int) config('ai-assistant.agent.poll_interval_ms'),
                ],
                'mention_modules' => $this->registry->availableModules($request->user()),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
