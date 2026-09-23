import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildColDefs } from '@/components/data-table/column-def-builder'
import { ACTIONS_COLUMN_ID } from '@/components/data-table/data-table-overlays'

const t = ((key: string) => key) as unknown as TFunction

function actionsWidth(options: { actionsColumnHasOverflow?: boolean; actionsColumnWidth?: number }) {
  const defs = buildColDefs({
    domain: 'notifications',
    columns: [],
    renderRowActions: () => null,
    t,
    ...options,
  })
  return defs.find((def) => def.colId === ACTIONS_COLUMN_ID)?.width
}

describe('buildColDefs actions column width', () => {
  it('keeps the narrow default, widened only for the overflow button', () => {
    expect(actionsWidth({})).toBe(100)
    expect(actionsWidth({ actionsColumnHasOverflow: true })).toBe(120)
  })

  it('uses an explicit width (labeled row actions, spec 0150 D-8) over the default', () => {
    expect(actionsWidth({ actionsColumnWidth: 190, actionsColumnHasOverflow: true })).toBe(190)
  })
})
