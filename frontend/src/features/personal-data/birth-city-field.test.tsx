import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { useState } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { EnumOption } from '@/features/config/types'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'

/**
 * The comune of birth (`personal_data.birth_city_id`): an individual-only field
 * that references the geo catalogue, so the card emits an id — never free text —
 * and a company card carries none.
 */

const useCitiesMock = vi.fn()

vi.mock('@/features/geo/use-geo', () => ({
  useCities: (stateId: number | null, provinceId?: number | null, search?: string) =>
    useCitiesMock(stateId, provinceId, search),
}))

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

function cityQuery(items: unknown[]) {
  return {
    data: { pages: [items] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
    refetch: vi.fn(),
  }
}

/** Controlled host, like every real caller of the card form. */
function CardHost({
  initial,
  onDraft,
}: {
  initial?: Partial<PersonalDataDraft>
  onDraft?: (draft: PersonalDataDraft) => void
}) {
  const [draft, setDraft] = useState<PersonalDataDraft>({
    ...emptyPersonalDataDraft(),
    ...initial,
  })

  return (
    <PersonalDataCardForm
      value={draft}
      onChange={(next) => {
        setDraft(next)
        onDraft?.(next)
      }}
    />
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useCitiesMock.mockReset()
  useCitiesMock.mockReturnValue(
    cityQuery([{ id: 501, name: 'Napoli', country_id: 1, state_id: 10, province_id: 100 }]),
  )
})

describe('PersonalDataCardForm — place of birth', () => {
  it('emits the picked comune id into the draft', async () => {
    const drafts: PersonalDataDraft[] = []
    render(<CardHost onDraft={(draft) => drafts.push(draft)} />)

    fireEvent.click(screen.getByRole('combobox', { name: /^Place of birth/ }))
    fireEvent.click(await screen.findByRole('option', { name: 'Napoli' }))

    await waitFor(() =>
      expect(drafts.at(-1)?.birth_city_id).toBe(501),
    )
  })

  it('labels the current value from the hydrated comune, before any search', () => {
    render(
      <CardHost
        initial={{ birth_city_id: 501, birth_city: { id: 501, name: 'Napoli' } }}
      />,
    )

    expect(screen.getByRole('combobox', { name: /^Place of birth/ })).toHaveTextContent(
      'Napoli',
    )
  })

  it('is not rendered on a company card', () => {
    render(<CardHost initial={{ type: 'company' }} />)

    expect(
      screen.queryByRole('combobox', { name: /^Place of birth/ }),
    ).not.toBeInTheDocument()
  })
})
