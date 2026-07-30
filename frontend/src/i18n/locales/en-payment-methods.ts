/**
 * Payment methods domain (spec 0068). Sibling file so `en.ts` stays within
 * the engineering size limits (see `.claude/rules/engineering.md` §6).
 * Lookup table describing the payment methods selectable across the CRM
 * (quotes, offers, contracts, orders, invoices and future consumers),
 * cloned from `reward-statuses` with `code` (immutable after create, D-3),
 * `payment_instructions` and `payment_days` replacing `color`, and no
 * system row (no consumer exists yet, D-2).
 */

export const paymentMethods = {
  title: 'Payment Methods',
  subtitle: 'Browse, filter and manage the payment methods available across the CRM.',
  forbidden: "You don't have permission to view payment methods.",
  columns: {
    name: 'Name',
    code: 'Code',
    description: 'Description',
    payment_days: 'Payment days',
    sort_order: 'Order',
    is_active: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  advancedFilters: {
    isActive: 'Active',
    paymentDaysRange: 'Payment days',
  },
  detail: {
    title: 'Payment method details',
    subtitle: 'Read-only view of the selected payment method.',
    loadError: 'Unable to load the payment method. Please try again.',
    description: 'Description',
    payment_instructions: 'Payment instructions',
    payment_days: 'Payment days',
    sort_order: 'Order',
    is_active: 'Active',
    created_at: 'Created at',
    updated_at: 'Updated at',
  },
  form: {
    newPaymentMethod: 'New payment method',
    createTitle: 'Create payment method',
    createSubtitle: 'Add a new payment method.',
    editTitle: 'Edit payment method',
    editSubtitle: 'Update the selected payment method.',
    name: 'Name',
    code: 'Code',
    description: 'Description',
    paymentInstructions: 'Payment instructions',
    paymentDays: 'Payment days',
    isActive: 'Active',
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'Payment method created successfully.',
    updated: 'Payment method updated successfully.',
    deleted: 'Payment method deleted successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name must be at most 191 characters.',
    codeRequired: 'Code is required.',
    codeMax: 'Code must be at most 64 characters.',
    codeInvalid:
      'Code must start with a lowercase letter and contain only lowercase letters, digits and underscores.',
    descriptionMax: 'Description must be at most 500 characters.',
    paymentInstructionsMax: 'Payment instructions must be at most 5000 characters.',
    paymentDaysInvalid: 'Payment days must be a whole number.',
    paymentDaysMin: 'Payment days cannot be negative.',
    paymentDaysMax: 'Payment days must be at most 3650.',
    genericError: 'Something went wrong. Please try again.',
    deleteError: 'Unable to delete the payment method. Please try again.',
    deleteForbidden: 'You cannot delete this payment method.',
    sections: {
      identity: {
        title: 'Details',
        description: 'Name, code, description, instructions and payment terms.',
      },
    },
    hints: {
      codeLocked: 'The code cannot be changed after creation.',
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder payment methods',
    subtitle: 'Drag the payment methods to reorder them.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the payment methods. Please try again.',
    saved: 'Order updated successfully.',
    forbidden: 'You cannot reorder these payment methods.',
    genericError: 'Unable to update the order. Please try again.',
  },
}
