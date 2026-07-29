import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import {
  commissionConfigurationColumnRenderers,
  formatCommissionValue,
} from './column-renderers'

beforeAll(async () => i18n.changeLanguage('en'))

describe('commission configuration column renderers', () => {
  it('formats fixed, percentage and invalid values', () => {
    expect(formatCommissionValue('12.5', 'PERCENTAGE')).toBe('12.5%')
    expect(formatCommissionValue(12.5, 'FIXED_AMOUNT')).toBe('12.50')
    expect(formatCommissionValue(null, 'PERCENTAGE')).toBe('0%')
    expect(formatCommissionValue('invalid', 'PERCENTAGE')).toBe('')
  })

  it.each([
    ['recipient_role', 'COMMERCIAL', 'Commercial'],
    ['application_scope', 'PRODUCT', 'Product'],
    ['commission_type', 'FIXED_AMOUNT', 'Fixed amount'],
    ['status', 'ACTIVE', 'Active'],
  ])('renders localized %s values', (key, value, label) => {
    const Renderer = commissionConfigurationColumnRenderers[key]
    render(<>{Renderer({ value } as never)}</>)
    expect(screen.getByText(label)).toBeInTheDocument()
  })

  it('renders empty enum and numeric cells safely', () => {
    const Role = commissionConfigurationColumnRenderers.recipient_role
    const Priority = commissionConfigurationColumnRenderers.priority
    render(<>{Role({ value: null } as never)}{Priority({ value: null } as never)}</>)
    expect(screen.getByText('–')).toBeInTheDocument()
  })
})
