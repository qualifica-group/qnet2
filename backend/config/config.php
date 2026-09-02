<?php

use App\Enums\AgreementStatusEnum;
use App\Enums\ContactTypeEnum;
use App\Enums\DocumentLayoutModule;
use App\Enums\GenderEnum;
use App\Enums\LocaleEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ProductType;
use App\Enums\ReferentContactScopeEnum;
use App\Enums\SiteTypeEnum;
use App\Enums\SizeClassEnum;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;

return [

    /*
    |--------------------------------------------------------------------------
    | Public application bootstrap config (GET /api/config)
    |--------------------------------------------------------------------------
    |
    | Server-side allowlist of the metadata exposed by the PUBLIC bootstrap
    | endpoint GET /api/config (outside auth:sanctum). ConfigService iterates
    | this list and serializes each enum's presentation options for the client.
    |
    | SECURITY — THIS ENDPOINT IS PUBLIC (UNAUTHENTICATED).
    |     - Only add NON-SENSITIVE presentation metadata here.
    |     - Never add anything user-, tenant-, or permission-scoped, nor any
    |       value that leaks internal structure beyond what the login screen
    |       legitimately needs before authentication.
    |     - This is a fixed server-side list, never built from request input:
    |       no reflection over user input is performed downstream.
    |     - ANY addition to this file REQUIRES a Security Agent review.
    |
    | Shape:
    |   'form_enums' => [ '<snake_case key>' => <BackedEnum>::class, ... ]
    | The key becomes the JSON key under data.enums; the value is the enum FQCN
    | whose ::options() (minus #[HiddenOnForm] cases) is serialized.
    |
    */

    'form_enums' => [
        // Supported UI/user locales (NON-SENSITIVE): the login screen and the
        // user/profile forms render their language select from this list instead
        // of hardcoding it on the frontend.
        'locale' => LocaleEnum::class,
        'personal_data_type' => PersonalDataTypeEnum::class,
        // Biological sex of a natural-person card (NON-SENSITIVE presentation
        // metadata: the male/female option list, not any person's value).
        'gender' => GenderEnum::class,
        'contact_type' => ContactTypeEnum::class,
        'notification_level' => NotificationLevelEnum::class,
        // Referent contact scope (spec 0016): internal/external classification
        // shown on the referent form's "Contact scope" select.
        'referent_contact_scope' => ReferentContactScopeEnum::class,
        // Product classification (spec 0017): the products table's
        // `product_type` badge. SERVICE-only for now.
        'product_type' => ProductType::class,
        // Address site type (spec 0020): shared `addresses.site_type` column,
        // rendered only by the Registries form (showSiteType opt-in).
        'site_type' => SiteTypeEnum::class,
        // Registry commercial agreement status (spec 0020).
        'agreement_status' => AgreementStatusEnum::class,
        // Registry size class (spec 0020).
        'size_class' => SizeClassEnum::class,
        // Document layout consumer module (spec 0069): populates the option
        // list of the `module` advanced filter on the layouts grid, which
        // declares `enumKey: document_layout_module`.
        'document_layout_module' => DocumentLayoutModule::class,
        // Work order type (spec 0093, D-10): the create form's `type` select
        // and the work-orders grid's `type` badge, which declares
        // `enumKey: work_order_type`.
        'work_order_type' => WorkOrderType::class,
        // Work order computed status (spec 0093, D-3): never a form field —
        // registered so the work-orders grid's `status` badge, which
        // declares `enumKey: work_order_status`, has a catalogue to read.
        'work_order_status' => WorkOrderStatus::class,
    ],

];
