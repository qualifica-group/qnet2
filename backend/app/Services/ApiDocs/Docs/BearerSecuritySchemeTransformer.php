<?php

namespace App\Services\ApiDocs\Docs;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

/**
 * Declares the bearer scheme every operation of the document is secured with:
 * the API client key or the token returned by client-login.
 */
class BearerSecuritySchemeTransformer implements DocumentTransformer
{
    private const string SECURITY_SCHEME = 'bearer';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->secure(
            SecurityScheme::http('bearer')
                ->as(self::SECURITY_SCHEME)
                ->setDescription('API client key, or the user token returned by `POST /api/auth/client-login`, sent as `Authorization: Bearer <token>`.'),
        );
    }
}
