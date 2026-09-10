import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { RelationCellEditor, type RelationCellValue } from '@/components/data-table/relation-cell-editor'
import { fetchForSelect } from '@/features/for-select/api'
import type { PaginatedResponse, ForSelectItem } from '@/features/for-select/types'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0054 AC-017 (+ D-9 single-click follow-up): a relation column's editor
 * mounts with its searchable, paginated dropdown ALREADY open (`defaultOpen`)
 * — the click that starts the cell edit is the only click the operator makes
 * — fed by the declared `/for-select` resource, and picking (or clearing) a
 * value commits it and closes the editor. Mocks only the HTTP boundary
 * (`fetchForSelect`) — `AsyncPaginatedSelect` itself and its `for-select`
 * hooks run for real, so this exercises the actual wiring.
 */

vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: vi.fn(),
}))

const fetchForSelectMock = vi.mocked(fetchForSelect)

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return { items, export_link: null, pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1 } }
}

function renderEditor(props: Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const fullProps = {
    value: null,
    onValueChange: vi.fn(),
    stopEditing: vi.fn(),
    resource: 'users',
    ...props,
  } as unknown as CustomCellEditorProps<TableRow, RelationCellValue | null> & { resource: string }

  render(
    (<QueryClientProvider client={client}>
      <RelationCellEditor {...fullProps} />
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

describe('RelationCellEditor', () => {
  // The editor renders its search + list DIRECTLY in AG Grid's popup (spec 0055
  // follow-up): the previous assertions described the Radix combobox trigger it
  // used to nest inside the cell, which is exactly what broke editing in a real
  // grid — see the component docblock.
  it('renders the searchable list immediately, fed by the declared /for-select resource — no second click (AC-017/D-9)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }, { id: 2, label: 'Luigi Bianchi' }]))
    renderEditor()

    expect(screen.getByRole('listbox', { name: i18n.t('table.relationEditor.trigger') })).toBeInTheDocument()
    expect(
      screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') }),
    ).toBeInTheDocument()
    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.any(Object)))
    expect(await screen.findByRole('option', { name: 'Mario Rossi' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Luigi Bianchi' })).toBeInTheDocument()
  })

  it('shows the initials avatar with `showAvatar`, even for an option the envelope sent without `avatar_url`', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
    renderEditor({ showAvatar: true } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

    expect(await screen.findByText('MR')).toBeInTheDocument()
  })

  it('shows no avatar without `showAvatar` (non-people resources)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi', avatar_url: null }]))
    renderEditor()

    expect(await screen.findByRole('option', { name: 'Mario Rossi' })).toBeInTheDocument()
    expect(screen.queryByText('MR')).not.toBeInTheDocument()
  })

  it('sends focus to the search field on mount (native `autoFocus`, no click needed)', () => {
    fetchForSelectMock.mockResolvedValue(page([]))
    renderEditor()

    expect(screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') })).toHaveFocus()
  })

  it('commits the picked item as {id, name} and stops editing, with a single click on the option (AC-017)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
    const props = renderEditor()

    fireEvent.click(await screen.findByRole('option', { name: 'Mario Rossi' }))

    expect(props.onValueChange).toHaveBeenCalledWith({ id: 1, name: 'Mario Rossi' })
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('commits null and stops editing when the current selection is cleared', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 5, label: 'Mario Rossi' }]))
    const props = renderEditor({ value: { id: 5, name: 'Mario Rossi' } })

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('table.relationEditor.clear') }))

    expect(props.onValueChange).toHaveBeenCalledWith(null)
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  it('hydrates the current value through the query so it is offered even outside the searched window', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 5, label: 'Mario Rossi' }]))
    renderEditor({ value: { id: 5, name: 'Mario Rossi' } })

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.objectContaining({ ids: [5] })))
    expect(await screen.findByRole('option', { name: 'Mario Rossi' })).toHaveAttribute('aria-selected', 'true')
  })

  it('does not commit when the CURRENT value is re-picked (no pointless PATCH)', async () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 5, label: 'Mario Rossi' }]))
    const props = renderEditor({ value: { id: 5, name: 'Mario Rossi' } })

    fireEvent.click(await screen.findByRole('option', { name: 'Mario Rossi' }))

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).toHaveBeenCalledTimes(1)
  })

  // Row-scoped narrowing (user directive 2026-07-23): the operator picker of a
  // request must offer only the users of THAT request's operational site.
  describe('row-scoped option list (`scope`)', () => {
    const SCOPE = { operational_site_id: 'operational_site' }

    it('sends the scope column value of the edited row as a /for-select param', async () => {
      fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
      renderEditor({
        scope: SCOPE,
        data: { id: 7, actions: [], operational_site: { id: 42, label: 'Via Roma 1 - Milano' } },
      } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

      await waitFor(() =>
        expect(fetchForSelectMock).toHaveBeenCalledWith(
          'users',
          expect.objectContaining({ params: { operational_site_id: 42 } }),
        ),
      )
    })

    it('accepts the `{id, name}` projection too — a single value stays a SINGLE param, never an array', async () => {
      fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
      renderEditor({
        scope: SCOPE,
        data: { id: 7, actions: [], operational_site: { id: 42, name: 'Via Roma 1 - Milano' } },
      } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

      await waitFor(() =>
        expect(fetchForSelectMock).toHaveBeenCalledWith(
          'users',
          expect.objectContaining({ params: { operational_site_id: 42 } }),
        ),
      )
    })

    it('accepts a bare id in the scope column', async () => {
      fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
      renderEditor({
        scope: SCOPE,
        data: { id: 7, actions: [], operational_site: 42 },
      } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

      await waitFor(() =>
        expect(fetchForSelectMock).toHaveBeenCalledWith(
          'users',
          expect.objectContaining({ params: { operational_site_id: 42 } }),
        ),
      )
    })

    it('sends NO param when the row has no value for the scope column — full list, never a junk filter', async () => {
      fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
      renderEditor({
        scope: SCOPE,
        data: { id: 7, actions: [], operational_site: null },
      } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

      await waitFor(() =>
        expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.objectContaining({ params: undefined })),
      )
    })

    // User directive 2026-09-10: the lead table's Operatore cell offered EVERY
    // operator, because a scope column holding a SET of ids (the lead's
    // required competence categories) could not be expressed as a param at all.
    describe('a scope column holding a SET of ids', () => {
      const CATEGORIES_SCOPE = { competence_category_ids: 'assignment_category_ids' }

      it('sends the whole set as a repeated param', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: CATEGORIES_SCOPE,
          data: { id: 7, actions: [], assignment_category_ids: [3, 9] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith(
            'users',
            expect.objectContaining({ params: { competence_category_ids: [3, 9] } }),
          ),
        )
      })

      it('sends NO param for an EMPTY set — no requirement means no filter, not "no candidate"', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: CATEGORIES_SCOPE,
          data: { id: 7, actions: [], assignment_category_ids: [] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.objectContaining({ params: undefined })),
        )
      })

      it('keeps the resolvable entries of a mixed set and drops the rest', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: CATEGORIES_SCOPE,
          data: { id: 7, actions: [], assignment_category_ids: [3, null, 'nine', { id: 9, label: 'Fotovoltaico' }] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith(
            'users',
            expect.objectContaining({ params: { competence_category_ids: [3, 9] } }),
          ),
        )
      })

      it('sends no param when NOTHING in the set resolves to an id', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: CATEGORIES_SCOPE,
          data: { id: 7, actions: [], assignment_category_ids: [null, 'nine'] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.objectContaining({ params: undefined })),
        )
      })

      // The Lead column itself (spec 0113): site AND competence, together.
      it('combines a single-value param and a set param in the same request', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: { operational_site_id: 'assignment_site_id', ...CATEGORIES_SCOPE },
          data: { id: 7, actions: [], assignment_site_id: 42, assignment_category_ids: [3, 9] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith(
            'users',
            expect.objectContaining({
              params: { operational_site_id: 42, competence_category_ids: [3, 9] },
            }),
          ),
        )
      })

      it('still sends the single-value param when the row carries no categories', async () => {
        fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
        renderEditor({
          scope: { operational_site_id: 'assignment_site_id', ...CATEGORIES_SCOPE },
          data: { id: 7, actions: [], assignment_site_id: 42, assignment_category_ids: [] },
        } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

        await waitFor(() =>
          expect(fetchForSelectMock).toHaveBeenCalledWith(
            'users',
            expect.objectContaining({ params: { operational_site_id: 42 } }),
          ),
        )
      })
    })

    it('sends no param when the column declares no scope at all', async () => {
      fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
      renderEditor({
        data: { id: 7, actions: [], operational_site: { id: 42, label: 'Via Roma 1 - Milano' } },
      } as Partial<CustomCellEditorProps<TableRow, RelationCellValue | null>>)

      await waitFor(() =>
        expect(fetchForSelectMock).toHaveBeenCalledWith('users', expect.objectContaining({ params: undefined })),
      )
    })
  })

  it('never commits or stops editing on its own on Escape — the component wires no handler for it, leaving AG Grid\'s own cancel path untouched', () => {
    fetchForSelectMock.mockResolvedValue(page([{ id: 1, label: 'Mario Rossi' }]))
    const props = renderEditor()

    fireEvent.keyDown(
      screen.getByRole('textbox', { name: i18n.t('table.relationEditor.searchPlaceholder') }),
      { key: 'Escape' },
    )

    expect(props.onValueChange).not.toHaveBeenCalled()
    expect(props.stopEditing).not.toHaveBeenCalled()
  })
})
