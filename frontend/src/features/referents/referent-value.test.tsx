import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ReferentValue } from '@/features/referents/referent-value'

/**
 * The "Referente" row of the Opportunita', Offerta and Attivita' records: the
 * link to the referent with its Tipo referente under the name.
 */

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true }),
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderValue(ui: React.ReactElement) {
  return render(<MemoryRouter>{ui}</MemoryRouter>)
}

describe('ReferentValue', () => {
  it('shows the referent link with its referent type, labelled for screen readers', () => {
    renderValue(<ReferentValue referent={{ id: 4, name: 'Ada Alberti', referent_type: { id: 2, name: 'Owner' } }} />)

    expect(screen.getByRole('link', { name: 'Ada Alberti' })).toBeInTheDocument()
    expect(screen.getByText('Owner')).toBeInTheDocument()
    expect(screen.getByText('Referent type:')).toHaveClass('sr-only')
  })

  it('shows only the link when the referent has no type (or the draft does not carry it)', () => {
    renderValue(<ReferentValue referent={{ id: 4, name: 'Ada Alberti' }} />)

    expect(screen.getByRole('link', { name: 'Ada Alberti' })).toBeInTheDocument()
    expect(screen.queryByText('Referent type:')).not.toBeInTheDocument()
  })

  it('task 428287: marks the anagrafica primary referent and makes its primary phone callable', () => {
    renderValue(
      <ReferentValue
        referent={{
          id: 4,
          name: 'Christian Taeggi',
          is_registry_primary: true,
          primary_contacts: [{ type: 'phone', icon: 'phone', label: 'Phone', value: '339 1838293' }],
        }}
      />,
    )

    expect(screen.getByText('Primary referent of the registry')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /339 1838293/ })).toHaveAttribute('href', 'tel:3391838293')
  })

  it('shows neither the note nor contacts for an offer own referent without contacts', () => {
    renderValue(<ReferentValue referent={{ id: 4, name: 'Ada Alberti', is_registry_primary: false, primary_contacts: [] }} />)

    expect(screen.queryByText('Primary referent of the registry')).not.toBeInTheDocument()
    expect(screen.getAllByRole('link')).toHaveLength(1)
  })

  it('renders the empty marker without a referent', () => {
    renderValue(<ReferentValue referent={null} />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
})
