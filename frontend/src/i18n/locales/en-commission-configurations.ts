/**
 * Table columns and advanced filters are backend-driven: the server sends the
 * i18n KEY (`commissionConfigurations.columns.*` /
 * `commissionConfigurations.advancedFilters.*`, see
 * `CommissionConfigurationColumnCatalog`) and the frontend translates it — the
 * keys below are mandatory and must match the column ids.
 */
export const commissionConfigurations = {
  forbidden: "You don't have permission to view commission configurations.",
  columns: {
    name: 'Configuration name', recipient_role: 'Recipient role', application_scope: 'Scope',
    category: 'Product category', product: 'Product', commission_type: 'Type',
    value: 'Value', priority: 'Priority', status: 'Status', updated_at: 'Last updated',
    recipient: 'Recipient',
  },
  advancedFilters: { name: 'Configuration name', validFrom: 'Valid from', validUntil: 'Valid until' },
  detail: { title: 'Commission configuration', loadError: 'Unable to load the commission configuration.', scope: 'Recipient and scope', calculation: 'Calculation', validity: 'Validity and notes', updated_at: 'Last updated' },
  form: {
    new: 'New configuration', save: 'Save', saving: 'Saving…',
    created: 'Commission configuration created.', updated: 'Commission configuration updated.', deleted: 'Commission configuration deleted.',
    genericError: 'Unable to save the commission configuration.', deleteError: 'Unable to delete the commission configuration.',
    deleteReferenced: 'This configuration is used by another module and cannot be deleted.',
    name: 'Configuration name', recipient_role: 'Recipient role', application_scope: 'Application scope',
    product_category_id: 'Product category', product_id: 'Product', recipient_type: 'Recipient type', recipient_id: 'Recipient', commission_type: 'Commission type',
    value: 'Commission value', priority: 'Rule priority', valid_from: 'Valid from', valid_until: 'Valid until',
    status: 'Status', internal_note: 'Internal service note', searchCategory: 'Search product categories…',
    searchProduct: 'Search products…', searchRecipient: 'Search recipient…', selectPlaceholder: 'Select…', selectEmpty: 'No results found.', selectError: 'Unable to load options.',
    sections: { scope: 'Identity and scope', calculation: 'Calculation', validity: 'Validity', notes: 'Internal note' },
    hints: { priority: 'Decides which rule wins when several valid configurations compete for the same role and the same scope: the highest number wins, then the most recent start date. A Product rule always beats a Category rule regardless.' },
    errors: { nameRequired: 'Configuration name is required.', valueInvalid: 'Commission value must be zero or greater.', priorityInvalid: 'Priority must be a whole number.', validFromRequired: 'Start date is required.', categoryRequired: 'Select a product category.', productRequired: 'Select a product.', recipientRequired: 'Select a recipient.', validUntilInvalid: 'End date cannot precede start date.', recipientTypeInvalid: 'This recipient type is not allowed for the selected role.' },
  },
  options: {
    recipient_role: { COMMERCIAL: 'Commercial', REPORTER: 'Reporter', SUPERVISOR: 'Supervisor', SUPPLIER: 'Supplier' },
    application_scope: { PRODUCT_CATEGORY: 'Product category', PRODUCT: 'Product', RECIPIENT: 'Specific recipient' },
    commission_type: { FIXED_AMOUNT: 'Fixed amount', PERCENTAGE: 'Percentage' },
    status: { ACTIVE: 'Active', SUSPENDED: 'Suspended' },
    recipient_type: { referent: 'Referent', user: 'User', registry: 'Registry' },
  },
}
