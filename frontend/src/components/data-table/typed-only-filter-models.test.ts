import { describe, expect, it } from 'vitest'
import { toTypedOnlyFilterModels } from '@/components/data-table/typed-only-filter-models'
import type { TableColumn } from '@/features/table/types'

function column(id: string, tooManyFilterValues?: boolean): TableColumn {
  return { id, label: id, type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true, tooManyFilterValues }
}

const containsCode = { filterType: 'text', type: 'contains', filter: 'COM-1' }

describe('toTypedOnlyFilterModels', () => {
  it('keeps the condition of a checklist + condition model on a column with too many values', () => {
    const model = { code: { filterType: 'multi', filterModels: [{ filterType: 'set', values: ['COM-1'] }, containsCode] } }

    expect(toTypedOnlyFilterModels(model, [column('code', true)])).toEqual({ code: containsCode })
  })

  it('drops a checklist-only model on a column with too many values', () => {
    const model = { code: { filterType: 'multi', filterModels: [{ filterType: 'set', values: ['COM-1'] }, null] } }

    expect(toTypedOnlyFilterModels(model, [column('code', true)])).toEqual({})
  })

  it('leaves a model already in condition form and the other columns unchanged', () => {
    const multi = { filterType: 'multi', filterModels: [{ filterType: 'set', values: ['Rossi'] }, null] }
    const model = { code: containsCode, name: multi }

    expect(toTypedOnlyFilterModels(model, [column('code', true), column('name')])).toEqual(model)
  })
})
