<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

final class AiChatDriverManager
{
    public const DRIVER_OPENAI = 'openai';

    public const DRIVER_ANTHROPIC = 'anthropic';

    public const DRIVER_OPENROUTER = 'openrouter';

    /**
     * Config is read on every call (not cached in the constructor) so the
     * singleton never serves a stale driver choice.
     */
    public function driverName(): ?string
    {
        $name = config('ai-assistant.driver');

        return match ($name) {
            self::DRIVER_OPENAI => self::DRIVER_OPENAI,
            self::DRIVER_ANTHROPIC => self::DRIVER_ANTHROPIC,
            self::DRIVER_OPENROUTER => self::DRIVER_OPENROUTER,
            default => null,
        };
    }

    public function isConfigured(): bool
    {
        return match ($this->driverName()) {
            self::DRIVER_OPENAI => true,
            self::DRIVER_ANTHROPIC, self::DRIVER_OPENROUTER => filled(config("ai-assistant.{$this->driverName()}.api_key")),
            default => false,
        };
    }

    public function model(): ?string
    {
        $name = $this->driverName();

        return $name === null ? null : config("ai-assistant.{$name}.model");
    }

    /**
     * @throws AiAssistantNotConfiguredException
     */
    public function driver(): AiChatDriver
    {
        if (! $this->isConfigured()) {
            throw new AiAssistantNotConfiguredException;
        }

        return match ($this->driverName()) {
            self::DRIVER_ANTHROPIC => new AnthropicDriver,
            self::DRIVER_OPENROUTER => new OpenRouterDriver,
            default => new OpenAiCompatibleDriver,
        };
    }
}
