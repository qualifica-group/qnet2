import { waitFor } from '@testing-library/react'
import type { SetFilterValuesFuncParams } from 'ag-grid-community'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  buildColumnFilter,
  createColumnValuesGetter,
  resolveFilter,
} from '@/components/data-table/column-filters'
import { fetchTableColumnValues } from '@/features/table/api'
import type { TableColumn } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableColumnValues: vi.fn(),
}))

const fetchValuesMock = vi.mocked(fetchTableColumnValues)

/** Minimal TableColumn stub; only the fields `resolveFilter` reads matter. */
function stubColumn(
  partial: Partial<TableColumn> & Pick<TableColumn, 'id' | 'type'>,
): TableColumn {
  return {
    label: 'label',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...partial,
  }
}

describe('resolveFilter', () => {
  it('returns false for a non-filterable column', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'text', filterable: false })),
    ).toBe(false)
  })

  it.each(['text', 'number', 'date'] as const)(
    'returns agMultiColumnFilter for filterType "%s"',
    (filterType) => {
      expect(resolveFilter(stubColumn({ id: 'x', type: 'text', filterType }))).toBe(
        'agMultiColumnFilter',
      )
    },
  )

  it.each(['set', 'boolean'] as const)(
    'returns agSetColumnFilter for filterType "%s"',
    (filterType) => {
      expect(resolveFilter(stubColumn({ id: 'x', type: 'text', filterType }))).toBe(
        'agSetColumnFilter',
      )
    },
  )

  it.each(['enum', 'tags', 'badge', 'boolean'] as const)(
    'falls back to agSetColumnFilter for column type "%s" without an explicit filterType',
    (type) => {
      expect(resolveFilter(stubColumn({ id: 'x', type }))).toBe('agSetColumnFilter')
    },
  )

  it.each(['text', 'number', 'datetime'] as const)(
    'falls back to agMultiColumnFilter for column type "%s" without an explicit filterType',
    (type) => {
      expect(resolveFilter(stubColumn({ id: 'x', type }))).toBe('agMultiColumnFilter')
    },
  )

  it('still returns agMultiColumnFilter when hasFilterValues is explicitly true', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'text', hasFilterValues: true })),
    ).toBe('agMultiColumnFilter')
  })

  // 0005 (AC-018): computed/derived columns have no queryable value list, so a
  // Set Filter attached to them would call /values and fail or stay empty.
  it.each([
    ['text', 'agTextColumnFilter'],
    ['number', 'agNumberColumnFilter'],
    ['date', 'agDateColumnFilter'],
  ] as const)(
    'falls back to the plain %s condition filter when hasFilterValues is false',
    (filterType, expected) => {
      expect(
        resolveFilter(
          stubColumn({ id: 'x', type: 'text', filterType, hasFilterValues: false }),
        ),
      ).toBe(expected)
    },
  )

  it('ignores hasFilterValues for set/enum/boolean columns (always agSetColumnFilter)', () => {
    expect(
      resolveFilter(stubColumn({ id: 'x', type: 'enum', hasFilterValues: false })),
    ).toBe('agSetColumnFilter')
  })

  // AC-024: universal custom fields (0021) carry a dynamic id (`custom.<key>`)
  // but resolveFilter only ever looks at type/filterType, so it already routes
  // them correctly with zero code changes — this locks that in.
  describe('source: "custom" columns', () => {
    it('routes a custom enum column (filterType "set") to agSetColumnFilter', () => {
      expect(
        resolveFilter(
          stubColumn({ id: 'custom.color', type: 'enum', filterType: 'set', source: 'custom' }),
        ),
      ).toBe('agSetColumnFilter')
    })

    it('routes a custom relation column (type "text", filterType "set") to agSetColumnFilter', () => {
      expect(
        resolveFilter(
          stubColumn({ id: 'custom.owner', type: 'text', filterType: 'set', source: 'custom' }),
        ),
      ).toBe('agSetColumnFilter')
    })

    it('routes a custom text column (filterType "text") to agMultiColumnFilter', () => {
      expect(
        resolveFilter(
          stubColumn({ id: 'custom.notes', type: 'text', filterType: 'text', source: 'custom' }),
        ),
      ).toBe('agMultiColumnFilter')
    })

    it('routes a custom number column (filterType "number") to agMultiColumnFilter', () => {
      expect(
        resolveFilter(
          stubColumn({ id: 'custom.score', type: 'number', filterType: 'number', source: 'custom' }),
        ),
      ).toBe('agMultiColumnFilter')
    })

    it('routes a custom boolean column (filterType "boolean") to agSetColumnFilter', () => {
      expect(
        resolveFilter(
          stubColumn({ id: 'custom.active', type: 'boolean', filterType: 'boolean', source: 'custom' }),
        ),
      ).toBe('agSetColumnFilter')
    })
  })
})

// Passthrough translator: asserting on the raw key is enough to prove the
// sub-menu title was resolved through i18n rather than hardcoded.
const translate = (key: string) => key

