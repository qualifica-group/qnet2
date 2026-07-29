import { fireEvent, render, waitFor, within } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { BadgeCell, ContactsCell, CountCell, TagsCountCell } from '@/features/table/cell-renderers'
import type { EnumBadge, PrimaryContact } from '@/features/table/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})
afterAll(async () => {
  await i18n.changeLanguage('it')
})

/**
 * BadgeCell is a generic, domain-agnostic renderer for `badge` columns: it maps
 * the row value to the backend-supplied badge metadata (label/color) and renders
 * a colored badge, or an em dash when the value has no matching metadata.
 */
const BADGES: EnumBadge[] = [
  { value: 'individual', label: 'Individual', color: 'blue', icon: 'user' },
  { value: 'company', label: 'Company', color: 'violet', icon: 'building' },
]

function renderBadge(value: unknown, badges: EnumBadge[] | undefined = BADGES) {
  const params = { value, badges } as unknown as ICellRendererParams
  return render(<BadgeCell {...params} />)
}

describe('BadgeCell', () => {
  it('renders the backend label for a known value', () => {
    const { getByText } = renderBadge('company')
    expect(getByText('Company')).toBeInTheDocument()
  })

  it('applies the color token mapped from the badge metadata', () => {
    const { getByText } = renderBadge('individual')
    // The blue token maps to a blue background utility class.
    expect(getByText('Individual').className).toContain('bg-blue-100')
  })

  it('renders an em dash when the value has no matching metadata', () => {
    const { getByText, queryByText } = renderBadge('unknown')
    expect(getByText('—')).toBeInTheDocument()
    expect(queryByText('Individual')).toBeNull()
  })

  it('renders an em dash when the value is null (no personal-data card)', () => {
    const { getByText } = renderBadge(null)
    expect(getByText('—')).toBeInTheDocument()
  })

  it('renders the backend type icon alongside the label', () => {
    const { getByText } = renderBadge('company')
    // The badge carries an inline SVG icon resolved from the `building` token.
    expect(getByText('Company').querySelector('svg')).not.toBeNull()
  })
})

/**
 * When the column declares an `enumKey`, the label is localized from the frontend
 * i18n resources (`enums.<enumKey>.<value>`) rather than the backend-supplied
 * label — the badge value/color/icon still come from the backend metadata.
 */
describe('BadgeCell with enumKey (i18n label)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })
  afterAll(async () => {
    await i18n.changeLanguage('en')
  })

  function renderWithKey(value: unknown, enumKey: string, badges = BADGES) {
    const params = { value, badges, enumKey } as unknown as ICellRendererParams
    return render(<BadgeCell {...params} />)
  }

  it('localizes the label from i18n, ignoring the backend label', () => {
    const { getByText, queryByText } = renderWithKey('company', 'personal_data_type')
    expect(getByText('Azienda')).toBeInTheDocument()
    expect(queryByText('Company')).toBeNull()
  })

  it('switches the localized label with the active language', async () => {
    await i18n.changeLanguage('en')
    const { getByText } = renderWithKey('individual', 'personal_data_type')
    expect(getByText('Individual')).toBeInTheDocument()
    await i18n.changeLanguage('it')
  })

  it('falls back to the raw value when the enum case is not translated', () => {
    const { getByText } = renderWithKey('company', 'unknown_enum')
    expect(getByText('company')).toBeInTheDocument()
  })
})

function renderCount(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<CountCell {...params} />)
}

describe('CountCell', () => {
  it('renders the numeric value inside a badge', () => {
    const { getByText } = renderCount(3)
    expect(getByText('3')).toBeInTheDocument()
  })

  it('renders zero (a role with no members) rather than an em dash', () => {
    const { getByText, queryByText } = renderCount(0)
    expect(getByText('0')).toBeInTheDocument()
    expect(queryByText('—')).toBeNull()
  })

  it('renders an em dash when the value is not a finite number', () => {
    const { getByText } = renderCount(null)
    expect(getByText('—')).toBeInTheDocument()
  })
})

function renderTagsCount(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<TagsCountCell {...params} />)
}

describe('TagsCountCell', () => {
  it('renders a single badge with the tag count, not the tags inline', () => {
    const { getByText, queryByText } = renderTagsCount(['Admin', 'Editor', 'Viewer'])
    expect(getByText('3')).toBeInTheDocument()
    expect(queryByText('Admin')).toBeNull()
  })

  it('exposes the full tag list via the badge accessible name (tooltip source)', () => {
    const { getByLabelText } = renderTagsCount(['Admin', 'Editor'])
    expect(getByLabelText('Admin, Editor')).toBeInTheDocument()
  })

  it('renders an em dash for an empty tag array', () => {
    expect(renderTagsCount([]).getByText('—')).toBeInTheDocument()
  })

  it('renders an em dash when the value is missing', () => {
    expect(renderTagsCount(undefined).getByText('—')).toBeInTheDocument()
  })
})

const CONTACTS: PrimaryContact[] = [
  { type: 'email', icon: 'mail', label: 'Work', value: 'work@example.com' },
  { type: 'phone', icon: 'phone', label: 'Mobile', value: '+39 333 1234567' },
]

function renderContacts(value: unknown) {
  const params = { value } as unknown as ICellRendererParams
  return render(<ContactsCell {...params} />)
}

describe('ContactsCell', () => {
  it('renders a single badge with the primary-contact count', () => {
    const { getByText, queryByText } = renderContacts(CONTACTS)
    expect(getByText('2')).toBeInTheDocument()
    expect(queryByText('Work')).toBeNull()
    expect(queryByText('Mobile')).toBeNull()
  })

  it('exposes the badge count via an accessible label', () => {
    const { getByLabelText } = renderContacts(CONTACTS)
    expect(getByLabelText('2 primary contacts')).toBeInTheDocument()
  })

  it('shows the full contact list in the tooltip', async () => {
    const { getByLabelText } = renderContacts(CONTACTS)
    fireEvent.focus(getByLabelText('2 primary contacts'))

    const tooltip = await waitFor(() => {
      const content = document.querySelector<HTMLElement>('[data-slot="tooltip-content"][data-state="instant-open"]')
      expect(content).not.toBeNull()
      return content!
    })
    const contactList = tooltip.querySelector<HTMLElement>('.flex.flex-col.divide-y')!
    expect(within(contactList).getByText('Work')).toBeInTheDocument()
    expect(within(contactList).getByText('work@example.com')).toBeInTheDocument()
    expect(within(contactList).getByText('Mobile')).toBeInTheDocument()
    expect(within(contactList).getByText('+39 333 1234567')).toBeInTheDocument()
  })

  it('renders one copy button per contact inside the tooltip', async () => {
    const { getByLabelText } = renderContacts(CONTACTS)
    fireEvent.focus(getByLabelText('2 primary contacts'))

    const tooltip = await waitFor(() => {
      const content = document.querySelector<HTMLElement>('[data-slot="tooltip-content"][data-state="instant-open"]')
      expect(content).not.toBeNull()
      return content!
    })
    const contactList = tooltip.querySelector<HTMLElement>('.flex.flex-col.divide-y')!
    expect(within(contactList).getAllByRole('button', { name: 'Copy' })).toHaveLength(2)
  })

  it('renders an em dash for an empty contact array', () => {
    expect(renderContacts([]).getByText('—')).toBeInTheDocument()
  })

  it('renders an em dash when the value is missing', () => {
    expect(renderContacts(undefined).getByText('—')).toBeInTheDocument()
  })
})
