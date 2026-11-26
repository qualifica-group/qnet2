<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

use RuntimeException;

/**
 * A monthly cap leaves no room for the call: the turn closes with `limit_reached` and the fixed text.
 */
final class AiSpendLimitReached extends RuntimeException
{
    public const string SCOPE_PERSON = 'person';

    public const string SCOPE_TOTAL = 'total';

    public function __construct(public readonly string $scope)
    {
        parent::__construct(self::textFor($scope));
    }

    /** The fixed text shown to the person (translated in lang/it.json). */
    public static function textFor(string $scope): string
    {
        return $scope === self::SCOPE_PERSON
            ? __('You have reached your monthly assistant spending limit.')
            : __('The company monthly assistant spending limit has been reached.');
    }
}
