import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProfileForm } from '@/features/auth/profile-form'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { User } from '@/features/auth/types'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/* ----------------------------- module mocks ------------------------------- */

const updateProfileMock = vi.fn()

vi.mock('@/features/auth/api', () => ({
  updateProfile: (...args: unknown[]) => updateProfileMock(...args),
}))

// Enum options consumed by the locale select and the personal-data card form
// (controlled, no network).
const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    {
      value: 'individual',
      label: 'Individual',
      color: null,
      icon: null,
      is_default: true,
      hidden_on_form: false,
    },
    {
      value: 'company',
      label: 'Company',
      color: null,
      icon: null,
      is_default: false,
      hidden_on_form: false,
    },
  ],
  contact_type: [],
  locale: [
    {
      value: 'en',
      label: 'English',
      color: null,
      icon: null,
      is_default: true,
      hidden_on_form: false,
    },
    {
      value: 'it',
      label: 'Italiano',
      color: null,
      icon: null,
      is_default: false,
      hidden_on_form: false,
    },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

// Control the authenticated user the form seeds from.
const currentUser = vi.fn<() => User | null>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

/* -------------------------------- helpers --------------------------------- */

function wrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    gender: null,
    personable_type: 'user',
    personable_id: 7,
    contacts: [
      {
        id: 5,
        type: 'email',
        label: 'Work',
        value: 'ada@work.com',
        is_primary: true,
        contactable_type: 'personal_data',
        contactable_id: 99,
        created_at: null,
      },
    ],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function user(overrides: Partial<User> = {}): User {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    personal_data: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProfileMock.mockReset()
  currentUser.mockReset()
  currentUser.mockReturnValue(user())
  updateProfileMock.mockResolvedValue(user())
})

/* --------------------------------- tests ---------------------------------- */

describe('ProfileForm', () => {
  it('renders the email read-only, locale and the personal-data section without a free name field', () => {
    render(<ProfileForm />, { wrapper: wrapper() })

    // The registration email is shown but not editable (read-only/disabled).
    const emailField = screen.getByLabelText(/^Email/)
    expect(emailField).toBeInTheDocument()
    expect(emailField).toHaveValue('ada@example.com')
    expect(emailField).toBeDisabled()
    expect(emailField).toHaveAttribute('readonly')

    expect(screen.getByText('Personal data')).toBeInTheDocument()
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()

    // The free account `name` field has been removed (parity with Users).
    expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument()
  })

  it('seeds the personal-data section from me.personal_data', () => {
    currentUser.mockReturnValue(user({ personal_data: card() }))

    render(<ProfileForm />, { wrapper: wrapper() })

    expect(screen.getByLabelText(/^First name/)).toHaveValue('Ada')
    expect(screen.getByLabelText(/^Last name/)).toHaveValue('Lovelace')
    expect(screen.getByText('ada@work.com')).toBeInTheDocument()
  })

  it('submits PATCH /auth/me with locale and nested personal_data only (no email, no name)', async () => {
    currentUser.mockReturnValue(user({ personal_data: card() }))

    render(<ProfileForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(updateProfileMock).toHaveBeenCalledTimes(1))

    const payload = updateProfileMock.mock.calls[0][0]
    // Email is the registration address: read-only, never part of the payload.
    expect(payload.email).toBeUndefined()
    expect(payload.name).toBeUndefined()
    expect(payload.locale).toBe('en')
    expect(payload.personal_data).toBeDefined()
    expect(payload.personal_data.type).toBe('individual')
    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.contacts[0].id).toBe(5)
    // The module open-mode preference is its own settings section now, never
    // part of the profile payload (spec 0042).
    expect(payload.module_open_preferences).toBeUndefined()
  })

  it('blocks the save until the required personal-data fields are filled', async () => {
    // No card on the user: the always-active card starts blank and invalid.
    currentUser.mockReturnValue(user({ personal_data: null }))

    render(<ProfileForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() =>
      expect(
        screen.getByText('Complete the required personal data fields.'),
      ).toBeInTheDocument(),
    )
    expect(updateProfileMock).not.toHaveBeenCalled()
  })

  it('shows a generic error when the request fails without field errors', async () => {
    currentUser.mockReturnValue(user({ personal_data: card() }))
    updateProfileMock.mockRejectedValue(new Error('network'))

    render(<ProfileForm />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() =>
      expect(
        screen.getByText('Something went wrong. Please try again.'),
      ).toBeInTheDocument(),
    )
  })
})
