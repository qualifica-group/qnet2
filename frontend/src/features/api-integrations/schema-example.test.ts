import { describe, expect, it } from 'vitest'
import { buildExampleFromSchema } from '@/features/api-integrations/schema-example'

describe('buildExampleFromSchema', () => {
  it('uses placeholders per type', () => {
    expect(
      buildExampleFromSchema({
        type: 'object',
        properties: {
          name: { type: 'string' },
          count: { type: 'integer' },
          price: { type: 'number' },
          active: { type: 'boolean' },
          tags: { type: 'array', items: { type: 'string' } },
          owner: { type: 'object', properties: { id: { type: 'integer' } } },
        },
      }),
    ).toEqual({ name: 'string', count: 0, price: 0, active: true, tags: ['string'], owner: { id: 0 } })
  })

  it('prefers example, then default, then the first enum value', () => {
    expect(
      buildExampleFromSchema({
        type: 'object',
        properties: {
          a: { type: 'string', example: 'x', default: 'y' },
          b: { type: 'string', default: 'y', enum: ['z'] },
          c: { type: 'string', enum: ['first', 'second'] },
        },
      }),
    ).toEqual({ a: 'x', b: 'y', c: 'first' })
  })

  it('picks the non-null member of a nullable type or union', () => {
    expect(
      buildExampleFromSchema({
        type: 'object',
        properties: { a: { type: ['integer', 'null'] }, b: { anyOf: [{ type: 'null' }, { type: 'string' }] } },
      }),
    ).toEqual({ a: 0, b: 'string' })
  })

  it('merges allOf and stays finite on circular stubs and deep schemas', () => {
    expect(
      buildExampleFromSchema({
        allOf: [
          { type: 'object', properties: { id: { type: 'integer' } } },
          { type: 'object', properties: { parent: { circular: true, $ref: '#' } } },
        ],
      }),
    ).toEqual({ id: 0, parent: null })
    expect(buildExampleFromSchema(null)).toBeNull()
  })
})
