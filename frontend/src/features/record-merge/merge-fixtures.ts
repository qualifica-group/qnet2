import type { MergePreview } from '@/features/record-merge/types'

/** Two registries: record 1 is the suggested survivor; record 2 carries the extra data. */
export const PREVIEW_FIXTURE: MergePreview = {
  suggested_survivor_id: 1,
  records: [
    { id: 1, name: 'Acme Srl', old_id: 10, created_at: '2024-01-05T10:00:00Z' },
    { id: 2, name: 'Acme Beta', old_id: null, created_at: '2025-02-06T10:00:00Z' },
  ],
  fields: [
    {
      key: 'company_name',
      type: 'text',
      combinable: false,
      values: [
        { record_id: 1, value: 'Acme Srl', display: null },
        { record_id: 2, value: 'Acme Beta Srl', display: null },
      ],
    },
    {
      key: 'vat_number',
      type: 'text',
      combinable: false,
      values: [
        { record_id: 1, value: '01234567890', display: null },
        { record_id: 2, value: '01234567890', display: null },
      ],
    },
    {
      key: 'sdi_code',
      type: 'text',
      combinable: false,
      values: [
        { record_id: 1, value: null, display: null },
        { record_id: 2, value: 'ABC1234', display: null },
      ],
    },
    {
      key: 'is_supplier',
      type: 'boolean',
      combinable: false,
      values: [
        { record_id: 1, value: true, display: null },
        { record_id: 2, value: false, display: null },
      ],
    },
    {
      key: 'general_notes',
      type: 'text',
      combinable: true,
      values: [
        { record_id: 1, value: 'Note uno', display: null },
        { record_id: 2, value: 'Note due', display: null },
      ],
    },
  ],
  collections: [
    {
      key: 'contacts',
      items: [
        { id: 5, record_id: 1, label: 'own@acme.it', detail: null, match_key: 'email|own@acme.it' },
        { id: 11, record_id: 2, label: 'sales@acme.it', detail: null, match_key: 'email|sales@acme.it' },
        { id: 12, record_id: 2, label: 'own@acme.it', detail: null, match_key: 'email|own@acme.it' },
      ],
    },
  ],
  linked: [
    {
      key: 'opportunities',
      count: 4,
      counts: [
        { record_id: 1, count: 1 },
        { record_id: 2, count: 3 },
      ],
      items: [
        { id: 99, record_id: 1, label: 'Opportunity Zero' },
        { id: 100, record_id: 2, label: 'Opportunity Alpha' },
        { id: 101, record_id: 2, label: 'Opportunity Beta' },
      ],
    },
  ],
}
