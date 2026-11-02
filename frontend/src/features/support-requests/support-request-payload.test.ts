import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildSupportRequestFormData } from '@/features/support-requests/support-request-payload'
import { buildSupportRequestSchema } from '@/features/support-requests/support-request-schema'

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('buildSupportRequestFormData (spec 0334 data_contract)', () => {
  it('serializes booleans as 1/0 and repeats attachments[]', () => {
    const first = new File(['a'], 'a.png')
    const second = new File(['b'], 'b.png')

    const body = buildSupportRequestFormData(
      { support_topic_id: 3, description: 'Non va', is_urgent: false, attachments: [first, second] },
      { page_url: '/leads?x=1', user_agent: 'UA' },
    )

    expect(body.get('support_topic_id')).toBe('3')
    expect(body.get('is_urgent')).toBe('0')
    expect(body.getAll('attachments[]')).toEqual([first, second])
    expect(body.get('page_url')).toBe('/leads?x=1')
    expect(body.get('user_agent')).toBe('UA')
  })
})

describe('buildSupportRequestSchema', () => {
  const schema = buildSupportRequestSchema(i18n.t)

  it('requires a topic and a non-blank description', () => {
    const result = schema.safeParse({ support_topic_id: null, description: '   ', is_urgent: false, attachments: [] })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => issue.path[0]).sort()).toEqual(['description', 'support_topic_id'])
  })

  it('rejects more than five attachments and descriptions over 5000 characters', () => {
    const files = Array.from({ length: 6 }, () => new File(['x'], 'x.png'))

    const result = schema.safeParse({
      support_topic_id: 1,
      description: 'a'.repeat(5001),
      is_urgent: false,
      attachments: files,
    })

    expect(result.error?.issues.map((issue) => issue.path[0]).sort()).toEqual(['attachments', 'description'])
  })

  it('accepts a valid request', () => {
    expect(
      schema.safeParse({ support_topic_id: 1, description: 'ok', is_urgent: true, attachments: [] }).success,
    ).toBe(true)
  })
})
