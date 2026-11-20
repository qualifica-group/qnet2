import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { IdentityDuplicateWarning } from '@/features/identity-duplicates/identity-duplicate-warning'
import type { IdentityDuplicateMatch } from '@/features/identity-duplicates/duplicate-check-api'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('IdentityDuplicateWarning (AC-007, AC-009)', () => {
  it('renders nothing without matches (AC-008)', () => {
    const { container } = render(<IdentityDuplicateWarning matches={[]} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('announces the matched referent and its criteria under role="status"', () => {
    const matches: IdentityDuplicateMatch[] = [
      { owner_type: 'referent', owner_id: 1, name: 'Mario Rossi', matched_on: ['email', 'tax_code'], registries: [] },
    ]
    render(<IdentityDuplicateWarning matches={matches} />)

    const status = screen.getByRole('status')
    expect(status).toHaveTextContent('Referent Mario Rossi might be a duplicate (email, tax code).')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('lists every match once, labelled by its owner kind, in the order returned by the API', () => {
    const matches: IdentityDuplicateMatch[] = [
      { owner_type: 'registry', owner_id: 1, name: 'Mario Rossi', matched_on: ['vat_number'], registries: [{ id: 1, name: 'Mario Rossi' }] },
      { owner_type: 'user', owner_id: 2, name: 'Anna Bianchi', matched_on: ['phone'], registries: [] },
    ]
    render(<IdentityDuplicateWarning matches={matches} />)

    // 'Registry', not 'Anagrafica': the English bundle used to leak the Italian
    // word while its siblings (User/Referent) were translated. The copy was the
    // defect, so the expectation moves with it.
    expect(screen.getByText('Registry Mario Rossi might be a duplicate (VAT number).')).toBeInTheDocument()
    expect(screen.getByText('User Anna Bianchi might be a duplicate (phone).')).toBeInTheDocument()
  })

  it('offers each anagrafica a match leads to as a button when a handler is given (task 427519)', async () => {
    const onSelectRegistry = vi.fn()
    const matches: IdentityDuplicateMatch[] = [
      {
        owner_type: 'referent',
        owner_id: 1,
        name: 'Mario Rossi',
        matched_on: ['phone'],
        registries: [{ id: 7, name: 'Alfa Srl' }],
      },
    ]
    render(<IdentityDuplicateWarning matches={matches} onSelectRegistry={onSelectRegistry} />)

    fireEvent.click(screen.getByRole('button', { name: 'Use Alfa Srl' }))

    expect(onSelectRegistry).toHaveBeenCalledWith({ id: 7, name: 'Alfa Srl' })
  })

  it('renders no button without a handler', () => {
    const matches: IdentityDuplicateMatch[] = [
      { owner_type: 'registry', owner_id: 7, name: 'Alfa Srl', matched_on: ['phone'], registries: [{ id: 7, name: 'Alfa Srl' }] },
    ]
    render(<IdentityDuplicateWarning matches={matches} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
