import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { useIdentityDuplicateCheck } from '@/features/identity-duplicates/use-identity-duplicate-check'

const checkIdentityDuplicatesMock = vi.fn()

vi.mock('@/features/identity-duplicates/duplicate-check-api', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/identity-duplicates/duplicate-check-api')
  >('@/features/identity-duplicates/duplicate-check-api')
  return {
    ...actual,
    checkIdentityDuplicates: (...args: unknown[]) => checkIdentityDuplicatesMock(...args),
  }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function draftWithEmail(email: string): PersonalDataDraft {
  return {
    ...emptyPersonalDataDraft(),
    contacts: [
      { _key: 'k1', type: 'email', value: email, label: null, is_primary: true },
    ],
  }
}

beforeEach(() => {
  checkIdentityDuplicatesMock.mockReset()
  checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
})

describe('useIdentityDuplicateCheck (AC-007, AC-008)', () => {
  it('does not call the API while every criterion is empty', async () => {
    renderHook(
      () => useIdentityDuplicateCheck({ enabled: true, profileDraft: emptyPersonalDataDraft() }),
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkIdentityDuplicatesMock).not.toHaveBeenCalled()
  })

  it('debounces and calls the API with the normalized email criterion once one is filled', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'referent', owner_id: 1, name: 'Mario Rossi', matched_on: ['email'] }],
    })

    const { result, rerender } = renderHook(
      ({ profileDraft }: { profileDraft: PersonalDataDraft }) =>
        useIdentityDuplicateCheck({ enabled: true, profileDraft }),
      { wrapper: wrapper(), initialProps: { profileDraft: emptyPersonalDataDraft() } },
    )

    rerender({ profileDraft: draftWithEmail(' Mario.Rossi@Example.com ') })

    await waitFor(() => expect(checkIdentityDuplicatesMock).toHaveBeenCalledTimes(1))
    expect(checkIdentityDuplicatesMock).toHaveBeenCalledWith({
      tax_code: undefined,
      vat_number: undefined,
      contacts: [{ type: 'email', value: 'Mario.Rossi@Example.com' }],
    })
    await waitFor(() => expect(result.current.matches).toHaveLength(1))
    expect(result.current.matches[0].name).toBe('Mario Rossi')
  })

  it('never runs while disabled (the edit forms)', async () => {
    renderHook(
      () =>
        useIdentityDuplicateCheck({
          enabled: false,
          profileDraft: draftWithEmail('mario.rossi@example.com'),
        }),
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkIdentityDuplicatesMock).not.toHaveBeenCalled()
  })

  it('clears the matches once the fields go back to empty', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'referent', owner_id: 1, name: 'Mario Rossi', matched_on: ['email'] }],
    })

    const { result, rerender } = renderHook(
      ({ profileDraft }: { profileDraft: PersonalDataDraft }) =>
        useIdentityDuplicateCheck({ enabled: true, profileDraft }),
      { wrapper: wrapper(), initialProps: { profileDraft: draftWithEmail('mario.rossi@example.com') } },
    )

    await waitFor(() => expect(result.current.matches).toHaveLength(1))

    rerender({ profileDraft: emptyPersonalDataDraft() })

    await waitFor(() => expect(result.current.matches).toHaveLength(0))
  })

  it('sends the fiscal identifiers as criteria of their own (user directive 2026-09-09)', async () => {
    const { rerender } = renderHook(
      ({ profileDraft }: { profileDraft: PersonalDataDraft }) =>
        useIdentityDuplicateCheck({ enabled: true, profileDraft }),
      { wrapper: wrapper(), initialProps: { profileDraft: emptyPersonalDataDraft() } },
    )

    rerender({
      profileDraft: {
        ...emptyPersonalDataDraft(),
        tax_code: ' RSSMRA80A01H501U ',
        vat_number: ' 01234567890 ',
      },
    })

    await waitFor(() => expect(checkIdentityDuplicatesMock).toHaveBeenCalledTimes(1))
    expect(checkIdentityDuplicatesMock).toHaveBeenCalledWith({
      tax_code: 'RSSMRA80A01H501U',
      vat_number: '01234567890',
      contacts: undefined,
    })
  })
})
