import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RoleFieldPermissions } from '@/features/roles/role-field-permissions'
import type { PermissionCatalogueField } from '@/features/roles/permission-catalogue-api'

/**
 * Spec 0008 AC-010: the `users` module's catalogue carries the
 * `personal_data.*` keys (`GET /api/authorization/permission-catalogue`,
 * spec 0076) — the matrix must render one row per key, each with a readable
 * label and three toggles, alongside the pre-existing account field.
 * `mandatory` (spec 0008 follow-up) locks a row's three checkboxes to
 * checked+disabled; values below mirror the coordinator's realistic
 * contract (email/type/first_name/last_name/company_name mandatory, the
 * rest not).
 */

const USERS_FIELDS: PermissionCatalogueField[] = [
  { key: 'email', type: 'email', group: null, mandatory: true, custom: false, label: null },
  { key: 'personal_data.type', type: 'select', group: 'personal_data', mandatory: true, custom: false, label: null },
  { key: 'personal_data.first_name', type: 'text', group: 'personal_data', mandatory: true, custom: false, label: null },
  { key: 'personal_data.last_name', type: 'text', group: 'personal_data', mandatory: true, custom: false, label: null },
  { key: 'personal_data.company_name', type: 'text', group: 'personal_data', mandatory: true, custom: false, label: null },
  { key: 'personal_data.tax_code', type: 'text', group: 'personal_data', mandatory: false, custom: false, label: null },
  { key: 'personal_data.vat_number', type: 'text', group: 'personal_data', mandatory: false, custom: false, label: null },
  { key: 'personal_data.sdi_code', type: 'text', group: 'personal_data', mandatory: false, custom: false, label: null },
  { key: 'personal_data.birth_date', type: 'date', group: 'personal_data', mandatory: false, custom: false, label: null },
  { key: 'personal_data.contacts', type: 'collection', group: 'personal_data', mandatory: false, custom: false, label: null },
  { key: 'personal_data.addresses', type: 'collection', group: 'personal_data', mandatory: false, custom: false, label: null },
]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RoleFieldPermissions — personal_data.* rows (spec 0008 AC-010)', () => {
  it('renders one row with three toggles for every personal_data.* key, with a readable label', () => {
    render(
      <RoleFieldPermissions resource="users" fields={USERS_FIELDS} value={[]} onToggle={() => {}} disabled={false} />,
    )

    // Readable labels, not raw dot-path tokens.
    expect(screen.getByText('First name')).toBeInTheDocument()
    expect(screen.getByText('Contacts')).toBeInTheDocument()
    expect(screen.getByText('Addresses')).toBeInTheDocument()

    // Three toggles for a NON-mandatory new key (default: unrestricted, toggable).
    expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Visible' })).toBeEnabled()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Editable' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Editable' })).toBeEnabled()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Required' })).not.toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'Tax code — Required' })).toBeEnabled()

    // Every catalogue field renders (all native — no custom group heading).
    expect(screen.getAllByRole('checkbox')).toHaveLength(USERS_FIELDS.length * 3)
    expect(screen.queryByText('Custom')).not.toBeInTheDocument()
  })

  it('locks a mandatory row: all three checkboxes checked and disabled', () => {
    render(
      <RoleFieldPermissions resource="users" fields={USERS_FIELDS} value={[]} onToggle={() => {}} disabled={false} />,
    )

    expect(screen.getByRole('checkbox', { name: 'First name — Visible' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'First name — Visible' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'First name — Editable' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'First name — Editable' })).toBeDisabled()
    expect(screen.getByRole('checkbox', { name: 'First name — Required' })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: 'First name — Required' })).toBeDisabled()
  })
})
