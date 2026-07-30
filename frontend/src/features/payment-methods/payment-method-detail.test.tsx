import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { PaymentMethodDetailView } from '@/features/payment-methods/payment-method-detail'
import type { PaymentMethodDetailWithPermissions } from '@/features/payment-methods/types'

/**
 * Spec 0068, AC-107: the detail shows name, code, description,
 * payment_instructions, payment_days, status and both timestamps, with the
 * shared detail kit's placeholder on the nullable fields. Purely
 * presentational (the caller fetches and passes the detail down), so the
 * Activity Log section gate is exercised directly on the
 * `permissions.actions.view_activity` prop (mirrors `RewardTypeDetailView`'s
 * suite).
 */

const activityLogSectionMock = vi.fn()

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
    description: 'Standard bank transfer',
    payment_instructions: 'Use the company IBAN',
    payment_days: 30,
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('PaymentMethodDetailView — detail fields (AC-107)', () => {
  it('shows name, code, description, payment_instructions, payment_days, status and both timestamps', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod()} />)

    expect(screen.getByRole('heading', { name: 'Bank transfer' })).toBeInTheDocument()
    expect(screen.getByText('bank_transfer')).toBeInTheDocument()
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
          description: null,
          payment_instructions: null,
          payment_days: null,
        })}
      />,
    )

    expect(screen.getAllByText('—')).toHaveLength(3)
  })

  it('shows the inactive status', () => {
    render(<PaymentMethodDetailView paymentMethod={paymentMethod({ is_active: false })} />)

    expect(screen.getByText('No')).toBeInTheDocument()
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
