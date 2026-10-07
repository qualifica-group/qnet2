import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { SupplierCommissionDirectionCell } from '@/features/product-typologies/supplier-commission-direction-cell'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('SupplierCommissionDirectionCell (spec 0202)', () => {
  it.each([
    ['RECEIVED', 'Received'],
    ['PAID', 'Paid'],
  ])('renders %s as a badge', (value, label) => {
    render(<SupplierCommissionDirectionCell value={value} />)
    expect(screen.getByText(label)).toBeInTheDocument()
  })

  it('renders a dash when the direction is null', () => {
    render(<SupplierCommissionDirectionCell value={null} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
