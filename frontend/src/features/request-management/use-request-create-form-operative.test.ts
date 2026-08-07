import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act } from '@testing-library/react'
import i18n from '@/i18n'
import {
  COMPLETE_ROW,
  TEST_SOURCE_ID,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'
import type { UseFormReturn } from 'react-hook-form'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

/**
 * User directive 2026-07-31 ("la scheda di creazione il piu' simile possibile a
 * quella di gestione"): the operative fields the work panel edits are now
 * collected at creation too. Each travels ONLY when it carries something — on
 * create there is no persisted value an empty key could clear.
 *
 * Split from `use-request-create-form.test.ts` when it crossed the 500-line
 * hard limit (engineering.md §6); the shared fixtures live in
 * `request-create-form-harness.ts`.
 */

const createRequestMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
  createRequest: (...args: unknown[]) => createRequestMock(...args),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
})

/** Fills the anagrafica/classification minimum every submitting case needs. */
function fillMandatory(form: UseFormReturn<RequestCreateFormValues>) {
  form.setValue('registry_id', 10)
  form.setValue('product_lines', [COMPLETE_ROW])
  form.setValue('source_id', TEST_SOURCE_ID)
}

describe('useRequestCreateForm — operative fields', () => {
  it('omits every operative key when none of them was filled in', async () => {
    createRequestMock.mockResolvedValue({ id: 50 })
    const { result } = renderCreateForm(vi.fn())

    act(() => fillMandatory(result.current.form))
    await act(async () => {
      await result.current.onSubmit()
    })

    const payload = createRequestMock.mock.calls[0][0]
    expect(payload).not.toHaveProperty('next_callback_at')
    expect(payload).not.toHaveProperty('general_notes')
  })

  it('sends next_callback_at and the trimmed general_notes when filled', async () => {
    createRequestMock.mockResolvedValue({ id: 51 })
    const { result } = renderCreateForm(vi.fn())

    act(() => {
      fillMandatory(result.current.form)
      result.current.form.setValue('next_callback_at', '2026-09-01T10:30')
      result.current.form.setValue('general_notes', '  Richiama a settembre.  ')
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      next_callback_at: '2026-09-01T10:30',
      general_notes: 'Richiama a settembre.',
    })
  })
})
