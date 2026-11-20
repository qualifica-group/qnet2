import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { QuoteSystemStatusDetailView } from '@/features/quote-system-statuses/quote-system-status-detail'
import type { QuoteSystemStatusDetailWithPermissions } from '@/features/quote-system-statuses/types'

/**
 * The detail edits in place (name, color, behavior), each save a PATCH of that
 * field alone; a system row (system_key) keeps its behavior fixed (spec 0404 A-D3).
 */

const updateQuoteSystemStatusMock = vi.fn()

vi.mock('@/features/quote-system-statuses/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/quote-system-statuses/api')>()),
  updateQuoteSystemStatus: (...args: unknown[]) => updateQuoteSystemStatusMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function status(overrides: Partial<QuoteSystemStatusDetailWithPermissions> = {}): QuoteSystemStatusDetailWithPermissions {
  return {
    id: 7,
    name: 'Provisionally won',
    color: 'violet',
    behavior: 'validated',
    system_key: null,
    sort_order: 3,
    workflow_statuses_count: 4,
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateQuoteSystemStatusMock.mockReset()
})

describe('QuoteSystemStatusDetailView', () => {
  it('shows the fields, the linked working statuses count and the timestamp', () => {
    render(<QuoteSystemStatusDetailView quoteSystemStatus={status()} />)

    expect(screen.getByRole('heading', { name: 'Provisionally won' })).toBeInTheDocument()
    expect(screen.getByText('Violet')).toBeInTheDocument()
    expect(screen.getByText('Linked working statuses')).toBeInTheDocument()
    expect(screen.getByText('4')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateQuoteSystemStatusMock.mockResolvedValue(status({ name: 'Renamed' }))
    render(<QuoteSystemStatusDetailView quoteSystemStatus={status()} />)

    fireEvent.click(pencilOf('quoteSystemStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(updateQuoteSystemStatusMock).toHaveBeenCalledWith(7, { name: 'Renamed' }))
  })

  it('lets a normal row change its behavior', () => {
    render(<QuoteSystemStatusDetailView quoteSystemStatus={status()} />)

    expect(pencilOf('quoteSystemStatuses.form.behavior.label')).toBeInTheDocument()
  })

  it('keeps the behavior of a system row read-only, name and color stay editable', () => {
    render(<QuoteSystemStatusDetailView quoteSystemStatus={status({ system_key: 'closed_won', behavior: 'closed_won' })} />)

    expect(pencilOf('quoteSystemStatuses.form.name')).toBeInTheDocument()
    expect(pencilOf('quoteSystemStatuses.form.color')).toBeInTheDocument()
    expect(pencilOf('quoteSystemStatuses.form.behavior.label')).not.toBeInTheDocument()
    expect(screen.getByText('Won')).toBeInTheDocument()
  })

  it('maps a server 422 onto the open row', async () => {
    updateQuoteSystemStatusMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<QuoteSystemStatusDetailView quoteSystemStatus={status()} />)

    fireEvent.click(pencilOf('quoteSystemStatuses.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', () => {
    const base = status()
    render(
      <QuoteSystemStatusDetailView
        quoteSystemStatus={status({ permissions: { ...base.permissions, resource: { ...base.permissions.resource, update: false } } })}
      />,
    )

    expect(pencilOf('quoteSystemStatuses.form.name')).not.toBeInTheDocument()
    expect(pencilOf('quoteSystemStatuses.form.behavior.label')).not.toBeInTheDocument()
  })
})
