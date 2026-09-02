import { describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { EnumOption } from '@/features/config/types'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'

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

/**
 * The authorization ceiling emits `required: false` for every `personal_data.*`
 * key on purpose (the per-type rule is validation-layer logic), so this is the
 * exact resolver the registry/referent/user forms hand the card.
 */
const grantedPermission: PersonalDataFieldPermission = {
  visible: true,
  editable: true,
  required: false,
  disabled: false,
  readonly: false,
}

function Harness({ revalidateSignal }: { revalidateSignal?: number }) {
  const [draft, setDraft] = useState<PersonalDataDraft>(() => emptyPersonalDataDraft())

  return (
    <PersonalDataCardForm
      value={draft}
      onChange={setDraft}
      fieldPermission={() => grantedPermission}
      revalidateSignal={revalidateSignal}
    />
  )
}

/** One stable client per test: the comune pickers of an individual card query geo. */
function renderCard(revalidateSignal?: number) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <Harness revalidateSignal={revalidateSignal} />
    </QueryClientProvider>,
  )
}

const firstNameLabel = i18n.t('personalData.form.firstName')
const lastNameLabel = i18n.t('personalData.form.lastName')

describe('PersonalDataCardForm — required markers', () => {
  it('marks the schema-required identity fields even when the permission says otherwise', () => {
    renderCard()

    for (const label of [firstNameLabel, lastNameLabel]) {
      expect(screen.getByText(label).textContent).toContain('*')
    }
  })

  it('leaves the optional fiscal fields unmarked', () => {
    renderCard()

    expect(screen.getByText(i18n.t('personalData.form.taxCode')).textContent).not.toContain('*')
  })
})

describe('PersonalDataCardForm — highlight on a refused save', () => {
  it('shows nothing until the owner form refuses the save', () => {
    renderCard()

    expect(screen.queryByText(i18n.t('personalData.form.firstNameRequired'))).toBeNull()
  })

  it('marks and names every missing field once the signal is bumped', async () => {
    renderCard(1)

    await waitFor(() => {
      expect(screen.getByText(i18n.t('personalData.form.firstNameRequired'))).toBeInTheDocument()
    })
    expect(screen.getByText(i18n.t('personalData.form.lastNameRequired'))).toBeInTheDocument()
    expect(screen.getByLabelText(new RegExp(firstNameLabel))).toHaveAttribute(
      'aria-invalid',
      'true',
    )
  })
})
