/**
 * Field change requests domain (spec 0078): the generic "propose a change to
 * a protected field, approve/reject it" system — today's only protected
 * field is request-management's "Fonte" (source), but the dialog, the
 * record's change-requests section and this dedicated browse are all
 * resource/field-agnostic (AC-054). Sibling file so `en.ts` stays within the
 * engineering size limits (see `.claude/rules/engineering.md` §6).
 *
 * `columns.*` mirror the 12 frozen columns of
 * `FieldChangeRequestColumnCatalog` (backend, spec 0078 `data_contract`) —
 * the backend sends these keys verbatim as column `label`s, resolved here.
 */

export const fieldChangeRequests = {
  forbidden: "You don't have permission to view field change requests.",
  proposeFromPicker: 'Pick a value to propose a change; it needs approval before it takes effect.',
  columns: {
    resource: 'Module',
    subject: 'Record',
    field: 'Field',
    currentValue: 'Current value',
    requestedValue: 'Requested value',
    reason: 'Reason',
    requestedBy: 'Requester',
    createdAt: 'Requested at',
    status: 'Status',
    handledBy: 'Handled by',
    handledAt: 'Handled at',
    handlingNote: 'Note',
  },
  section: {
    title: 'Change requests',
    description: 'Proposals awaiting approval on this record.',
    empty: 'No change request awaiting a decision on this record.',
    emptyValue: 'None',
    loadError: 'Unable to load the change requests. Please try again.',
  },
  detail: {
    record: 'Record',
    field: 'Field',
    currentValue: 'Current value',
    requestedValue: 'Requested value',
    reason: 'Reason',
    requestedBy: 'Requester',
    requestedAt: 'Requested at',
    handledBy: 'Handled by',
    handledAt: 'Handled at',
    handlingNote: 'Note',
    loadError: 'Unable to load the request. Please try again.',
    actions: {
      genericError: 'Something went wrong. Please try again.',
      approved: 'Request approved.',
      rejected: 'Request rejected.',
      approve: 'Approve',
      reject: 'Reject',
      approveTitle: 'Approve the request?',
      rejectTitle: 'Reject the request?',
      approveDescription: 'The proposed change will be applied. You can add an optional note.',
      rejectDescription: 'The proposed change will not be applied. You can add an optional note.',
      noteLabel: 'Note',
      submitting: 'Saving…',
      confirm: 'Confirm',
    },
  },
  dialog: {
    genericError: 'Something went wrong. Please try again.',
    success: 'Change request sent.',
    title: 'Propose a change to {{field}}',
    description: 'Pick a new value for {{field}}; it needs approval before it takes effect.',
    current: 'Current',
    requested: 'Requested',
    emptyValue: 'None',
    reasonLabel: 'Why do you want to change {{field}}?',
    reasonMax: 'Reason must be at most 1000 characters.',
    cancel: 'Cancel',
    submitting: 'Sending…',
    submit: 'Send request',
  },
}
