import type { ReactNode } from 'react'
import { AxiosError, AxiosHeaders } from 'axios'
import { fireEvent, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import type { TimeEntry } from '@/features/time-entries/types'

/** Shared data and helpers of the time entry record tests (spec 0226): create draft and in-place detail. */

export const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const TASK_TYPES_PAGE = {
  items: [
    { id: 1, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } },
    { id: 2, label: 'Riunione', meta: { color: 'violet', icon: 'users' } },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}
const REGISTRIES_PAGE = {
  items: [{ id: 10, label: 'ACME SpA' }],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}
const TASKS_PAGE = {
  items: [{ id: 40, label: 'Task Demo', meta: {} }],
  pagination: { offset: 0, limit: 25, total: 1 },
  export_link: null,
}
export const WORK_ORDER_PAGE = {
  items: [
    { id: 30, label: 'COM-0001', meta: {} },
    { id: 31, label: 'COM-0002', meta: {} },
  ],
  pagination: { offset: 0, limit: 25, total: 2 },
  export_link: null,
}

/** The default `fetchForSelect` answer per resource. */
export async function defaultForSelect(resource: string) {
  if (resource === 'task-types') return TASK_TYPES_PAGE
  if (resource === 'registries') return REGISTRIES_PAGE
  if (resource === 'tasks') return TASKS_PAGE
  if (resource === 'work-orders') return WORK_ORDER_PAGE
  return EMPTY_PAGE
}

export function buildEntry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 1,
    user: { id: 1, name: 'Utente Corrente' },
    date: '2026-09-14',
    title: 'Rientro cliente',
    task_type: { id: 1, name: 'Attività', color: 'blue', icon: 'clipboard-list' },
    start_time: null,
    end_time: null,
    minutes: 60,
    notes: null,
    registry: null,
    opportunity: null,
    work_order: null,
    task: null,
    work_order_stage: null,
    created_at: '2026-09-14T08:00:00Z',
    updated_at: '2026-09-14T08:00:00Z',
    permissions: { update: true, delete: true },
    ...overrides,
  }
}

/** A 422 shaped so `axios.isAxiosError` recognizes it (mirrors `task-detail.test.tsx`). */
export function validationError(errors: Record<string, string[]>): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status: 422,
    statusText: 'Unprocessable Content',
    data: { success: false, message: 'failed', errors },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

/** One QueryClient per test (frontend.md §10), plus the router the record links need. */
export function timeEntryWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
}

export const label = (key: string) => i18n.t(key)

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * A field control by its label, anchored: a required field's label also
 * renders a trailing "*", and a substring match would confuse "Alle ore" with
 * "Dalle ore".
 */
export const field = (key: string) => screen.getByLabelText(new RegExp(`^${escapeRegExp(label(key))}\\s*\\*?$`))

/** The pencil of the row labelled by `key`. */
export const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label(key) }) })

/** Opens the row labelled by `key` through its pencil. */
export function openRow(key: string) {
  const pencil = pencilOf(key)
  if (!pencil) {
    throw new Error(`No pencil on the "${label(key)}" row`)
  }
  fireEvent.click(pencil)
}
