import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { WorkOrderClientInfoSection } from '@/features/work-orders/work-order-client-info-section'
import type { WorkOrderClientInfo } from '@/features/work-orders/types'

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

/** Spec 0400: the legacy "Info cliente" block on the commessa detail. */

const REGISTRY = { id: 7, name: 'Acme S.p.A.' }

function info(overrides: Partial<WorkOrderClientInfo> = {}): WorkOrderClientInfo {
  return {
    legal_address: 'Via Legale 9 - 20100 Milano',
    vat_number: '01234567890',
    tax_code: 'RSSMRA80A01H501U',
    tags: ['Edilizia', 'VIP'],
    emails: [
      { type: 'email', icon: 'mail', label: 'Email', value: 'info@acme.it' },
      { type: 'pec', icon: 'shield-check', label: 'PEC', value: 'acme@pec.it' },
    ],
    phones: [{ type: 'phone', icon: 'phone', label: 'Telefono', value: '+39 02 111111' }],
    referents: [{ id: 15, name: 'Mario Bianchi', referent_type: null, primary_contacts: [] }],
    commercial: { id: 30, name: 'Agente Rossi' },
    sectors: ['EA 28 — Costruzioni'],
    comments: 'Cliente storico',
    ...overrides,
  }
}

function renderSection(value: WorkOrderClientInfo) {
  return render(
    <MemoryRouter>
      <WorkOrderClientInfoSection registry={REGISTRY} info={value} />
    </MemoryRouter>,
  )
}

/** The value cell of the row labelled `label`. */
function row(label: string): HTMLElement {
  return screen.getByText(label, { selector: 'dt' }).nextElementSibling as HTMLElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('WorkOrderClientInfoSection', () => {
  it('shows every legacy row in order', () => {
    renderSection(info())

    const labels = screen.getAllByRole('term').map((term) => term.textContent)
    expect(labels).toEqual([
      'Company',
      'Registered office',
      'Tags',
      'Email/PEC',
      'Phone',
      'Referent 1',
      'Referent 2',
      'Commercial',
      'Sector',
      'Comments',
    ])
  })

  it('links the company and shows the legal seat with its fiscal ids', () => {
    renderSection(info())

    expect(within(row('Company')).getByRole('link', { name: 'Acme S.p.A.' })).toHaveAttribute('href', '/registries/7')
    expect(within(row('Registered office')).getByText('Via Legale 9 - 20100 Milano')).toBeInTheDocument()
    expect(within(row('Registered office')).getByText('VAT no. 01234567890 - Tax code RSSMRA80A01H501U')).toBeInTheDocument()
  })

  it('renders email, PEC and phone as actionable contacts', () => {
    renderSection(info())

    expect(within(row('Email/PEC')).getByRole('link', { name: /info@acme\.it/ })).toHaveAttribute('href', 'mailto:info@acme.it')
    expect(within(row('Email/PEC')).getByRole('link', { name: /acme@pec\.it/ })).toBeInTheDocument()
    expect(within(row('Phone')).getByRole('link', { name: /\+39 02 111111/ })).toBeInTheDocument()
  })

  it('shows tags, referents, commercial, sector and comments, Referent 2 empty when missing', () => {
    renderSection(info())

    expect(within(row('Tags')).getByText('VIP')).toBeInTheDocument()
    expect(within(row('Referent 1')).getByRole('link', { name: 'Mario Bianchi' })).toBeInTheDocument()
    expect(within(row('Referent 2')).queryByRole('link')).not.toBeInTheDocument()
    expect(within(row('Commercial')).getByRole('link', { name: 'Agente Rossi' })).toBeInTheDocument()
    expect(within(row('Sector')).getByText('EA 28 — Costruzioni')).toBeInTheDocument()
    expect(within(row('Comments')).getByText('Cliente storico')).toBeInTheDocument()
  })

  it('keeps each referent in its slot: Referent 2 without Referent 1 (spec 0408 D-5)', () => {
    renderSection(info({ referents: [null, { id: 16, name: 'Pasqualina Pagano', referent_type: null, primary_contacts: [] }] }))

    expect(within(row('Referent 1')).queryByRole('link')).not.toBeInTheDocument()
    expect(within(row('Referent 2')).getByRole('link', { name: 'Pasqualina Pagano' })).toBeInTheDocument()
  })

  it('keeps the rows with the empty marker when the client has nothing to show', () => {
    renderSection(
      info({ legal_address: null, vat_number: null, tax_code: null, tags: [], emails: [], phones: [], referents: [null, null], commercial: null, sectors: [], comments: null }),
    )

    for (const label of ['Registered office', 'Tags', 'Email/PEC', 'Phone', 'Referent 1', 'Commercial', 'Sector', 'Comments']) {
      expect(within(row(label)).queryByRole('link')).not.toBeInTheDocument()
      expect(within(row(label)).queryByRole('listitem')).not.toBeInTheDocument()
    }
  })
})
