// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { useState } from 'react'
import { emptyPersonalDataDraft, isBlankPersonalDataDraft } from '@/features/personal-data/drafts'
import { useCategoryRegistryType } from '@/features/request-management/use-category-registry-type'
import type { PersonalDataDraft, PersonalDataType } from '@/features/personal-data/types'

/**
 * Spec 0410 AC-004: the first product line's category decides which type the
 * new-client card starts on, only while nothing has been typed into it.
 */

function renderTypeHook(initialDraft: PersonalDataDraft, initialType: PersonalDataType | null = null) {
  return renderHook(
    ({ registryType }: { registryType: PersonalDataType | null }) => {
      const [draft, setDraft] = useState(initialDraft)
      const key = useCategoryRegistryType(registryType, draft, setDraft)
      return { draft, setDraft, key }
    },
    { initialProps: { registryType: initialType } },
  )
}

describe('isBlankPersonalDataDraft', () => {
  it('is blank until an identity field is typed, empty strings included', () => {
    expect(isBlankPersonalDataDraft(emptyPersonalDataDraft())).toBe(true)
    expect(isBlankPersonalDataDraft({ ...emptyPersonalDataDraft(), first_name: '' })).toBe(true)
    expect(isBlankPersonalDataDraft({ ...emptyPersonalDataDraft(), first_name: 'Mario' })).toBe(false)
    expect(isBlankPersonalDataDraft({ ...emptyPersonalDataDraft(), birth_city_id: 4 })).toBe(false)
  })
})

describe('useCategoryRegistryType', () => {
  it('switches a blank card to the category type and remounts it', () => {
    const { result, rerender } = renderTypeHook(emptyPersonalDataDraft())

    rerender({ registryType: 'company' })

    expect(result.current.draft.type).toBe('company')
    expect(result.current.key).toBe(1)
  })

  it('leaves a card with typed data untouched', () => {
    const { result, rerender } = renderTypeHook({ ...emptyPersonalDataDraft(), last_name: 'Rossi' })

    rerender({ registryType: 'company' })

    expect(result.current.draft.type).toBe('individual')
    expect(result.current.draft.last_name).toBe('Rossi')
    expect(result.current.key).toBe(0)
  })

  it('does nothing without a category, or when the card already has that type', () => {
    const { result, rerender } = renderTypeHook(emptyPersonalDataDraft())

    rerender({ registryType: null })
    rerender({ registryType: 'individual' })

    expect(result.current.draft.type).toBe('individual')
    expect(result.current.key).toBe(0)
  })

  it('keeps a type the operator picked by hand until the category type changes again', () => {
    const { result, rerender } = renderTypeHook(emptyPersonalDataDraft(), 'company')
    expect(result.current.draft.type).toBe('company')

    act(() => result.current.setDraft((draft) => ({ ...draft, type: 'individual' })))
    rerender({ registryType: 'company' })
    expect(result.current.draft.type).toBe('individual')

    rerender({ registryType: 'individual' })
    rerender({ registryType: 'company' })
    expect(result.current.draft.type).toBe('company')
  })
})
