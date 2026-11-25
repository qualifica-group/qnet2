import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { SupplierDirectionExplanation } from './supplier-direction-explanation'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('SupplierDirectionExplanation', () => {
  it('explains only the chosen direction', () => {
    render(<SupplierDirectionExplanation value="PAID" />)
    expect(screen.getByText(/^Paid: we pay the commission to the supplier/)).toBeInTheDocument()
    expect(screen.queryByText(/^Received:/)).not.toBeInTheDocument()
  })

  it('explains both directions while none is chosen', () => {
    render(<SupplierDirectionExplanation />)
    expect(screen.getByText(/^Received: we collect the commission/)).toBeInTheDocument()
    expect(screen.getByText(/^Paid: we pay the commission/)).toBeInTheDocument()
  })
})
