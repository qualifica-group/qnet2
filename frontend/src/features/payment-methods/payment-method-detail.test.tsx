import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PaymentMethodDetailView } from '@/features/payment-methods/payment-method-detail'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'

/**
 * Spec 0068, AC-107: the detail shows name, code (hero subtitle), the
 * fiscal code, description, payment_instructions, payment_days, the
 * installment rules, status and both timestamps. Wave 3 of the "no edit
 * pages" plan (spec 0236): every row edits in place, each save a PATCH of
 * that row's field(s) alone; the schedule (count + gap) and the two excluded
 * months are one row each, the extra days a row of their own (spec 0325). The Activity Log gate is exercised
 * on the `permissions.actions.view_activity` prop.
 */

const activityLogSectionMock = vi.fn()
const updatePaymentMethodMock = vi.fn()

vi.mock('@/features/payment-methods/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/payment-methods/api')>()),
  updatePaymentMethod: (...args: unknown[]) => updatePaymentMethodMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function paymentMethod(
  overrides: Partial<PaymentMethodDetailWithPermissions> = {},
): PaymentMethodDetailWithPermissions {
  return {
    id: 4,
    name: 'Bank transfer',
    code: 'bank_transfer',
    payment_method_code: 'MP05',
    description: 'Standard bank transfer',
    payment_instructions: 'Use the company IBAN',
    payment_days: 30,
    installments_count: 1,
    days_between_installments: 0,
    end_of_month: false,
    end_of_month_extra_days: null,
    vat_allocation: 'split',
    payment_condition: 'TP02',
    excluded_month_1: null,
    excluded_month_2: null,
    sort_order: 10,
    is_active: true,
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

const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updatePaymentMethodMock.mockReset()
})

describe('PaymentMethodDetailView — detail fields (AC-107)', () => {
  it('shows name, code, description, payment_instructions, payment_days, status and both timestamps', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    expect(screen.getByRole('heading', { name: 'Bank transfer' })).toBeInTheDocument()
    expect(screen.getByText('bank_transfer')).toBeInTheDocument()
    expect(screen.getByText('MP05')).toBeInTheDocument()
    expect(screen.getByText('Standard bank transfer')).toBeInTheDocument()
    expect(screen.getByText('Use the company IBAN')).toBeInTheDocument()
    expect(screen.getByText('30')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the em-dash placeholder on every nullable field left empty', () => {
    render(
      <PaymentMethodDetailView
        paymentMethod={paymentMethod({
          payment_method_code: null,
          description: null,
          payment_instructions: null,
          payment_days: null,
        })}
      />,
    )

    // REQUIREMENT CHANGED (spec 0325): the extra days row is always shown (null here).
    expect(screen.getAllByText('—')).toHaveLength(5)
  })

  it('shows the inactive status', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod({ is_active: false })} />)

    // REQUIREMENT CHANGED (spec 0325): end of month is a switch row too, so "No" twice.
    expect(screen.getAllByText('No', { selector: 'span' })).toHaveLength(2)
  })

  it('summarises the schedule, the payment terms and the excluded months on one row each', () => {
    render(
      <PaymentMethodDetailView
        paymentMethod={paymentMethod({
          installments_count: 3,
          days_between_installments: 30,
          payment_condition: 'TP01',
          excluded_month_1: 8,
          excluded_month_2: 12,
        })}
      />,
    )

    expect(screen.getByText('3 installments, one every 30 days')).toBeInTheDocument()
    expect(screen.getByText('TP01 - Installment payment')).toBeInTheDocument()
    expect(screen.getByText('August, December')).toBeInTheDocument()
  })
})

describe('PaymentMethodDetailView — activity log section', () => {
  it('mounts the section for the viewed payment method when view_activity is granted', () => {
    render(
      <PaymentMethodDetailView
        paymentMethod={paymentMethod({
          permissions: { ...paymentMethod().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'payment-methods', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('PaymentMethodDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed with its pencil, and code is not a row', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.name')).toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.installmentsCount')).toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.vatAllocation')).toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.code')).not.toBeInTheDocument()
  })

  it('Save sends only the changed field, never code', async () => {
    updatePaymentMethodMock.mockResolvedValue(paymentMethod({ name: 'Wire transfer' }))
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Wire transfer' } })
    saveRow()

    await waitFor(() => expect(updatePaymentMethodMock).toHaveBeenCalledWith(4, { name: 'Wire transfer' }))
  })

  it('clearing the fiscal code sends null, not an empty string', async () => {
    updatePaymentMethodMock.mockResolvedValue(paymentMethod({ payment_method_code: null }))
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.paymentMethodCode') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: '' } })
    saveRow()

    await waitFor(() => expect(updatePaymentMethodMock).toHaveBeenCalledWith(4, { payment_method_code: null }))
  })

  it('saves the schedule row as one PATCH of count and gap', async () => {
    updatePaymentMethodMock.mockResolvedValue(paymentMethod())
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.installmentsCount') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Number of installments/), { target: { value: '3' } })
    fireEvent.change(screen.getByLabelText(/^Days between installments/), { target: { value: '30' } })
    saveRow()

    await waitFor(() =>
      expect(updatePaymentMethodMock).toHaveBeenCalledWith(4, {
        installments_count: 3,
        days_between_installments: 30,
      }),
    )
  })

  it('does not save several installments without a gap, and says so on the row', async () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.installmentsCount') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Number of installments/), { target: { value: '3' } })
    fireEvent.change(screen.getByLabelText(/^Days between installments/), { target: { value: '' } })
    saveRow()

    expect(
      await screen.findByText('Days between installments are required with more than one installment.'),
    ).toBeInTheDocument()
    expect(updatePaymentMethodMock).not.toHaveBeenCalled()
  })

  // REQUIREMENT CHANGED (spec 0325 D-2): the extra days apply without end of month too, on their own row.
  it('saves the extra days without end of month', async () => {
    updatePaymentMethodMock.mockResolvedValue(paymentMethod())
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.endOfMonthExtraDays') as HTMLElement)
    fireEvent.change(screen.getByLabelText(/^Extra days on due date/), { target: { value: '16' } })
    saveRow()

    await waitFor(() => expect(updatePaymentMethodMock).toHaveBeenCalledWith(4, { end_of_month_extra_days: 16 }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updatePaymentMethodMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updatePaymentMethodMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    fireEvent.click(pencilOf('paymentMethods.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed, nor on a read-only field', () => {
    const readonly = { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false }
    const base = paymentMethod().permissions
    const { unmount } = render(
      <PaymentMethodDetailView
        paymentMethod={paymentMethod({ permissions: { ...base, resource: { ...base.resource, update: false } } })}
      />,
    )
    expect(pencilOf('paymentMethods.form.name')).not.toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.installmentsCount')).not.toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.vatAllocation')).not.toBeInTheDocument()
    unmount()

    render(<PaymentMethodDetailView paymentMethod={paymentMethod({ permissions: { ...base, fields: { name: readonly } } })} />)
    expect(pencilOf('paymentMethods.form.name')).not.toBeInTheDocument()
    expect(pencilOf('paymentMethods.form.paymentDays')).toBeInTheDocument()
  })
})
