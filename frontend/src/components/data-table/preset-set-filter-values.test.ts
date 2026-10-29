import type { GridApi } from 'ag-grid-community'
import { describe, expect, it, vi } from 'vitest'
import {
  registerPresetSetFilterValues,
  withPresetSetFilterValues,
} from '@/components/data-table/preset-set-filter-values'

function stubApi(initialFilterModel?: Record<string, unknown>, refreshFilterValues = vi.fn()) {
  const api = {
    getGridOption: (key: string) =>
      key === 'initialState' && initialFilterModel ? { filter: { filterModel: initialFilterModel } } : undefined,
    getColumnFilterHandler: vi.fn(() => ({ refreshFilterValues })),
  }
  return { api: api as unknown as GridApi, handlerLookup: api.getColumnFilterHandler, refreshFilterValues }
}

describe('withPresetSetFilterValues', () => {
  it('returns the server list unchanged when nothing is preset', () => {
    const { api } = stubApi()
    const values = ['proforma']

    expect(withPresetSetFilterValues(api, 'type', values)).toBe(values)
  })

  it('keeps a value written through the handle even when the scoped list lacks it', () => {
    const { api } = stubApi()
    registerPresetSetFilterValues(api, { type: { filterType: 'set', values: ['invoice'] } })

    expect(withPresetSetFilterValues(api, 'type', ['proforma'])).toEqual(['proforma', 'invoice'])
  })

  it('keeps the values of the model restored at grid creation', () => {
    const { api } = stubApi({ document_year: { filterType: 'set', values: [2026] } })

    expect(withPresetSetFilterValues(api, 'document_year', [])).toEqual(['2026'])
  })

  it('drops a column preset once the handle clears that column', () => {
    const { api } = stubApi()
    registerPresetSetFilterValues(api, { type: { filterType: 'set', values: ['invoice'] } })
    registerPresetSetFilterValues(api, { type: null })

    expect(withPresetSetFilterValues(api, 'type', ['proforma'])).toEqual(['proforma'])
  })
})

describe('registerPresetSetFilterValues', () => {
  it('reloads the value list of the patched set columns only', () => {
    const { api, handlerLookup, refreshFilterValues } = stubApi()

    registerPresetSetFilterValues(api, {
      type: { filterType: 'set', values: ['invoice'] },
      document_month: null,
      customer: { filterType: 'text', type: 'contains', filter: 'x' },
    })

    expect(handlerLookup).toHaveBeenCalledTimes(1)
    expect(handlerLookup).toHaveBeenCalledWith('type')
    expect(refreshFilterValues).toHaveBeenCalledTimes(1)
  })

  it('tolerates a column whose filter is not built yet', () => {
    const { api, handlerLookup } = stubApi()
    handlerLookup.mockReturnValueOnce(undefined as never)

    expect(() => registerPresetSetFilterValues(api, { type: { filterType: 'set', values: ['invoice'] } })).not.toThrow()
  })
})
