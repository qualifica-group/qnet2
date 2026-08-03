import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { MultiSelectCellEditor, type MultiSelectCellValue } from '@/components/data-table/multi-select-cell-editor'
import { resolveMultiScopeParams } from '@/components/data-table/multi-select-scope'
import { fetchForSelect } from '@/features/for-select/api'
import type { PaginatedResponse, ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'

/**
 * User directive 2026-07-23: the in-grid twin of the form's products picker —
 * scoped to the row's own categories by default, the whole catalogue only
 * after the inline warning is confirmed, and several options toggled in one
 * editing session. Mocks only the HTTP boundary (`fetchForSelect`); the
 * for-select hooks run for real, so the params actually sent are asserted.
 */

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: vi.fn(),
}))

const fetchForSelectMock = vi.mocked(fetchForSelect)

const ROW: TableRow = {
  id: 1,
  actions: [],
  editable: true,
  product_category_ids: [7, 9],
  products_of_interest: [],
}

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, export_link: null, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1 } }
}

function renderEditor(props: Partial<CustomCellEditorProps<TableRow, MultiSelectCellValue[] | null>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const fullProps = {
    value: null,
    data: ROW,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    resource: 'products',
    scope: { category_ids: 'product_category_ids' },
    ...props,
  } as unknown as CustomCellEditorProps<TableRow, MultiSelectCellValue[] | null> & {
    resource: string
    scope?: Record<string, string>
  }

  render(
    (<QueryClientProvider client={client}>
      <MultiSelectCellEditor {...fullProps} />
    </QueryClientProvider>) as ReactElement,
  )

  return fullProps
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
})

describe('resolveMultiScopeParams', () => {
  it('reads an array of ids off the row', () => {
    expect(resolveMultiScopeParams({ category_ids: 'product_category_ids' }, ROW)).toEqual({ category_ids: [7, 9] })
  })

  it('reads a single relation projection or a bare id as a one-element list', () => {
    const row = { id: 1, actions: [], site: { id: 3, name: 'Milano' }, other: 5 } as TableRow

    expect(resolveMultiScopeParams({ a: 'site', b: 'other' }, row)).toEqual({ a: [3], b: [5] })
  })

  it('sends no param at all when the row carries no value for the scope column', () => {
    expect(resolveMultiScopeParams({ category_ids: 'missing' }, ROW)).toBeUndefined()
    expect(resolveMultiScopeParams(undefined, ROW)).toBeUndefined()
  })
})

describe('MultiSelectCellEditor', () => {
  it("scopes the option list to the row's own ids by default", async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Fibra 1000' }]))
    renderEditor()

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'products',
        expect.objectContaining({ params: { category_ids: [7, 9] } }),
      ),
    )
    expect(await screen.findByRole('option', { name: 'Fibra 1000' })).toBeInTheDocument()
  })

  it('toggles options without closing the editor, building the whole collection', async () => {
    fetchForSelectMock.mockResolvedValue(
      page([{ id: 1, label: 'Fibra 1000' }, { id: 2, label: 'ADSL 20' }]),
    )
    const props = renderEditor()

    fireEvent.click(await screen.findByRole('option', { name: 'Fibra 1000' }))

    expect(props.onValueChange).toHaveBeenCalledWith([{ id: 1, name: 'Fibra 1000' }])
    expect(props.stopEditing).not.toHaveBeenCalled()
  })

  it('removes an already-selected option on a second click', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Fibra 1000' }]))
    const props = renderEditor({ value: [{ id: 1, name: 'Fibra 1000' }] })

    fireEvent.click(await screen.findByRole('option', { name: 'Fibra 1000' }))

    expect(props.onValueChange).toHaveBeenCalledWith([])
  })

  it('asks for confirmation before unlocking, then drops the scope from the query', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Fibra 1000' }]))
    renderEditor()
    await screen.findByRole('option', { name: 'Fibra 1000' })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') }))

    // The warning is INLINE (a portalled dialog would tear the editor down).
    expect(
      screen.getByRole('alertdialog', { name: i18n.t('table.multiSelectEditor.unlockTitle') }),
    ).toBeInTheDocument()
    expect(screen.getByText(i18n.t('table.multiSelectEditor.unlockDescription'))).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') }))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'products',
        expect.objectContaining({ params: undefined }),
      ),
    )
  })

  it('keeps the scope when the unlock warning is dismissed', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Fibra 1000' }]))
    renderEditor()
    await screen.findByRole('option', { name: 'Fibra 1000' })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.cancel') }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(fetchForSelectMock).not.toHaveBeenCalledWith('products', expect.objectContaining({ params: undefined }))
  })

  it('offers no options at all when the row has nothing to scope to, until it is unlocked', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Fibra 1000' }]))
    renderEditor({ data: { id: 2, actions: [] } as TableRow })

    expect(screen.getByText(i18n.t('table.multiSelectEditor.noScope'))).toBeInTheDocument()
    expect(fetchForSelectMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') }))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
  })
})

// ---------------------------------------------------------------------------
// Spec 0075, D-4 — the locked variant (request-management's own column)
// ---------------------------------------------------------------------------

describe('MultiSelectCellEditor with lockScope (spec 0075, AC-016)', () => {
  it('offers no unlock: the module refuses what falls outside the row scope', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 4, label: 'Fibra 1000' }]))

    renderEditor({ lockScope: true } as never)

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())

    expect(screen.queryByRole('button', { name: i18n.t('table.multiSelectEditor.unlock') })).not.toBeInTheDocument()
    expect(screen.getByText(i18n.t('table.multiSelectEditor.hintLocked'))).toBeInTheDocument()
  })
})
