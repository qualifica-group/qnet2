import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/** The "inspection audits" guide case of spec 0180: 5 columns + row selection + summary. */
export const TABLE_DESCRIPTOR: CustomFieldDescriptor = {
  key: 'custom.audits',
  type: 'table',
  label: 'Audits',
  group: null,
  mandatory: false,
  source: 'custom',
  config: {
    columns: [
      { key: 'audit_date', label: 'Audit date', type: 'date', required: true },
      { key: 'inspector', label: 'Inspector', type: 'text' },
      { key: 'site', label: 'Site', type: 'integer' },
      {
        key: 'stage',
        label: 'Stage',
        type: 'enum',
        options: [
          { value: 'stage_1', label: 'Stage 1' },
          { value: 'stage_2', label: 'Stage 2' },
        ],
      },
      { key: 'alert_sent', label: 'Alert sent', type: 'boolean' },
    ],
    selectable: { key: 'active', label: 'Active' },
    summary: { column: 'audit_date', strategy: 'selected' },
    min_rows: 0,
    max_rows: 5,
  },
}
