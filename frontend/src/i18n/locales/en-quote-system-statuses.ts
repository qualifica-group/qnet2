/**
 * Quote system statuses domain (spec 0404, part A). System statuses are what
 * Offerte shows: configurable, global, linked by the working statuses of the
 * quote workflows. The `behavior` is the key the code knows (contract on Won,
 * Gestione Iscritti on Provisionally won/Won).
 */

export const quoteSystemStatuses = {
  title: 'Quote system statuses',
  subtitle: 'The statuses shown in Quotes, linked to the working statuses.',
  forbidden: "You don't have permission to view quote system statuses.",
  // Accessible name of the status tabs of Offerte and Opportunita' (spec 0409).
  tabsLabel: 'System statuses',
  columns: {
    name: 'Name',
    color: 'Color',
    behavior: 'Behavior',
    workflowStatusesCount: 'Linked working statuses',
    sortOrder: 'Order',
    createdAt: 'Created at',
  },
  advancedFilters: {
    name: 'Name',
    behavior: 'Behavior',
    createdRange: 'Created at',
  },
  detail: {
    title: 'System status detail',
    loadError: 'Unable to load the system status. Try again.',
    workflowStatusesCount: 'Linked working statuses',
    sortOrder: 'Order',
    createdAt: 'Created at',
  },
  form: {
    newQuoteSystemStatus: 'New status',
    createTitle: 'Create system status',
    createSubtitle: 'Add a status that will show up in Quotes.',
    name: 'Name',
    color: 'Color',
    behavior: {
      label: 'Behavior',
      hint: 'Decides what the status does: on Won it creates the contract, on Provisionally won and Won it shows in Enrollee Management. On the mandatory statuses (Open, Won, Lost) it is fixed.',
      open: 'Open',
      pending: 'Pending',
      validated: 'Provisionally won',
      closed_won: 'Won',
      closed_lost: 'Lost',
    },
    save: 'Save',
    saving: 'Saving…',
    cancel: 'Cancel',
    created: 'System status created successfully.',
    updated: 'System status updated successfully.',
    deleted: 'System status deleted successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 100 characters.',
    colorRequired: 'The color is required.',
    colorMax: 'The color can be at most 32 characters.',
    genericError: 'Something went wrong. Try again.',
    deleteError: 'Unable to delete the system status. Try again.',
    leaveConfirm: {
      title: 'Leave without saving?',
      description: 'The record has not been created yet: the data you entered will be lost.',
      confirm: 'Leave without saving',
      cancel: 'Keep editing',
    },
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder system statuses',
    subtitle: 'Drag the statuses to reorder them. "Open" stays first and "Won"/"Lost" stay last.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the statuses. Try again.',
    saved: 'Order updated successfully.',
    genericError: 'Unable to update the order. Try again.',
  },
}
