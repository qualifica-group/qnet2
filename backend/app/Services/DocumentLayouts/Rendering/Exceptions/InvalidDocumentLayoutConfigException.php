<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Exceptions;

use RuntimeException;

/**
 * Thrown by QuoteDocumentGenerator when a DocumentLayout's persisted
 * `config` no longer passes DocumentLayoutConfigValidator (spec 0070
 * AC-262: a layout corrupted by a manual DB edit) — the future
 * document-generation controller maps this to a 422, never a 500 or a
 * broken `.docx`.
 */
final class InvalidDocumentLayoutConfigException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors  path => message, from DocumentLayoutConfigValidator::validate().
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The document layout configuration is not valid.');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
