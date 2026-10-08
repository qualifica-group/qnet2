/* eslint-disable react-refresh/only-export-components -- test doubles and helpers colocated on purpose */
import type { ReactNode } from 'react'
import { fireEvent, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { ForSelectItem } from '@/features/for-select/types'

/**
 * Shared scaffolding of the opportunity CREATE form suites (spec 0198): the
 * create form is a replica of the detail whose rows start CLOSED, so every
 * suite opens a row through its pencil before using the row's control. The
 * `vi.mock` calls themselves cannot live here (they are hoisted per file);
 * the doubles they install can:
 *
 *   vi.mock('@/components/ui/async-paginated-select', async () => ({
 *     AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers')).AsyncPaginatedSelectDouble,
 *   }))
 */

export const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

export const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** Fixed ids offered per trigger label by the select doubles (default `[1]`); a suite fills what it needs. */
export const SELECT_IDS: Record<string, number[]> = {}

interface SelectDoubleProps {
  value: number | null
  onChange: (value: number | null) => void
  disabled?: boolean
  params?: Record<string, string | number>
  selectedItem?: { id: number; label: string } | null
  labels: { triggerLabel: string }
}

/** Stands in for `AsyncPaginatedSelect`: exposes value/disabled/params/label as probes and one "select" button per fixed id. */
export function AsyncPaginatedSelectDouble({ value, onChange, disabled, params, selectedItem, labels }: SelectDoubleProps) {
  return (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <span data-testid={`params-${labels.triggerLabel}`}>{JSON.stringify(params ?? null)}</span>
      <span data-testid={`label-${labels.triggerLabel}`}>{selectedItem?.label ?? ''}</span>
      {(SELECT_IDS[labels.triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
      <button type="button" onClick={() => onChange(null)}>{`clear ${labels.triggerLabel}`}</button>
    </div>
  )
}

/** The classification row's FIRST step (spec 0132): same double style, never disabled by the stub itself. */
export function ProductCategoryRootSelectDouble({
  value,
  onChange,
  disabled,
  triggerLabel,
}: {
  value: number | null
  onChange: (rootCategoryId: number) => void
  disabled?: boolean
  triggerLabel: string
}) {
  return (
    <div data-testid={`select-${triggerLabel}`}>
      <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${triggerLabel}`}>{String(Boolean(disabled))}</span>
      {(SELECT_IDS[triggerLabel] ?? [1]).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${triggerLabel} ${id}`}
        </button>
      ))}
    </div>
  )
}

/** The id the products-of-interest multi-select double adds on click. */
export const TEST_PRODUCT_ID = 700

/** Stands in for `AsyncPaginatedMultiSelect` (the products-of-interest picker). */
export function AsyncPaginatedMultiSelectDouble({
  value,
  onChange,
  labels,
}: {
  value: number[]
  onChange: (value: number[]) => void
  labels: { triggerLabel: string }
}) {
  return (
    <div data-testid={`multi-${labels.triggerLabel}`}>
      <span data-testid={`value-multi-${labels.triggerLabel}`}>{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, TEST_PRODUCT_ID])}>
        {`select ${labels.triggerLabel} ${TEST_PRODUCT_ID}`}
      </button>
    </div>
  )
}

/** Names the closed rows show: the create form resolves ids through `fetchForSelect` (`useForSelectLabels`). */
export const FOR_SELECT_LABELS: Record<string, Record<number, string>> = {
  registries: { 10: 'Acme S.p.A.', 20: 'Beta Srl', 30: 'Gamma Spa' },
  referents: { 1: 'Contact One', 71: 'Sara Conti', 81: 'Elio Fabbri' },
  sources: { 20: 'Web' },
  users: { 1: 'User One', 61: 'Ivo Bianchi', 91: 'Gina Manager', 93: 'Turi Manager', 300: 'Giulia Bianchi' },
}

/** `fetchForSelect` double: resolves requested `ids` to the names above, anything else is an empty page. */
export function resolveForSelectLabels(resource: string, params?: { ids?: number[] }) {
  const names = FOR_SELECT_LABELS[resource] ?? {}
  const items: ForSelectItem[] = (params?.ids ?? []).flatMap((id) =>
    names[id] !== undefined ? [{ id, label: names[id] }] : [],
  )
  return { ...EMPTY_PAGE, items }
}

/** Fresh client per call: one per test, never per render (frontend.md §10). */
export function formTestWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter>{children}</MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

/** The pencil of a closed row, by the accessible name the row gives it. */
export function queryPencil(rowLabel: string): HTMLElement | null {
  return screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: rowLabel }) })
}

/** Opens a closed row: its control replaces the displayed value. */
export function openRow(rowLabel: string): void {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: rowLabel }) }))
}

/** "Done" on the open row: keeps the value in the draft and validates that field. */
export function applyRow(): void {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
}

/** "Revert" on the open row: restores the draft as it was when the row opened. */
export function revertRow(): void {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.revert') }))
}

/** Row labels of the create form (English locale), the pencils' accessible names derive from them. */
export const ROW = {
  title: 'Title',
  registry: 'Registry',
  referent: 'Contact',
  commercial: 'Sales rep',
  reporter: 'Reporter',
  source: 'Source',
  productLines: 'Product lines',
  productsOfInterest: 'Products of interest',
  supervisor: 'Supervisor',
  managers: 'Account managers',
} as const

/** Both the identity band and the form's footer carry a "Save" for the same `<form>`: every one of them. */
export function saveButtons(): HTMLElement[] {
  return screen.getAllByRole('button', { name: i18n.t('opportunities.form.save') })
}

/** Submits the create form through its first "Save". */
export function clickSave(): void {
  fireEvent.click(saveButtons()[0])
}
