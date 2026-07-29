import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { CommissionConfigurationDetailView } from './commission-configuration-detail'
import type { CommissionConfigurationDetailWithPermissions } from './types'

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity log</div>,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('CommissionConfigurationDetailView permissions', () => {
  it('does not expose hidden fields or render their empty sections', () => {
    const hidden = {
      visible: false,
      hidden: true,
      editable: false,
      readonly: false,
      required: false,
      disabled: true,
    }
    const configuration: CommissionConfigurationDetailWithPermissions = {
      id: 1,
      name: 'Secret commission rule',
      recipient_role: 'COMMERCIAL',
      application_scope: 'PRODUCT',
      product_category_id: null,
      product_category: null,
      product_id: 9,
      product: { id: 9, name: 'Secret product' },
      commission_type: 'PERCENTAGE',
      value: '12.5000',
      priority: 99,
      valid_from: '2026-07-29',
      valid_until: null,
      status: 'ACTIVE',
      internal_note: 'Secret internal note',
      created_at: '2026-07-29T10:00:00Z',
      updated_at: '2026-07-29T10:00:00Z',
      permissions: {
        resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
        actions: { view_activity: false },
        fields: {
          name: hidden,
          recipient_role: hidden,
          application_scope: hidden,
          product_id: hidden,
          commission_type: hidden,
          value: hidden,
          priority: hidden,
          status: hidden,
          valid_from: hidden,
          valid_until: hidden,
          internal_note: hidden,
          updated_at: hidden,
        },
      },
    }

    render(<CommissionConfigurationDetailView configuration={configuration} />)

    expect(screen.getByText('Commission configuration')).toBeInTheDocument()
    expect(screen.queryByText('Secret commission rule')).not.toBeInTheDocument()
    expect(screen.queryByText('Secret product')).not.toBeInTheDocument()
    expect(screen.queryByText('Secret internal note')).not.toBeInTheDocument()
    expect(screen.queryByText('Recipient and scope')).not.toBeInTheDocument()
    expect(screen.queryByText('Calculation')).not.toBeInTheDocument()
    expect(screen.queryByText('Validity and notes')).not.toBeInTheDocument()
  })

  it.each([
    {
      scope: 'PRODUCT' as const,
      product: { id: 9, name: 'Router Pro' },
      product_category: null,
      type: 'PERCENTAGE' as const,
      value: '12.5000',
      status: 'ACTIVE' as const,
      expectedRelation: 'Router Pro',
      expectedValue: '12.5%',
    },
    {
      scope: 'PRODUCT_CATEGORY' as const,
      product: null,
      product_category: { id: 4, name: 'Networking' },
      type: 'FIXED_AMOUNT' as const,
      value: '25',
      status: 'SUSPENDED' as const,
      expectedRelation: 'Networking',
      expectedValue: '25.00',
    },
  ])('renders a complete visible $scope rule', ({
    scope,
    product,
    product_category,
    type,
    value,
    status,
    expectedRelation,
    expectedValue,
  }) => {
    const configuration = {
      id: 2,
      name: 'Visible rule',
      recipient_role: 'SUPPLIER',
      application_scope: scope,
      product_category_id: product_category?.id ?? null,
      product_category,
      product_id: product?.id ?? null,
      product,
      commission_type: type,
      value,
      priority: 3,
      valid_from: '2026-07-29',
      valid_until: null,
      status,
      internal_note: null,
      created_at: '2026-07-29T10:00:00Z',
      updated_at: '2026-07-29T10:00:00Z',
      permissions: {
        resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
        actions: { view_activity: true },
        fields: {},
      },
    } satisfies CommissionConfigurationDetailWithPermissions

    render(<CommissionConfigurationDetailView configuration={configuration} />)

    expect(screen.getByText('Visible rule')).toBeInTheDocument()
    expect(screen.getByText(expectedRelation)).toBeInTheDocument()
    expect(screen.getByText(expectedValue)).toBeInTheDocument()
    expect(screen.getAllByText('–').length).toBeGreaterThan(0)
    expect(screen.getByText('activity log')).toBeInTheDocument()
    expect(screen.getByText('Last updated')).toBeInTheDocument()
  })
})