describe('buildColumnFilter', () => {
  it('builds no filterParams (no Set tab, no values-callback) for a computed column', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'primary_address', type: 'text', hasFilterValues: false }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agTextColumnFilter')
    expect(filterParams).toBeUndefined()
  })

  // 0005 (AC-020/021): Excel-classic layout — the Set checklist is the primary,
  // inline view; the typed condition lives in a titled sub-menu, never a tab.
  it('inlines the Excel-mode Set checklist and tucks the condition into a titled sub-menu', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'email', type: 'text' }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agMultiColumnFilter')
    expect(filterParams).toMatchObject({
      filters: [
        { filter: 'agSetColumnFilter', filterParams: { excelMode: 'windows' } },
        { filter: 'agTextColumnFilter', display: 'subMenu', title: 'table.textFilters' },
      ],
    })
  })

  it.each([
    ['number', 'agNumberColumnFilter', 'table.numberFilters'],
    ['date', 'agDateColumnFilter', 'table.dateFilters'],
  ] as const)(
    'resolves the "%s" sub-menu title for the typed condition filter',
    (filterType, expectedFilter, expectedTitleKey) => {
      const { filterParams } = buildColumnFilter(
        'users',
        stubColumn({ id: 'x', type: 'text', filterType }),
        vi.fn(),
        translate,
      )

      expect(filterParams).toMatchObject({
        filters: [
          expect.anything(),
          { filter: expectedFilter, display: 'subMenu', title: expectedTitleKey },
        ],
      })
    },
  )

  it('gives a standalone Set Filter column (set/enum/boolean) the same Excel-mode checklist', () => {
    const { filter, filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'user_type', type: 'enum' }),
      vi.fn(),
      translate,
    )

    expect(filter).toBe('agSetColumnFilter')
    expect(filterParams).toMatchObject({ excelMode: 'windows' })
  })

  // A boolean column's checklist must show Sì/No, not the raw 1/0 the backend
  // yields. The formatter localizes display only; the raw value is untouched.
  it('localizes a boolean column Set Filter to yes/no (both 1/0 and true/false shapes)', () => {
    const { filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'is_active', type: 'boolean', filterType: 'set' }),
      vi.fn(),
      translate,
    )

    const format = (filterParams as { valueFormatter: (p: { value: unknown }) => string })
      .valueFormatter
    expect(format({ value: '1' })).toBe('common.yes')
    expect(format({ value: '0' })).toBe('common.no')
    expect(format({ value: true })).toBe('common.yes')
    expect(format({ value: false })).toBe('common.no')
    expect(format({ value: null })).toBe('')
  })

  it('does not attach a boolean valueFormatter to a non-boolean Set Filter column', () => {
    const { filterParams } = buildColumnFilter(
      'users',
      stubColumn({ id: 'user_type', type: 'enum' }),
      vi.fn(),
      translate,
    )

    expect((filterParams as { valueFormatter?: unknown }).valueFormatter).toBeUndefined()
  })
})

/** Builds a minimal `SetFilterValuesFuncParams` stub carrying a fake filter model. */
function stubValuesParams(filterModel: Record<string, unknown>): SetFilterValuesFuncParams {
  return {
    api: { getFilterModel: () => filterModel },
    success: vi.fn(),
  } as unknown as SetFilterValuesFuncParams
}

describe('createColumnValuesGetter', () => {
  beforeEach(() => {
    fetchValuesMock.mockReset()
  })

  it('fetches values scoped to the OTHER columns, excluding the target column', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['a', 'b'], hasMore: false })
    const onTruncated = vi.fn()
    const params = stubValuesParams({
      email: { filterType: 'text', type: 'contains', filter: 'x' },
      roles: { filterType: 'set', values: ['admin'] },
    })

    createColumnValuesGetter('users', 'email', onTruncated)(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(fetchValuesMock).toHaveBeenCalledWith('users', {
      columnId: 'email',
      filterModel: { roles: { filterType: 'set', values: ['admin'] } },
    })
    expect(params.success).toHaveBeenCalledWith(['a', 'b'])
    expect(onTruncated).not.toHaveBeenCalled()
  })

  it('signals truncation when the backend reports hasMore', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['a'], hasMore: true })
    const onTruncated = vi.fn()
    const params = stubValuesParams({})

    createColumnValuesGetter('users', 'email', onTruncated)(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(onTruncated).toHaveBeenCalledOnce()
  })

  it('resolves to an empty list without crashing on a fetch failure', async () => {
    fetchValuesMock.mockRejectedValue(new Error('network error'))
    const params = stubValuesParams({})

    expect(() =>
      createColumnValuesGetter('users', 'email', vi.fn())(params),
    ).not.toThrow()
    await waitFor(() => expect(params.success).toHaveBeenCalledWith([]))
  })

  // Spec 0064: an `attr.<code>` column's set filter needs the selected
  // category so the backend can resolve it against that category's attributes.
  it('forwards productCategoryId when given (spec 0064)', async () => {
    fetchValuesMock.mockResolvedValue({ values: ['a'], hasMore: false })
    const params = stubValuesParams({})

    createColumnValuesGetter('request-management', 'attr.durata_corso', vi.fn(), 12)(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(fetchValuesMock).toHaveBeenCalledWith('request-management', {
      columnId: 'attr.durata_corso',
      filterModel: {},
      productCategoryId: 12,
    })
  })

  it('omits productCategoryId entirely when not given', async () => {
    fetchValuesMock.mockResolvedValue({ values: [], hasMore: false })
    const params = stubValuesParams({})

    createColumnValuesGetter('users', 'email', vi.fn())(params)
    await waitFor(() => expect(params.success).toHaveBeenCalled())

    expect(fetchValuesMock.mock.calls[0][1]).not.toHaveProperty('productCategoryId')
  })
})
