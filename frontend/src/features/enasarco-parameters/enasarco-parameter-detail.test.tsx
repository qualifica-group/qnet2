import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { EnasarcoParameterDetailView } from '@/features/enasarco-parameters/enasarco-parameter-detail'
import type { EnasarcoParameterDetailWithPermissions } from '@/features/enasarco-parameters/types'

/** Spec 0264 D-5: every parameter of the year edits in place, each save a PATCH of that field alone. */

const updateMock = vi.fn()

vi.mock('@/features/enasarco-parameters/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/enasarco-parameters/api')>()),
  updateEnasarcoParameter: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function parameter(overrides: Partial<EnasarcoParameterDetailWithPermissions> = {}): EnasarcoParameterDetailWithPermissions {
  return {
    id: 3,
    year: 2026,
    total_rate: '17.00',
    agent_share_percent: '50.00',
    single_firm_ceiling: '13000.00',
    multi_firm_ceiling: '26000.00',
    notes: null,
    created_at: '2026-01-02T08:00:00Z',
    updated_at: '2026-01-02T08:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (label: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })
const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('EnasarcoParameterDetailView - in-place editing (spec 0264)', () => {
  it('shows the closed rows, with no Edit button', () => {
    render(<EnasarcoParameterDetailView parameter={parameter()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.getByText('17.00%')).toBeInTheDocument()
    expect(pencilOf('Single-firm ceiling')).toBeInTheDocument()
  })

  it('a row sends only its own field', async () => {
    updateMock.mockResolvedValue(parameter({ single_firm_ceiling: '14000.00' }))
    render(<EnasarcoParameterDetailView parameter={parameter()} />)

    fireEvent.click(pencilOf('Single-firm ceiling') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Single-firm ceiling/), { target: { value: '14000' } })
    saveRow()

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(3, { single_firm_ceiling: 14000 }))
  })

  it('maps a duplicated year 422 onto the open row', async () => {
    updateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { year: ['The year has already been taken.'] } },
      } as never),
    )
    render(<EnasarcoParameterDetailView parameter={parameter()} />)

    fireEvent.click(pencilOf('Year') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Year/), { target: { value: '2025' } })
    saveRow()

    expect(await screen.findByText('The year has already been taken.')).toHaveAttribute('role', 'alert')
  })

  it('offers no pencil without the update permission', () => {
    const base = parameter().permissions
    render(
      <EnasarcoParameterDetailView parameter={parameter({ permissions: { ...base, resource: { ...base.resource, update: false } } })} />,
    )

    expect(pencilOf('Year')).not.toBeInTheDocument()
  })
})
