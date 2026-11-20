<?php

return [
    'POST /document-layouts' => [
        'summary' => 'Create a document layout',
        'description' => 'Creates a print layout for one module (quotes, invoices or purchase_orders). code (lowercase letters, digits, underscores, starting with a letter) is globally unique and immutable; name is unique within the module; config is the document structure (header, body and footer zones of typed blocks), validated against the layout schema. The first layout of a module becomes its default; is_default moves the default from the previous one. 422 when a default would be inactive. Returns 201 with the layout and the caller\'s permissions. Requires document-layouts.create.',
    ],
    'GET /document-layouts/for-select' => [
        'summary' => 'List document layouts of a module for a dropdown',
        'description' => 'Returns the active layouts of the required module (quotes, invoices, purchase_orders) as id, label (name), subtitle (code) and meta (is_default, code), default first then by name, filtered by search and paginated with offset and limit. ids[] also returns those layouts even when inactive. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /document-layouts/variables' => [
        'summary' => 'List the variables usable in a document layout',
        'description' => 'Returns, for the required module (quotes, invoices, purchase_orders), the catalogue of placeholder variables that layout blocks can use, grouped in categories: each variable has its token (e.g. {category.key}), label, type and example. Variables on fields the caller cannot see are left out. Requires document-layouts.viewAny.',
    ],
    'GET /document-layouts/{documentLayout}' => [
        'summary' => 'Get a document layout',
        'description' => 'Returns the layout (name, code, module, active and default flags, config block structure) with the metadata of its uploaded images, plus the caller\'s resource, field and action permissions on it. Requires document-layouts.view.',
    ],
    'PUT /document-layouts/{documentLayout}' => [
        'summary' => 'Update a document layout',
        'description' => 'Partially updates name, description, is_active, is_default and config (validated against the layout schema). code and module must not be sent (422). Setting is_default makes it the module default and unsets the previous one. 422 when unsetting is_default on the default layout (assign another default instead), when deactivating the default layout, or when making an inactive layout default. Returns the layout with the caller\'s permissions. Requires document-layouts.update.',
    ],
    'DELETE /document-layouts/{documentLayout}' => [
        'summary' => 'Delete a document layout',
        'description' => 'Deletes the layout and its uploaded images. 422 when it is the module default and other layouts exist in the module, or when quotes or invoices still use it (deactivate it instead). Returns 204 no content. Requires document-layouts.delete.',
    ],
    'POST /document-layouts/{documentLayout}/clone' => [
        'summary' => 'Clone a document layout',
        'description' => 'Creates a copy of the layout in the same module with a new name (unique within the module) and code (globally unique, lowercase snake format): description, active flag and config are copied, its images are duplicated and the copy\'s image blocks point to the new images. The copy is never the module default. 422 when an image file of the source is missing. Returns 201 with the new layout and the caller\'s permissions. Requires document-layouts.view on the source and document-layouts.create.',
    ],
    'GET /document-layouts/{documentLayout}/images' => [
        'summary' => 'List the images of a document layout',
        'description' => 'Returns the images uploaded to the layout (logos, letterhead), newest first: attachment_id (the id image blocks reference), filename, mime type, size and data_uri, the image content inline as a base64 data URI. Requires document-layouts.view.',
    ],
    'POST /document-layouts/{documentLayout}/images' => [
        'summary' => 'Upload an image to a document layout',
        'description' => 'Multipart upload of a JPEG or PNG file (field file) to the layout, so its image blocks can reference it by attachment_id. 422 when the layout already has 5 images. Returns 201 with the image metadata and its data URI. Requires document-layouts.update.',
    ],
    'DELETE /document-layouts/{documentLayout}/images/{attachment}' => [
        'summary' => 'Delete an image of a document layout',
        'description' => 'Removes an uploaded image from the layout. 404 when the attachment does not belong to this layout; 422 when the layout config still references it in an image block. Returns 204 no content. Requires document-layouts.update.',
    ],
    'POST /document-layouts/{documentLayout}/preview' => [
        'summary' => 'Preview a document layout as PDF',
        'description' => 'Renders the saved layout to a PDF and returns it as a file download. The document is filled with the record given by quote_id, invoice_id or purchase_order_id according to the layout module (the caller must be allowed to view it; 404 when it does not exist); without one it uses the most recent record the caller can see, or sample data. 422 when the layout config is invalid. Requires document-layouts.view.',
    ],
];
