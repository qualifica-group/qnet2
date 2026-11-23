import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildPollSchema, toCreatePollPayload } from '@/features/work-order-page/blocks/poll-schema'

const t = ((key: string) => key) as unknown as TFunction
const schema = buildPollSchema(t)
const options = (labels: string[]) => labels.map((label) => ({ label }))

describe('poll schema (mirror of the POST polls rules)', () => {
  it('accepts a question with 2..10 distinct options and trims the payload', () => {
    const parsed = schema.parse({ question: '  Which day? ', options: options([' Mon ', 'Tue']) })

    expect(toCreatePollPayload(parsed)).toEqual({ question: 'Which day?', options: ['Mon', 'Tue'] })
    expect(schema.safeParse({ question: 'q', options: options(Array.from({ length: 10 }, (_, i) => `o${i}`)) }).success).toBe(true)
  })

  it.each([
    ['one option', { question: 'q', options: options(['a']) }],
    ['eleven options', { question: 'q', options: options(Array.from({ length: 11 }, (_, i) => `o${i}`)) }],
    ['duplicate options (case-insensitive)', { question: 'q', options: options(['a', 'A']) }],
    ['empty option', { question: 'q', options: options(['a', ' ']) }],
    ['empty question', { question: ' ', options: options(['a', 'b']) }],
    ['option over 120 chars', { question: 'q', options: options(['a', 'x'.repeat(121)]) }],
  ])('rejects %s', (_label, values) => {
    expect(schema.safeParse(values).success).toBe(false)
  })
})
