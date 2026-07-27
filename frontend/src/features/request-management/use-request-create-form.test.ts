import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import i18n from '@/i18n'
import { useRequestCreateForm } from '@/features/request-management/use-request-create-form'
import type { PersonalDataDraft } from '@/features/personal-data/types'

/**
 * Spec 0057 D-2/D-3/AC-016: the create form's non-render logic — the two
 * mutually-exclusive anagrafica branches (existing registry vs a brand-new
 * client), the mandatory product lines, and 422 mapping onto `registry_id`
 * (RHF) plus the `client_*`/`product_lines` blocks (banner, since those
 * sections are outside RHF — see the hook's own doc comment).
 */

const createRequestMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  createRequest: (...args: unknown[]) => createRequestMock(...args),
}))

const COMPLETE_ROW = { business_function_id: 1, product_category_id: 2 }

function completeIdentity(): PersonalDataDraft {
  return {
    type: 'individual',
    first_name: 'Mario',
    last_name: 'Rossi',
    company_name: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    gender: 'male',
    contacts: [],
    addresses: [],
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
})

describe('useRequestCreateForm', () => {
  it('blocks submit and surfaces a client banner when neither a registry nor a complete identity is provided', async () => {
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })
    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.clientBlockError).toBe('Complete the client identity fields before saving.')
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('blocks submit when product_lines is empty (D-3)', async () => {
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).not.toHaveBeenCalled()
    expect(result.current.form.formState.errors.product_lines).toBeTruthy()
  })

  it('submits the registry branch, dropping the client buffer entirely (D-2)', async () => {
    createRequestMock.mockResolvedValue({ id: 42 })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      // Even if something was typed before picking the registry, the registry
      // branch must win: the buffer is never sent alongside `registry_id`.
      result.current.setIdentityDraft(completeIdentity())
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith({
      registry_id: 10,
      product_lines: [COMPLETE_ROW],
      // Attribution slots ride along with either branch; empty by default,
      // `rewards` omitted entirely until at least one is picked.
      source_id: null,
      reporter_id: null,
    })
    expect(onSuccess).toHaveBeenCalledWith(42)
  })

  it('sends the initial attribution (source/reporter/rewards) when set (user directive 2026-07-24)', async () => {
    createRequestMock.mockResolvedValue({ id: 44 })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('source_id', 7)
      result.current.form.setValue('reporter_id', 3)
      result.current.form.setValue('rewards', [{ reward_type_id: 5 }])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith({
      registry_id: 10,
      product_lines: [COMPLETE_ROW],
      source_id: 7,
      reporter_id: 3,
      rewards: [{ reward_type_id: 5 }],
    })
    expect(onSuccess).toHaveBeenCalledWith(44)
  })

  it('collects a rewards D-3 422 (reward without a reporter) into the rewards banner', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { errors: { rewards: ['Rewards require the opportunity to have a reporter (Segnalatore).'] } },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
      result.current.form.setValue('rewards', [{ reward_type_id: 5 }])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.rewardsError).toBe('Rewards require the opportunity to have a reporter (Segnalatore).')
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('submits the new-client branch with the buffered identity/contacts/address', async () => {
    createRequestMock.mockResolvedValue({ id: 43 })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.setIdentityDraft(completeIdentity())
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(createRequestMock).toHaveBeenCalledWith(
      expect.objectContaining({
        client_identity: expect.objectContaining({ type: 'individual', first_name: 'Mario', last_name: 'Rossi' }),
        client_contacts: [],
        product_lines: [COMPLETE_ROW],
      }),
    )
    expect(onSuccess).toHaveBeenCalledWith(43)
  })

  it('maps a 422 onto registry_id (RHF) and collects client_identity.* into the client banner (AC-016)', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          errors: {
            registry_id: ['The selected registry id is invalid.'],
            'client_identity.first_name': ['The client identity first name field is required.'],
          },
        },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('registry_id', 999)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.form.formState.errors.registry_id?.message).toBe('The selected registry id is invalid.')
    expect(result.current.clientBlockError).toBe('The client identity first name field is required.')
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('maps a 422 on product_lines.* into the product-lines banner', async () => {
    createRequestMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { errors: { 'product_lines.0.product_category_id': ['That category does not belong to the chosen function.'] } },
      },
    })
    const onSuccess = vi.fn()
    const { result } = renderHook(() => useRequestCreateForm({ onSuccess }))

    act(() => {
      result.current.form.setValue('registry_id', 10)
      result.current.form.setValue('product_lines', [COMPLETE_ROW])
    })

    await act(async () => {
      await result.current.onSubmit()
    })

    expect(result.current.productLinesError).toBe('That category does not belong to the chosen function.')
    expect(onSuccess).not.toHaveBeenCalled()
  })
})
