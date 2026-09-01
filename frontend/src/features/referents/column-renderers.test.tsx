import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { referentColumnRenderers } from '@/features/referents/column-renderers'

function renderCell(columnId: string, value: unknown) {
  const renderer = referentColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('referentColumnRenderers.referent_type', () => {
  it('renders the hydrated type name', () => {
    renderCell('referent_type', { id: 3, name: 'Sponsor' })
    expect(screen.getByText('Sponsor')).toBeInTheDocument()
  })

  it('renders an em dash when the referent has no type', () => {
    renderCell('referent_type', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('referentColumnRenderers.contact_scope', () => {
  it('renders a localized badge for "internal"', () => {
    renderCell('contact_scope', 'internal')
    expect(screen.getByText('Internal')).toBeInTheDocument()
  })

  it('renders a localized badge for "external"', () => {
    renderCell('contact_scope', 'external')
    expect(screen.getByText('External')).toBeInTheDocument()
  })

  it('renders an em dash for an unknown value', () => {
    renderCell('contact_scope', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('referentColumnRenderers.user', () => {
  it('renders the linked user name', () => {
    renderCell('user', 'Mario Rossi')
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
  })

  it('renders an em dash when the referent has no linked user', () => {
    renderCell('user', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('referentColumnRenderers.primary_contact', () => {
  it('reuses the shared ContactsCell: a count badge for the primary contacts', () => {
    renderCell('primary_contact', [
      { type: 'email', icon: 'mail', label: 'Work', value: 'ada@example.com' },
      { type: 'phone', icon: 'phone', label: 'Mobile', value: '+39 333 1234567' },
    ])
    expect(screen.getByLabelText('2 primary contacts')).toBeInTheDocument()
  })

  it('renders an em dash when the referent has no primary contact', () => {
    renderCell('primary_contact', [])
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
