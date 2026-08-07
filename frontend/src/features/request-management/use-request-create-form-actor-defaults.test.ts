import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act } from '@testing-library/react'
import i18n from '@/i18n'
import {
  ASSIGN_OPERATOR_PERMISSION,
  OPERATIONAL_SITES_VIEW_ANY_PERMISSION,
} from '@/features/request-management/use-request-actor-defaults'
import {
  COMPLETE_ROW,
  TEST_ACTOR_ID,
  TEST_ACTOR_SITE_ID,
  TEST_SOURCE_ID,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'

/**
 * User directive 2026-08-04: a new request is worked by whoever opened it, from
 * the Sede they belong to — the create form opens Operatore on the connected
 * actor and Sede operativa on the actor's own Sede.
 *
 * Only the VISIBLE half is asserted here: whether the two values reach the
 * payload depends on the abilities that decide whether the fields are rendered
 * at all (an actor without them may not submit the keys — the endpoint answers
 * 403). For that actor the server applies the identical default, which is
 * covered by RequestManagementCreateActorDefaultsTest.
 */

const createRequestMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  // The create form resolves its "Informazioni aggiuntive" from the picked
  // categories (user directive 2026-08-07): stubbed empty, this suite is not
  // about that block.
  fetchRequestFormContext: () => Promise.resolve({ applicable_attributes: [], attribute_layout: null }),
  createRequest: (...args: unknown[]) => createRequestMock(...args),
}))

const BOTH_ABILITIES = [ASSIGN_OPERATOR_PERMISSION, OPERATIONAL_SITES_VIEW_ANY_PERMISSION]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
})

describe('create form — actor attribution defaults', () => {
  it('opens the Operatore on the connected actor and the Sede on the actor own site', () => {
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    expect(result.current.form.getValues('operator_id')).toBe(TEST_ACTOR_ID)
    expect(result.current.form.getValues('operational_site_id')).toBe(TEST_ACTOR_SITE_ID)
  })

  it('seeds each field only under its own ability', () => {
    const operatorOnly = renderCreateForm(vi.fn(), [ASSIGN_OPERATOR_PERMISSION])
    expect(operatorOnly.result.current.form.getValues('operator_id')).toBe(TEST_ACTOR_ID)
    expect(operatorOnly.result.current.form.getValues('operational_site_id')).toBeNull()

    const siteOnly = renderCreateForm(vi.fn(), [OPERATIONAL_SITES_VIEW_ANY_PERMISSION])
    expect(siteOnly.result.current.form.getValues('operator_id')).toBeNull()
    expect(siteOnly.result.current.form.getValues('operational_site_id')).toBe(TEST_ACTOR_SITE_ID)
  })

  /**
   * An actor holding neither ability renders neither control, so the payload
   * must stay clean: the two keys are the server's to fill in.
   */
  it('sends neither key for an actor who may not submit them', async () => {
    createRequestMock.mockResolvedValue({ id: 1 })
    const { result } = renderCreateForm(vi.fn())

    await submitMinimalRequest(result)

    const payload = createRequestMock.mock.calls[0][0]
    expect(payload).not.toHaveProperty('operator_id')
    expect(payload).not.toHaveProperty('operational_site_id')
  })

  it('sends the seeded defaults for an actor who may submit them', async () => {
    createRequestMock.mockResolvedValue({ id: 2 })
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    await submitMinimalRequest(result)

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      operator_id: TEST_ACTOR_ID,
      operational_site_id: TEST_ACTOR_SITE_ID,
    })
  })

  it('never overwrites a pick made after the seeding', async () => {
    createRequestMock.mockResolvedValue({ id: 3 })
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    act(() => {
      result.current.form.setValue('operator_id', 77)
      result.current.form.setValue('operational_site_id', 88)
    })
    await submitMinimalRequest(result)

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      operator_id: 77,
      operational_site_id: 88,
    })
  })
})

/** The smallest payload the schema accepts: an existing registry + one complete line + the Fonte. */
async function submitMinimalRequest(result: { current: ReturnType<typeof renderCreateForm>['result']['current'] }) {
  act(() => {
    result.current.form.setValue('registry_id', 4)
    result.current.form.setValue('product_lines', [COMPLETE_ROW])
    result.current.form.setValue('source_id', TEST_SOURCE_ID)
  })
  await act(async () => {
    await result.current.onSubmit()
  })
}
