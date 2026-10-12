/**
 * Record-level actions of the ODA (spec 0210 MT-11): change status, merge,
 * merge history, PDF and send to the supplier. Side file of `en.ts`.
 */

export const purchaseOrderActions = {
  buttons: {
    status: 'Change status',
    merge: 'Merge',
    history: 'Merge history',
    pdf: 'Print PDF',
    send: 'Send to supplier',
  },
  tooltips: {
    status: 'Move the order to another status',
    merge: 'Move lines of other orders of the same supplier into this one',
    history: 'Orders merged into this one',
    pdf: 'Download the order as PDF',
    send: 'Compose the email to the supplier with the PDF attached',
  },
  status: {
    title: 'Change status',
    subtitle: 'ODA {{code}}',
    current: 'Current status',
    newStatus: 'New status',
    none: 'No status change is available for this order with your permissions.',
    confirm: 'Change status',
    done: 'Status changed to {{status}}.',
    error: 'Unable to change the status. Please try again.',
  },
  merge: {
    title: 'Merge purchase orders',
    subtitle: 'Move lines of other orders of the same supplier and company into ODA {{code}}.',
    loading: 'Loading orders',
    loadError: 'Unable to load the orders that can be merged.',
    empty: 'There are no other draft or in-validation orders of this supplier and company.',
    unsavedChanges: 'Save the changes to the order before merging: the merge takes effect immediately.',
    emptiedWarning: 'Orders left without lines are deleted.',
    selectOrder: 'Select all lines of order {{code}}',
    selectLine: 'Select line {{description}}',
    lineCount_one: '{{count}} line',
    lineCount_other: '{{count}} lines',
    confirm_one: 'Merge {{count}} line',
    confirm_other: 'Merge {{count}} lines',
    done_one: '{{count}} line merged.',
    done_other: '{{count}} lines merged.',
    deleted_one: '{{count}} emptied order was deleted.',
    deleted_other: '{{count}} emptied orders were deleted.',
    error: 'Unable to merge the lines. Please try again.',
  },
  history: {
    title: 'Merge history',
    subtitle: 'Orders merged into ODA {{code}}, most recent first.',
    loadError: 'Unable to load the merge history.',
    empty: 'No merge has been performed on this order.',
    actions: {
      deleted: 'Order deleted',
      partial: 'Partial merge',
    },
    movedLines: 'Moved lines',
    by: 'by {{name}}',
    movedTotal: 'Moved total',
  },
  pdf: {
    ready: 'PDF generated.',
    forbidden: 'You cannot download this PDF.',
    error: 'Unable to generate the PDF. Please try again.',
  },
  send: {
    error: 'Unable to prepare the email. Please try again.',
  },
}
