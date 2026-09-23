import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { User } from '@/features/auth/types'

/**
 * Spec 0146 D-3/AC-016/AC-030: `work_order_stage_id`'s two reset handlers
 * and its create-mode prefill — split out of `use-task-form.test.tsx`
 * (engineering.md §6, file-size split, mirrors
 * `task-form-recurrence-section.test.tsx`'s own split).
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, fetchWorkOrder: vi.fn() }
})

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn(async () => []) }))

const currentUserMock = vi.fn<() => User | null>(() => currentUser())
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUserMock() }),
}))

function currentUser(overrides: Partial<User> = {}): User {
  return {
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
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

/**
 * D-3: the fase belongs to the commessa and a sub-task may not carry one, so
 * both handlers reset it in the change HANDLER — never in an effect that
 * could race a later edit (mirrors `useTaskForm`'s own `handleRegistryChange`).
 */
describe('useTaskForm — work_order_stage_id reset handlers (spec 0146 D-3)', () => {
  it('clears the fase when the commessa changes', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))
    expect(result.current.form.getValues('work_order_stage_id')).toBe(7)

    act(() => result.current.handleWorkOrderChange())

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('clears the fase when a parent task is picked', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.form.setValue('work_order_stage_id', 7))

    act(() => result.current.handleParentChange())

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })
})

describe('useTaskForm — fase prefill from ModuleCreateParams (spec 0146 AC-030)', () => {
  it('seeds work_order_stage_id from the create mode', () => {
    const { result } = renderHook(
      () =>
        useTaskForm({
          mode: { type: 'create', workOrderId: 9, workOrderStageId: 7 },
          onSuccess: () => undefined,
        }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('work_order_stage_id')).toBe(7)
  })

  it('never seeds the fase on a "crea sotto-task" prefill (D-3: a sub-task cannot carry one)', () => {
    const { result } = renderHook(
      () =>
        useTaskForm({
          mode: { type: 'create', parentTaskId: 90, workOrderId: 9, workOrderStageId: 7 },
          onSuccess: () => undefined,
        }),
      { wrapper: wrapper() },
    )

    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })
})
