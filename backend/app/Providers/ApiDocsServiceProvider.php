<?php

namespace App\Providers;

use App\Services\ApiDocs\Docs\ApiGuideTransformer;
use App\Services\ApiDocs\Docs\ApiModuleTagsTransformer;
use App\Services\ApiDocs\Docs\ApiOperationDescriptionsTransformer;
use App\Services\ApiDocs\Docs\BearerSecuritySchemeTransformer;
use App\Services\ApiDocs\Infer\ApiEnvelopeReturnTypeExtension;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the API documentation transformers and the envelope inference into Scramble (specs 0209, 0210).
 * Scramble itself only generates the document: the public /docs/api UI stays
 * disabled: the document is served by an admin endpoint.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    /**
     * register(), not boot(): Scramble snapshots its configuration while its
     * own provider boots, after every provider has registered.
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();

        Scramble::registerExtension(ApiEnvelopeReturnTypeExtension::class);

        Scramble::configure()->withDocumentTransformers([
            BearerSecuritySchemeTransformer::class,
            ApiGuideTransformer::class,
            ApiOperationDescriptionsTransformer::class,
        ]);

        // Scramble appends its own AddDocumentTags while booting, which replaces
        // the document tags: the module tags must be written after it.
        $this->app->booted(static fn () => Scramble::configure()->withDocumentTransformers(ApiModuleTagsTransformer::class));
    }
}
