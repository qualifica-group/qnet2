<?php

return [

    'modules' => [
        'quotes' => 'Quotes',
        'invoices' => 'Invoices',
    ],

    // Business messages (D-7, spec 0069): thrown by
    // App\Services\DocumentLayouts\DocumentLayoutDefaultManager as a 422
    // ValidationException keyed on `is_active`/`is_default`.
    'default_requires_active' => 'A default layout must be active.',
    'default_cannot_be_deactivated' => 'The default layout cannot be deactivated: designate another layout as default first.',
    'default_must_be_reassigned' => 'The default layout cannot be unset directly: designate another layout as default first.',
    'default_cannot_be_deleted' => 'The default layout cannot be deleted while other layouts exist in the same module: designate another layout as default first.',

    // Usage guard (D-7, spec 0070): thrown by App\Services\DocumentLayoutService
    // as a 422 ValidationException when a layout is referenced by at least
    // one Quote, keyed on `quotes`.
    'layout_in_use' => 'This layout is used by :count quotes: you can only deactivate it.',

    'invoice_no_layout_available' => 'No document layout available for invoices.',

    // Image guard (spec 0069, MT-4).
    'image_in_use' => 'This image is referenced by a block in the current config: remove the block before deleting it.',

    'variables' => [
        'categories' => [
            'quote' => 'Quote',
            'totals' => 'Totals',
            'client' => 'Client',
            'opportunity' => 'Opportunity',
            'referent' => 'Referent',
            'commercial' => 'Sales rep',
            'reporter' => 'Reporter',
            'supervisor' => 'Supervisor',
            'company' => 'Company',
            'company_site' => 'Company site',
            'operational_site' => 'Operational site',
            'custom_fields' => 'Custom fields',
            'quote_attributes' => 'Quote attributes',
            'invoice' => 'Invoice',
            'customer' => 'Customer',
            'payment' => 'Payment',
            'work_order' => 'Work order',
            'document' => 'Document',
        ],

        'quote' => [
            'code' => 'Quote code',
            'title' => 'Title',
            'internal_notes' => 'Internal notes',
            'status_name' => 'Status',
            'created_at' => 'Created at',
            'updated_at' => 'Last updated at',
        ],

        'totals' => [
            'revenue_net' => 'Revenue (net)',
            'revenue_vat' => 'Revenue VAT',
            'revenue_gross' => 'Revenue (gross)',
            'cost_net' => 'Cost (net)',
            'cost_vat' => 'Cost VAT',
            'cost_gross' => 'Cost (gross)',
            'margin_net' => 'Net margin',
            'net' => 'Net total',
            'vat' => 'VAT',
            'total' => 'Total',
            'collected' => 'Collected',
            'residual' => 'Residual',
        ],

        'client' => [
            'name' => 'Name',
            'full_name' => 'Full name',
            'type' => 'Type',
            'vat_number' => 'VAT number',
            'tax_code' => 'Tax code',
            'sdi_code' => 'SDI code',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'address_line1' => 'Address (street)',
            'address_postal_code' => 'Postal code',
            'address_city' => 'City',
            'address_province' => 'Province',
            'address_state' => 'State',
            'address_country' => 'Country',
        ],

        'opportunity' => [
            'name' => 'Opportunity name',
            'status_name' => 'Status',
            'estimated_value' => 'Estimated value',
            'expected_close_date' => 'Expected close date',
            'start_date' => 'Start date',
            'general_notes' => 'General notes',
        ],

        'referent' => [
            'name' => 'Name',
            'type_name' => 'Referent type',
            'email' => 'Email',
            'phone' => 'Phone',
        ],

        'commercial' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
        ],

        'reporter' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
        ],

        'supervisor' => [
            'name' => 'Name',
            'email' => 'Email',
        ],

        'company' => [
            'denomination' => 'Legal name',
            'vat_number' => 'VAT number',
            'address' => 'Address',
            'address_city' => 'City',
            'address_postal_code' => 'Postal code',
            'name' => 'Name',
        ],

        'company_site' => [
            'name' => 'Site name',
            'bank_name' => 'Bank',
            'bank_iban' => 'IBAN',
            'address' => 'Address',
            'address_city' => 'City',
            'address_postal_code' => 'Postal code',
        ],

        'operational_site' => [
            'label' => 'Operational site',
        ],

        'invoice' => [
            'number_label' => 'Number',
            'type_label' => 'Document type',
            'document_date' => 'Document date',
            'external_number' => 'External number',
            'external_date' => 'External date',
            'notes' => 'Notes',
            'tag_label' => 'Estimate/final',
        ],

        'customer' => [
            'name' => 'Name',
            'address' => 'Address',
            'vat_number' => 'VAT number',
            'tax_code' => 'Tax code',
            'sdi_code' => 'SDI code',
            'pec' => 'PEC',
            'email' => 'Email',
        ],

        'payment' => [
            'method_name' => 'Payment method',
            'payment_instructions' => 'Payment instructions',
            'bank_name' => 'Bank',
            'iban' => 'IBAN',
        ],

        'work_order' => [
            'code' => 'Work order code',
            'title' => 'Title',
        ],

        'document' => [
            'generated_at' => 'Generated at',
            'generated_by' => 'Generated by',
        ],
    ],

    'installment_status' => [
        'unpaid' => 'Not collected',
        'paid' => 'Collected',
    ],

];
