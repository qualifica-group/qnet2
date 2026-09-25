import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { TimeEntryContextFields } from '@/features/time-entries/form/time-entry-context-fields'
import { fetchWorkOrderStages } from '@/features/work-orders/task-board/api'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import type { TimeEntryWorkOrderStageRef } from '@/features/time-entries/types'

/**
 * Spec 0163 D-1/AC-008: the "Fase" field — visible ONLY with a commessa and
 * no task, OPEN fasi only, read-only (or absent) once a task is linked.
 * `use-time-entry-form-stage.test.ts` covers the reset HANDLERS at the hook
 * level; this file covers the fieldset's own visibility/wiring.
 */

vi.mock('@/features/work-orders/task-board/api', () => ({ fetchWorkOrderStages: vi.fn() }))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

interface HarnessProps {
  workOrderId?: number | null
  taskId?: number | null
  isTaskLinked?: boolean
  stage?: TimeEntryWorkOrderStageRef | null
  workOrder?: RelationFieldRef | null
}

function Harness({ workOrderId = null, taskId = null, isTaskLinked = false, stage = null, workOrder = null }: HarnessProps) {
  const form = useForm<TimeEntryFormValues>({
    defaultValues: {
      title: '',
      date: '2026-09-25',
      task_type_id: null,
      start_time: null,
      end_time: null,
      minutes: null,
      notes: null,
      registry_id: null,
      opportunity_id: null,
      work_order_id: workOrderId,
      task_id: taskId,
      work_order_stage_id: null,
    },
  })

  return (
    <Form {...form}>
      <TimeEntryContextFields
        control={form.control}
        registry={null}
        opportunity={null}
        workOrder={workOrder}
        task={taskId ? { id: taskId, name: 'Task Demo' } : null}
        isTaskLinked={isTaskLinked}
        taskDetail={undefined}
        stage={stage}
        onRegistryChange={() => undefined}
        onOpportunityItemChange={() => undefined}
        onWorkOrderItemChange={() => undefined}
      />
    </Form>
  )
}

function renderHarness(props: HarnessProps = {}) {
  return render(<Harness {...props} />, { wrapper: wrapper() })
}

const label = (key: string) => i18n.t(key)
const fasePicker = () => screen.queryByRole('combobox', { name: label('timeEntries.form.workOrderStage') })

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  vi.mocked(fetchWorkOrderStages).mockReset()
  vi.mocked(fetchWorkOrderStages).mockResolvedValue([
    { id: 1, name: 'Analisi', sort_order: 0, closed_at: null, closed_by: null, logged_minutes: 0 },
    { id: 2, name: 'Chiusa', sort_order: 1, closed_at: '2026-09-01T00:00:00Z', closed_by: null, logged_minutes: 0 },
  ])
})

describe('TimeEntryContextFields — "Fase" visible only with a commessa and no task (AC-008)', () => {
  it('is absent without a commessa', () => {
    renderHarness()

    expect(fasePicker()).not.toBeInTheDocument()
    expect(screen.queryByText(label('timeEntries.form.workOrderStage'))).not.toBeInTheDocument()
  })

  it('appears once a commessa is picked, standalone (no task)', async () => {
    renderHarness({ workOrderId: 30, workOrder: { id: 30, name: 'COM-0001' } })

    await waitFor(() => expect(fasePicker()).toBeInTheDocument())
  })

  it('stays absent with a task linked and no persisted fase on the entry', () => {
    renderHarness({ workOrderId: 30, taskId: 40, isTaskLinked: true, stage: null })

    expect(fasePicker()).not.toBeInTheDocument()
    expect(screen.queryByText(label('timeEntries.form.workOrderStage'))).not.toBeInTheDocument()
  })

  it('shows the persisted fase read-only with a task linked (AC-008: "mostrala in sola lettura")', () => {
    renderHarness({
      workOrderId: 30,
      taskId: 40,
      isTaskLinked: true,
      stage: { id: 1, name: 'Analisi' },
    })

    expect(fasePicker()).not.toBeInTheDocument()
    const readOnly = screen.getByDisplayValue('Analisi')
    expect(readOnly).toBeDisabled()
  })
})

describe('TimeEntryContextFields — options are the commessa\'s OPEN fasi only (AC-008)', () => {
  it('lists the open fase but not the closed one', async () => {
    renderHarness({ workOrderId: 30, workOrder: { id: 30, name: 'COM-0001' } })
    await waitFor(() => expect(fasePicker()).not.toBeDisabled())

    fireEvent.click(fasePicker() as HTMLElement)

    expect(await screen.findByRole('option', { name: 'Analisi' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Chiusa' })).not.toBeInTheDocument()
    expect(screen.getByRole('option', { name: label('timeEntries.form.workOrderStageNoStage') })).toBeInTheDocument()
  })
})
