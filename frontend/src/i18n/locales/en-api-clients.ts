/** English labels (`apiClients.*`) of the `api-clients` table definition (spec 0209). */
export const apiClients = {
  columns: {
    id: 'ID',
    name: 'Name',
    is_active: 'Active',
    expires_at: 'Key expiry',
    key_last_four: 'Key ends with',
    last_used_at: 'Last used',
    created_by: 'Created by',
    created_at: 'Created at',
  },
  actions: {
    rotate_key: 'Rotate key',
  },
}
