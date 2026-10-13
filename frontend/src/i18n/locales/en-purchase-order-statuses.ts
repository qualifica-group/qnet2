/** ODA statuses catalog (spec 0248). Root namespace: the backend column labels are `purchaseOrderStatuses.columns.*`. */

export const purchaseOrderStatuses = {
  forbidden: 'You don’t have permission to view ODA statuses.',
  columns: {
    system_key: 'System value',
    name: 'Name',
    color: 'Color',
    icon: 'Icon',
    sort_order: 'Order',
  },
  detail: {
    loadError: 'Unable to load the ODA status. Please retry.',
    systemKey: 'System value',
    sort_order: 'Order',
  },
  form: {
    name: 'Name',
    color: 'Color',
    icon: 'Icon',
    updated: 'ODA status updated successfully.',
    nameRequired: 'The name is required.',
    nameMax: 'The name can be at most 191 characters long.',
    colorRequired: 'The color is required.',
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
    title: 'Reorder ODA statuses',
    subtitle: 'Drag the rows to change their order. The order is reflected on the tabs, the table and the status change dialog.',
    dragHandleLabel: 'Drag to reorder',
    loadError: 'Unable to load the list. Please retry.',
    saved: 'Order updated successfully.',
    forbidden: 'You can’t reorder these rows.',
    genericError: 'Unable to update the order. Please retry.',
  },
}
