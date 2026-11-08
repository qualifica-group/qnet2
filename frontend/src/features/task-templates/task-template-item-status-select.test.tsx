import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { fetchForSelect } from '@/features/for-select/api'
import type { ForSelectParams } from '@/features/for-select/types'
import { TaskTemplateItemStatusSelect } from '@/features/task-templates/task-template-item-status-select'

/**
 * ADR 0011: every row's saved initial status keeps its label, even on a row
 * past the first (the list cache is shared) and even when the status was
 * deactivated or moved out of the open/pending groups since.
 */

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(),
}))

const STATUSES = {
  1: { id: 1, label: 'Da fare', meta: { group: 'open', color: 'blue' } },
  2: { id: 2, label: 'In attesa', meta: { group: 'pending', color: 'amber' } },
  5: { id: 5, label: 'Archiviato', meta: { group: 'closed', color: 'slate' } },
} as const

function page(ids: (keyof typeof STATUSES)[]) {
  return {
    items: ids.map((id) => STATUSES[id]),
    export_link: null,
    pagination: { total: ids.length, offset: 0, limit: 25 },
  } as unknown as Awaited<ReturnType<typeof fetchForSelect>>
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.mocked(fetchForSelect).mockReset()
  // The browse list holds the active open/pending statuses only; a requested id is served on top.
  vi.mocked(fetchForSelect).mockImplementation(async (_resource: string, params: ForSelectParams = {}) =>
    page([1, 2, ...((params.ids ?? []).filter((id) => id === 5) as 5[])]),
  )
})

describe('TaskTemplateItemStatusSelect', () => {
  it('labels the saved status of every row, also one deactivated or out of the allowed groups', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <TaskTemplateItemStatusSelect value={1} onChange={vi.fn()} />
        <TaskTemplateItemStatusSelect value={5} onChange={vi.fn()} />
      </QueryClientProvider>,
    )

    const [first, second] = screen.getAllByRole('combobox', { name: i18n.t('taskTemplates.form.items.status') })
    await waitFor(() => expect(first).toHaveTextContent('Da fare'))
    await waitFor(() => expect(second).toHaveTextContent('Archiviato'))
  })
})
