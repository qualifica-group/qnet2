/** Revenue classification (spec 0350): shared sub-account picker, product/category field, invoice line, bulk action. */

export const revenueTypes = {
  unclassified: 'To be classified',
  select: {
    label: 'Revenue sub-account',
    placeholder: 'Select a sub-account…',
    search: 'Search by code or name…',
    empty: 'No sub-accounts found.',
    error: 'Unable to load the sub-accounts.',
    clear: 'Clear the sub-account',
  },
  effective: {
    none: 'No sub-account set: invoice lines stay to be classified.',
    inherited: 'Inherited from category {{category}}: {{revenueType}}',
  },
  field: {
    label: 'Revenue sub-account',
    productHint: 'Proposed on the invoice lines of this product.',
  },
  bulk: {
    bulkAction: 'Set revenue sub-account',
    title: 'Set revenue sub-account',
    description: 'The chosen revenue sub-account will be set on all {{count}} selected products.',
    selected: 'Selected products',
    none: 'None (remove the sub-account)',
    confirm: 'Apply to {{count}} products',
    done: 'Revenue sub-account updated on {{count}} product(s).',
    error: 'Unable to set the revenue sub-account. No product was changed.',
  },
  invoiceLine: {
    automatic: 'Automatic (proposed on save)',
    column: 'Revenue sub-account',
    change: 'Change the revenue sub-account of line {{line}}',
    saved: 'Revenue sub-account updated.',
    error: 'Unable to update the revenue sub-account. Try again.',
    hint: 'Management data only: it does not appear on the document.',
  },
  commissionDefault: {
    label: 'Default for Entity commissions',
    hint: 'Invoice lines for Entity commission requests get this sub-account. Only one sub-account can be the default.',
    badge: 'Entity commissions',
  },
}
