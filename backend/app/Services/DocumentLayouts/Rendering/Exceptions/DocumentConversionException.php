<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Exceptions;

use RuntimeException;

/**
 * The DOCX-to-PDF step failed: the LibreOffice binary is missing, exited
 * non-zero, timed out, or produced no output file.
 *
 * Deliberately NOT mapped to a business 4xx by the controllers: this is a
 * server/deployment fault, so it falls through to
 * BaseApiController::handleControllerException, which logs the full detail
 * (exit code + stderr, needed to tell "binary not installed" from "corrupted
 * source document") and answers the generic 500 envelope — the message here
 * never reaches the client outside debug mode.
 */
final class DocumentConversionException extends RuntimeException {}
