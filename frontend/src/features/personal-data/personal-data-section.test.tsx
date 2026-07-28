import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { PersonalDataSection } from '@/features/personal-data/personal-data-section'
import type { EnumOption } from '@/features/config/types'
import type {
  AddressDraft,
  ContactDraft,
  PersonalDataDraft,
  PersonalDataFieldPermission,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

/** `ContactsManager`/`AddressesManager` call `useConfirm()`, so every render needs the provider. */
function renderSection(ui: ReactElement) {
  // The card's comune-of-birth lookup runs through TanStack Query: one client
  // per render keeps each test's cache isolated.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

/**
 * Spec 0008 acceptance criteria (frontend): AC-011 (per-field/section gating
 * via the injected resolver) and AC-013 (no resolver = today's ungated
 * behaviour, required for the self-service profile form's regression safety —
 * exercised separately by `profile-form.test.tsx`, unmodified by this spec).
 */

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [
    { value: 'email', label: 'Email', color: null, icon: null, is_default: true, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'contact-1',
    id: 1,
    type: 'email',
    label: 'Work',
    value: 'ada@example.com',
    is_primary: true,
    ...overrides,
  }
}

function address(overrides: Partial<AddressDraft> = {}): AddressDraft {
  return {
    _key: 'address-1',
    id: 1,
    line1: '221B Baker Street',
    line2: null,
    postal_code: null,
    city_id: null,
    province_id: null,
    state_id: null,
    country_id: null,
    site_type: null,
    is_primary: true,
    ...overrides,
  }
}

function draft(overrides: Partial<PersonalDataDraft> = {}): PersonalDataDraft {
  return {
    type: 'individual',
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: null,
    contacts: [contact()],
    addresses: [address()],
    ...overrides,
  }
}

/** Builds a resolver returning the permissive default, overridden per key. */
function resolverFrom(
  overrides: Record<string, Partial<PersonalDataFieldPermission>>,
): PersonalDataFieldPermissionResolver {
  return (key) => ({
    visible: true,
    editable: true,
    required: false,
    disabled: false,
    readonly: false,
    ...overrides[key],
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PersonalDataSection — field-permission gating (spec 0008)', () => {
  it('AC-013 — without a resolver, every field and section renders exactly as today', () => {
    renderSection(<PersonalDataSection value={draft()} onChange={() => {}} />)

    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Last name/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add contact' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add address' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit contact' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit address' })).toBeInTheDocument()
  })

  it('AC-011 — a card field with visible:false is not rendered', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.first_name': { visible: false },
        })}
      />,
    )

    expect(screen.queryByLabelText(/^First name/)).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^Last name/)).toBeInTheDocument()
  })

  it('AC-011 — a card field with editable:false renders disabled', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.first_name': { editable: false },
        })}
      />,
    )

    expect(screen.getByLabelText(/^First name/)).toBeDisabled()
    expect(screen.getByLabelText(/^Last name/)).toBeEnabled()
  })

  it('AC-011 — required reflects the resolved flag (both directions)', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          // Normally hardcoded required in the card UI: the resolver overrides it.
          'personal_data.first_name': { required: false },
          // Normally not required: the resolver can flag it required instead.
          'personal_data.tax_code': { required: true },
        })}
      />,
    )

    const firstNameLabel = screen.getByText(
      (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('First name') === true,
    )
    const taxCodeLabel = screen.getByText(
      (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('Tax code') === true,
    )
    expect(firstNameLabel.textContent).not.toContain('*')
    expect(taxCodeLabel.textContent).toContain('*')
  })

  it('AC-011 — a hidden contacts section is not rendered', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.contacts': { visible: false },
        })}
      />,
    )

    expect(screen.queryByText('Contacts')).not.toBeInTheDocument()
    expect(screen.getByText('Addresses')).toBeInTheDocument()
  })

  it('AC-011 — a non-editable contacts section is read-only: no add/edit/delete', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.contacts': { editable: false },
        })}
      />,
    )

    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add contact' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit contact' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete contact' })).not.toBeInTheDocument()
  })

  it('AC-011 — a hidden addresses section is not rendered', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.addresses': { visible: false },
        })}
      />,
    )

    expect(screen.queryByText('Addresses')).not.toBeInTheDocument()
    expect(screen.getByText('Contacts')).toBeInTheDocument()
  })

  it('AC-011 — a non-editable addresses section is read-only: no add/edit/delete', () => {
    renderSection(
      <PersonalDataSection
        value={draft()}
        onChange={() => {}}
        fieldPermission={resolverFrom({
          'personal_data.addresses': { editable: false },
        })}
      />,
    )

    expect(screen.getByText('221B Baker Street')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add address' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit address' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete address' })).not.toBeInTheDocument()
  })
})
