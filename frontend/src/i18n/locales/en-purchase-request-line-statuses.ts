/** RDA line statuses catalog (spec 0248). Root namespace: the backend column labels are `purchaseRequestLineStatuses.columns.*`. */

export const purchaseRequestLineStatuses = {
  forbidden: 'You don’t have permission to view RDA line statuses.',
  columns: {
    name: 'Name',
    system_key: 'System key',
    color: 'Color',
    icon: 'Icon',
    sort_order: 'Order',
  },
  detail: {
    title: 'RDA line status details',
    subtitle: 'Details of the selected record, editable in place.',
    loadError: 'Unable to load the RDA line status. Please retry.',
    system_key: 'System key',
    sort_order: 'Order',
  },
  form: {
    name: 'Name',
    color: 'Color',
    icon: 'Icon',
    updated: 'RDA line status updated successfully.',
    nameRequired: 'Name is required.',
    nameMax: 'Name can be at most 191 characters long.',
    colorRequired: 'Color is required.',
    colorInvalid: 'Pick a color from the palette.',
    iconInvalid: 'Pick an icon from the catalog.',
    genericError: 'Something went wrong. Please retry.',
    sections: {
      identity: {
        title: 'Details',
      },
    },
  },
  reorder: {
    openButton: 'Reorder',
    title: 'Reorder RDA line statuses',
    subtitle: 'Drag the rows to change their order. The order is reflected in the status tabs, the change status dialog and the menus.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the list. Please retry.',
    saved: 'Order updated successfully.',
    genericError: 'Unable to update the order. Please retry.',
    inactiveBadge: 'Inactive',
  },
}
