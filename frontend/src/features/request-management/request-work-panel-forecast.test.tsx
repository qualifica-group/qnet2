import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { FULL_PERMISSIONS, workPanel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * "Previsione commerciale" in the work panel (spec 0313, AC-006): the
 * Opportunity's forecast trio, editable here and no longer read-only in the
 * summary; a change travels alone in the sparse PATCH.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: () => null,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('@/features/field-change-requests/api', () => ({
  fetchFieldChangeRequestsForRecord: () => Promise.resolve([]),
  approveFieldChangeRequest: vi.fn(),
  rejectFieldChangeRequest: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderPanel() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={4001} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function forecastSection() {
  const heading = await screen.findByText('Sales forecast')
  return heading.closest('section') ?? document.body
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('RequestWorkPanelScreen — sales forecast (spec 0313)', () => {
  it('hydrates the three controls from the Opportunity values', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      workPanel({ estimated_value: '1234.50', expected_close_date: '2026-08-01', success_probability: 40 }),
    )

    renderPanel()
    const section = within(await forecastSection())

    expect(section.getByLabelText('Expected close date')).toHaveValue('2026-08-01')
    expect(section.getByLabelText('Estimated value')).toHaveValue(1234.5)
    expect(section.getByRole('slider', { name: 'Success probability (%)' })).toHaveAttribute('aria-valuenow', '40')
    expect(screen.queryByText('Read-only commercial context.')?.closest('section')).not.toHaveTextContent(
      'Estimated value',
    )
  })

  it('sends only the forecast key that changed', async () => {
    const stored = workPanel({ estimated_value: '1234.50', expected_close_date: null, success_probability: null })
    fetchRequestWorkPanelMock.mockResolvedValue(stored)
    updateRequestWorkMock.mockResolvedValue(stored)

    renderPanel()
    const section = within(await forecastSection())

    fireEvent.change(section.getByLabelText('Estimated value'), { target: { value: '2000' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalled())
    expect(updateRequestWorkMock.mock.calls[0][2]).toEqual({ estimated_value: 2000 })
  })

  it('locks a forecast field the role matrix made read-only', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(
      workPanel({
        permissions: {
          ...FULL_PERMISSIONS,
          fields: {
            estimated_value: {
              visible: true,
              hidden: false,
              editable: false,
              readonly: true,
              required: false,
              disabled: false,
            },
          },
        },
      }),
    )

    renderPanel()
    const section = within(await forecastSection())

    expect(section.getByLabelText('Estimated value')).toBeDisabled()
    expect(section.getByLabelText('Expected close date')).toBeEnabled()
  })
})
