import { beforeAll, describe, expect, it } from 'vitest'
import type { FieldErrors } from 'react-hook-form'
import i18n from '@/i18n'
import { describeInvalidFields } from '@/features/request-management/request-work-invalid-fields'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

/**
 * Spec 0097: the team is ONE field of the summary, `manager_slots` — the
 * single "Operatore" entry (spec 0080, resolved from the request's own G.A.
 * labels) no longer exists, since any of the slots can be the one that
 * refused the submit.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const TEAM_ERROR: FieldErrors<RequestWorkFormValues> = {
  manager_slots: { type: 'custom', message: '' },
}

describe('describeInvalidFields — team block (spec 0097)', () => {
  it('names the whole team block, not one slot', () => {
    const fields = describeInvalidFields(TEAM_ERROR, i18n.t.bind(i18n))

    expect(fields).toEqual([i18n.t('requestManagement.workPanel.team.managers')])
  })

  it('keeps naming the other attribution fields it always named', () => {
    const fields = describeInvalidFields({ source_id: { type: 'custom', message: '' } }, i18n.t.bind(i18n))

    expect(fields).toEqual([i18n.t('requestManagement.workPanel.attribution.source')])
  })
})
