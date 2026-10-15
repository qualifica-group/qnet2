import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { AgentFiscalProfileDetailView } from '@/features/agent-fiscal-profiles/agent-fiscal-profile-detail'
import type { AgentFiscalProfileDetailWithPermissions } from '@/features/agent-fiscal-profiles/types'

/** Spec 0264 D-4: every row of the profile edits in place, each save a PATCH of that row's fields alone. */

const updateMock = vi.fn()

vi.mock('@/features/agent-fiscal-profiles/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/agent-fiscal-profiles/api')>()),
  updateAgentFiscalProfile: (...args: unknown[]) => updateMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function profile(overrides: Partial<AgentFiscalProfileDetailWithPermissions> = {}): AgentFiscalProfileDetailWithPermissions {
  return {
    id: 9,
    referent_id: 5,
    referent: { id: 5, name: 'Mario Rossi' },
    withholding_enabled: true,
    withholding_rate: '23.00',
    withholding_base_percent: '50.00',
    enasarco_enabled: false,
    enasarco_mandate_type: null,
    vat_rate_id: null,
    vat_rate: null,
    payment_method_id: null,
    payment_method: null,
    cost_type_id: null,
    cost_type: null,
    valid_from: '2026-01-01',
    valid_until: null,
    notes: null,
    created_at: '2026-10-12T08:00:00Z',
    updated_at: '2026-10-12T08:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: false },
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

const pencilOf = (label: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) })
const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateMock.mockReset()
})

describe('AgentFiscalProfileDetailView - in-place editing (spec 0264)', () => {
  it('shows the closed rows, with no Edit button', () => {
    render(<AgentFiscalProfileDetailView profile={profile()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.getByText('23.00% on 50.00% of the taxable amount')).toBeInTheDocument()
    expect(screen.getByText('No VAT')).toBeInTheDocument()
    expect(pencilOf('Validity')).toBeInTheDocument()
    expect(pencilOf('Notes')).toBeInTheDocument()
  })

  it('shows the payment method and cost type defaults on their rows (spec 0267 D-8)', () => {
    render(
      <AgentFiscalProfileDetailView
        profile={profile({
          payment_method_id: 3,
          payment_method: { id: 3, name: 'Bank transfer' },
          cost_type_id: 8,
          cost_type: { id: 8, name: 'Agent commissions' },
        })}
      />,
    )

    expect(screen.getByText('Payment method')).toBeInTheDocument()
    expect(screen.getByText('Bank transfer')).toBeInTheDocument()
    expect(screen.getByText('Cost type')).toBeInTheDocument()
    expect(screen.getByText('Agent commissions')).toBeInTheDocument()
  })

  it('a row sends only its own field', async () => {
    updateMock.mockResolvedValue(profile({ notes: 'ok' }))
    render(<AgentFiscalProfileDetailView profile={profile()} />)

    fireEvent.click(pencilOf('Notes') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'ok' } })
    saveRow()

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(9, { notes: 'ok' }))
  })

  it('switching the withholding off saves the switch and clears both rates together', async () => {
    updateMock.mockResolvedValue(profile())
    render(<AgentFiscalProfileDetailView profile={profile()} />)

    fireEvent.click(pencilOf('Withholding') as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /^Apply withholding tax/ }))
    saveRow()

    await waitFor(() =>
      expect(updateMock).toHaveBeenCalledWith(9, {
        withholding_enabled: false,
        withholding_rate: null,
        withholding_base_percent: null,
      }),
    )
  })

  it('Cancel restores the row without calling the API', () => {
    render(<AgentFiscalProfileDetailView profile={profile()} />)

    fireEvent.click(pencilOf('Notes') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'draft' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('draft')).not.toBeInTheDocument()
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('maps the server overlapping-validity 422 onto the open validity row', async () => {
    const overlapping = 'The validity overlaps another profile of this agent.'
    updateMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { valid_from: [overlapping] } },
      } as never),
    )
    render(<AgentFiscalProfileDetailView profile={profile()} />)

    fireEvent.click(pencilOf('Validity') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Valid from/), { target: { value: '2025-06-01' } })
    saveRow()

    const message = await screen.findByText(overlapping)
    expect(message).toHaveAttribute('role', 'alert')
    expect(screen.getByLabelText(/^Valid from/)).toHaveAttribute('aria-invalid', 'true')
  })

  it('offers no pencil without the update permission', () => {
    const base = profile().permissions
    render(
      <AgentFiscalProfileDetailView profile={profile({ permissions: { ...base, resource: { ...base.resource, update: false } } })} />,
    )

    expect(pencilOf('Notes')).not.toBeInTheDocument()
    expect(pencilOf('Withholding')).not.toBeInTheDocument()
  })
})
