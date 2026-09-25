import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { User } from '@/features/auth/types'

/**
 * Spec 0157 D-4: the Kanban's per-column "+" carries `taskStatusId`/`endDate`
 * through `ModuleCreateParams` -> `TaskFormMode` (`task-screens.tsx`) ->
 * `createDefaults` (`use-task-form.ts`). A dedicated, minimal file (mirrors
 * `use-task-form.test.tsx`'s own harness) so it does not touch that large,
 * shared test file.
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn(), fetchTask: vi.fn() }
})

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({
    user: {
      id: 99,
      name: 'Utente Corrente',
      email: 'utente@example.com',
      locale: 'en',
      roles: [],
      avatar_url: null,
      personal_data: null,
      created_at: null,
      module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
      ui_scale: 40,
      date_format: 'dmy',
      time_format: '24h',
      color_preset: 'default',
    } satisfies User,
  }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Today as `YYYY-MM-DD`, mirroring `use-task-form.ts`'s own private `todayIsoDate()`. */
function todayIsoDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useTaskForm — create prefilled from the Kanban "+" (spec 0157 D-4)', () => {
  it('prefills task_status_id from taskStatusId', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', taskStatusId: 7 }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('task_status_id')).toBe(7)
  })

  it('prefills end_date from endDate, instead of today', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create', endDate: '2026-12-25' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('end_date')).toBe('2026-12-25')
  })

  it('both together, without disturbing each other', () => {
    const { result } = renderHook(
      () =>
        useTaskForm({
          mode: { type: 'create', taskStatusId: 3, endDate: '2026-11-01' },
          onSuccess: () => undefined,
        }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('task_status_id')).toBe(3)
    expect(result.current.form.getValues('end_date')).toBe('2026-11-01')
  })

  it('falls back to today/null (unchanged bare-create behavior) when omitted', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('task_status_id')).toBeNull()
    expect(result.current.form.getValues('end_date')).toBe(todayIsoDate())
  })
})
