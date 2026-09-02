import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act } from '@testing-library/react'
import i18n from '@/i18n'
import {
  ASSIGN_OPERATOR_PERMISSION,
  OPERATIONAL_SITES_VIEW_ANY_PERMISSION,
} from '@/features/request-management/use-request-actor-defaults'
import { OPERATOR_MANAGER_POSITION } from '@/features/request-management/types'
import {
  COMPLETE_ROW,
  TEST_ACTOR_ID,
  TEST_ACTOR_SITE_ID,
  TEST_SOURCE_ID,
  renderCreateForm,
} from '@/features/request-management/request-create-form-harness'

/**
 * User directive 2026-08-04: a new request is worked by whoever opened it, from
 * the Sede they belong to — the create form opens the team's OPERATOR slot
 * (spec 0097 D-1) on the connected actor and Sede operativa on the actor's own
 * Sede.
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

/** The id sitting on the team's operator slot, whatever the rest of the array holds. */
const operatorSlot = (slots: (number | null)[]): number | null => slots[OPERATOR_MANAGER_POSITION - 1] ?? null

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRequestMock.mockReset()
})

describe('create form — actor attribution defaults', () => {
  it('opens the operator slot on the connected actor and the Sede on the actor own site', () => {
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    expect(operatorSlot(result.current.form.getValues('manager_slots'))).toBe(TEST_ACTOR_ID)
    expect(result.current.form.getValues('operational_site_id')).toBe(TEST_ACTOR_SITE_ID)
  })

  /** AC-002: the seeding writes ONE position — the rest of the team opens empty. */
  it('leaves every other slot empty', () => {
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    const slots = result.current.form.getValues('manager_slots')
    expect(slots.length).toBeGreaterThan(OPERATOR_MANAGER_POSITION)
    expect(slots.filter((slot) => slot !== null)).toEqual([TEST_ACTOR_ID])
  })

  it('seeds each field only under its own ability', () => {
    const operatorOnly = renderCreateForm(vi.fn(), [ASSIGN_OPERATOR_PERMISSION])
    expect(operatorSlot(operatorOnly.result.current.form.getValues('manager_slots'))).toBe(TEST_ACTOR_ID)
    expect(operatorOnly.result.current.form.getValues('operational_site_id')).toBeNull()

    const siteOnly = renderCreateForm(vi.fn(), [OPERATIONAL_SITES_VIEW_ANY_PERMISSION])
    expect(operatorSlot(siteOnly.result.current.form.getValues('manager_slots'))).toBeNull()
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
    expect(payload).not.toHaveProperty('manager_slots')
    expect(payload).not.toHaveProperty('operator_id')
    expect(payload).not.toHaveProperty('operational_site_id')
  })

  it('sends the seeded defaults for an actor who may submit them', async () => {
    createRequestMock.mockResolvedValue({ id: 2 })
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    await submitMinimalRequest(result)

    const payload = createRequestMock.mock.calls[0][0] as { manager_slots: (number | null)[] }
    expect(operatorSlot(payload.manager_slots)).toBe(TEST_ACTOR_ID)
    expect(payload).toMatchObject({ operational_site_id: TEST_ACTOR_SITE_ID })
  })

  it('never overwrites a pick made after the seeding', async () => {
    createRequestMock.mockResolvedValue({ id: 3 })
    const { result } = renderCreateForm(vi.fn(), BOTH_ABILITIES)

    act(() => {
      result.current.form.setValue('manager_slots', [null, 77, null, null])
      result.current.form.setValue('operational_site_id', 88)
    })
    await submitMinimalRequest(result)

    expect(createRequestMock.mock.calls[0][0]).toMatchObject({
      manager_slots: [null, 77, null, null],
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
