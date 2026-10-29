import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { fetchForSelect } from '@/features/for-select/api'
import { TaskTemplateItemWorkOrderStatusSelect } from '@/features/task-templates/task-template-item-work-order-status-select'

/** Spec 0323 AC-020: the row's "Stato lavorazione commessa" select, grouped by workflow with the progress %. */

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(),
}))

const PAGE = {
  items: [
    { id: 1, name: 'Aperta', color: 'blue', progress_percentage: 0, system_key: 'open', work_order_workflow_id: null, work_order_workflow_name: null },
    { id: 2, name: 'Chiusa', color: 'green', progress_percentage: 100, system_key: 'completed', work_order_workflow_id: null, work_order_workflow_name: null },
    { id: 7, name: 'Collaudo', color: null, progress_percentage: 60, system_key: null, work_order_workflow_id: 3, work_order_workflow_name: 'Impianti' },
    { id: 8, name: 'Consegna', color: null, progress_percentage: null, system_key: null, work_order_workflow_id: 3, work_order_workflow_name: 'Impianti' },
  ],
  export_link: null,
  pagination: { total: 4, offset: 0, limit: 25, total_pages: 1 },
}

const TRIGGER_NAME = () => i18n.t('taskTemplates.form.items.workOrderStatus')

function renderSelect(value: number | null, onChange = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskTemplateItemWorkOrderStatusSelect value={value} onChange={onChange} />
    </QueryClientProvider>,
  )
  return onChange
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(fetchForSelect).mockReset()
  vi.mocked(fetchForSelect).mockResolvedValue(PAGE as unknown as Awaited<ReturnType<typeof fetchForSelect>>)
})

describe('TaskTemplateItemWorkOrderStatusSelect', () => {
  it('groups the statuses by workflow, default set first, with the progress % beside the name', async () => {
    renderSelect(null)

    fireEvent.click(screen.getByRole('combobox', { name: TRIGGER_NAME() }))
    const listbox = await screen.findByRole('listbox')
    await within(listbox).findByRole('option', { name: 'Aperta (0%)' })

    const labels = within(listbox)
      .getAllByRole('option')
      .map((option) => option.textContent)
    expect(labels).toEqual([
      i18n.t('taskTemplates.form.items.workOrderStatusDefaultSet'),
      'Aperta (0%)',
      'Chiusa (100%)',
      'Impianti',
      'Collaudo (60%)',
      'Consegna',
    ])
    expect(within(listbox).getByRole('option', { name: 'Impianti' })).toHaveAttribute('aria-disabled', 'true')
  })

  it('emits the status id when one is picked, and ignores a group header', async () => {
    const onChange = renderSelect(null)

    fireEvent.click(screen.getByRole('combobox', { name: TRIGGER_NAME() }))
    const listbox = await screen.findByRole('listbox')
    fireEvent.click(await within(listbox).findByRole('option', { name: 'Impianti' }))
    expect(onChange).not.toHaveBeenCalled()

    fireEvent.click(within(listbox).getByRole('option', { name: 'Collaudo (60%)' }))
    expect(onChange).toHaveBeenCalledWith(7)
  })

  it('hydrates the saved value through ids and clears to null', async () => {
    const onChange = renderSelect(7)

    const trigger = await screen.findByRole('combobox', { name: TRIGGER_NAME() })
    expect(await within(trigger).findByText('Collaudo (60%)')).toBeInTheDocument()
    expect(vi.mocked(fetchForSelect)).toHaveBeenCalledWith(
      'work-order-workflow-statuses',
      expect.objectContaining({ ids: [7] }),
    )

    fireEvent.click(screen.getByRole('button', { name: new RegExp(i18n.t('taskTemplates.form.items.workOrderStatusClear')) }))
    expect(onChange).toHaveBeenCalledWith(null)
  })

  it('labels every row of the board even when the saved statuses are beyond the shared first page', async () => {
    const offPage = [
      { id: 40, name: 'Intervista', color: null, progress_percentage: 10, system_key: null, work_order_workflow_id: 9, work_order_workflow_name: 'ISO' },
      { id: 41, name: 'Audit', color: null, progress_percentage: 80, system_key: null, work_order_workflow_id: 9, work_order_workflow_name: 'ISO' },
    ]
    vi.mocked(fetchForSelect).mockImplementation(async (_resource, params) => {
      const hydrated = offPage.filter((status) => params?.ids?.includes(status.id))
      return { ...PAGE, items: [...PAGE.items, ...hydrated] } as unknown as Awaited<ReturnType<typeof fetchForSelect>>
    })
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <TaskTemplateItemWorkOrderStatusSelect value={40} onChange={vi.fn()} />
        <TaskTemplateItemWorkOrderStatusSelect value={41} onChange={vi.fn()} />
      </QueryClientProvider>,
    )

    const [first, second] = await screen.findAllByRole('combobox', { name: TRIGGER_NAME() })
    expect(await within(first).findByText('Intervista (10%)')).toBeInTheDocument()
    expect(await within(second).findByText('Audit (80%)')).toBeInTheDocument()
  })
})
