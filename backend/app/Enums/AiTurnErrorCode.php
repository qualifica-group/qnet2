<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an AI assistant turn ended without an answer (spec 0433, `fixed_texts`).
 * Each code has one fixed English phrase, translated in lang/it.json.
 */
enum AiTurnErrorCode: string
{
    case ModelUnavailable = 'model_unavailable';
    case ConnectorUnavailable = 'connector_unavailable';
    case LinkLost = 'link_lost';
    case TooManySteps = 'too_many_steps';
    case TurnTimeout = 'turn_timeout';
    case StreamError = 'stream_error';
    case SwitchedOff = 'switched_off';
    case LimitPerson = 'limit_person';
    case LimitTotal = 'limit_total';
    case Internal = 'internal';

    public function message(string $locale): string
    {
        $text = match ($this) {
            self::ModelUnavailable => 'The assistant could not reach the model. Try again later.',
            self::ConnectorUnavailable => 'The assistant could not reach its tools. Try again later.',
            self::LinkLost => 'Your session changed. Send your message again.',
            self::TooManySteps => 'The assistant stopped: this request needed too many steps.',
            self::TurnTimeout => 'The assistant stopped: this request took too long.',
            self::StreamError => 'The model returned an error. Try again.',
            self::SwitchedOff => 'The AI assistant has been switched off.',
            self::LimitPerson => 'You have reached your monthly assistant spending limit.',
            self::LimitTotal => 'The company monthly assistant spending limit has been reached.',
            self::Internal => 'Something went wrong. Try again later.',
        };

        return __($text, [], $locale);
    }
}
