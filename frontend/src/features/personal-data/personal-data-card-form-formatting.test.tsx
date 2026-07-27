import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState, type ReactElement } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { EnumOption } from '@/features/config/types'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'

// Canonical shape on blur (user directive 2026-07-23): the card form shows the
// value in the shape the server stores it in, without waiting for a refetch.
// The formatting rules themselves are covered by
// `src/lib/formatting/input-format.test.ts` and by its backend twin.

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  gender: [
    { value: 'male', label: 'Male', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'female', label: 'Female', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

/** Controlled host, like every real caller of the card form. */
function CardHost({ initial }: { initial?: Partial<PersonalDataDraft> }) {
  const [draft, setDraft] = useState<PersonalDataDraft>({
    ...emptyPersonalDataDraft(),
    ...initial,
  })

  return <PersonalDataCardForm value={draft} onChange={setDraft} />
}

/**
 * The card form looks the comune of birth up through TanStack Query, so every
 * render needs a client; one per test keeps the cache from leaking across them.
 */
function renderCard(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PersonalDataCardForm formatting', () => {
  it.each([
    [/^First name/, '  ada  ', 'Ada'],
    [/^Last name/, "DELL'ACQUA", "Dell'Acqua"],
    [/^Tax code/, ' lvldaa80a01h501v ', 'LVLDAA80A01H501V'],
  ])('canonicalizes %s on blur', async (label, typed, expected) => {
    renderCard(<CardHost />)

    const input = screen.getByLabelText(label)
    fireEvent.change(input, { target: { value: typed } })
    fireEvent.blur(input)

    await waitFor(() => expect(input).toHaveValue(expected))
  })

  it('drops the IT prefix from a VAT number on blur', async () => {
    renderCard(<CardHost initial={{ type: 'company' }} />)

    const input = screen.getByLabelText(/^VAT number/)
    fireEvent.change(input, { target: { value: 'IT 12345678903' } })
    fireEvent.blur(input)

    await waitFor(() => expect(input).toHaveValue('12345678903'))
  })

  it('leaves a company name casing alone and only collapses its spacing', async () => {
    renderCard(<CardHost initial={{ type: 'company' }} />)

    const input = screen.getByLabelText(/^Company name/)
    fireEvent.change(input, { target: { value: '  ACME   S.R.L. ' } })
    fireEvent.blur(input)

    await waitFor(() => expect(input).toHaveValue('ACME S.R.L.'))
  })
})
