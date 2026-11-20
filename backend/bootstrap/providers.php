<?php

use App\Providers\ActivitylogServiceProvider;
use App\Providers\ApiClientsServiceProvider;
use App\Providers\ApiDocsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PermissionServiceProvider;
use Integrations\Formalab\FormalabServiceProvider;

return [
    ActivitylogServiceProvider::class,
    PermissionServiceProvider::class,
    AppServiceProvider::class,
    ApiClientsServiceProvider::class,
    ApiDocsServiceProvider::class,
    FormalabServiceProvider::class,
];
