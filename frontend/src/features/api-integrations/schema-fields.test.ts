import { describe, expect, it } from 'vitest'
import { describeSchema, flattenSchemaFields, parametersToFields } from '@/features/api-integrations/schema-fields'
import type { JsonValue } from '@/features/api-integrations/openapi-types'

describe('describeSchema', () => {
  it('describes scalars, formats, arrays and enums', () => {
    expect(describeSchema({ type: 'string', format: 'date-time' }).type).toBe('string (date-time)')
    expect(describeSchema({ type: 'array', items: { type: 'integer' } }).type).toBe('array<integer>')
    expect(describeSchema({ type: 'string', enum: ['a', 'b'] })).toEqual({
      type: 'string',
      nullable: false,
      enumValues: ['a', 'b'],
    })
    expect(describeSchema(undefined).type).toBe('any')
  })

  it('detects nullability from a type list and from anyOf', () => {
    expect(describeSchema({ type: ['integer', 'null'] })).toMatchObject({ type: 'integer', nullable: true })
    expect(describeSchema({ anyOf: [{ type: 'string' }, { type: 'null' }] })).toMatchObject({
      type: 'string',
      nullable: true,
    })
    expect(describeSchema({ anyOf: [{ type: 'string' }, { type: 'integer' }] }).type).toBe('string | integer')
  })
})

describe('flattenSchemaFields', () => {
  const schema: JsonValue = {
    type: 'object',
    required: ['name'],
    properties: {
      name: { type: 'string', description: 'Full name' },
      status: { type: 'string', enum: ['open', 'closed'] },
      note: { type: ['string', 'null'] },
      address: {
        type: 'object',
        required: ['city'],
        properties: { city: { type: 'string' }, geo: { type: 'object', properties: { lat: { type: 'number' } } } },
      },
      tags: { type: 'array', items: { type: 'object', properties: { label: { type: 'string' } } } },
    },
  }
  const fields = flattenSchemaFields(schema)

  it('lists fields in reading order with nested ones indented', () => {
    expect(fields.map((field) => `${'  '.repeat(field.depth)}${field.name}`)).toEqual([
      'name',
      'status',
      'note',
      'address',
      '  city',
      '  geo',
      '    lat',
      'tags',
      '  label',
    ])
  })

  it('carries required, nullable, enum, type and description', () => {
    const byPath = Object.fromEntries(fields.map((field) => [field.path, field]))
    expect(byPath.name).toMatchObject({ required: true, type: 'string', description: 'Full name' })
    expect(byPath.status.enumValues).toEqual(['open', 'closed'])
    expect(byPath.note).toMatchObject({ nullable: true, type: 'string', required: false })
    expect(byPath['address.city'].required).toBe(true)
    expect(byPath.tags.type).toBe('array<object>')
    expect(byPath['tags.label'].depth).toBe(1)
  })

  it('merges allOf members, uniting properties and required', () => {
    const merged = flattenSchemaFields({
      allOf: [
        { type: 'object', properties: { id: { type: 'integer' } } },
        { type: 'object', required: ['id'], properties: { city: { type: 'string' } } },
      ],
    })
    expect(merged.map((field) => [field.name, field.required])).toEqual([
      ['id', true],
      ['city', false],
    ])
  })

  it('expands an array of objects at the root and ignores schemas without properties', () => {
    expect(flattenSchemaFields({ type: 'array', items: { type: 'object', properties: { id: { type: 'integer' } } } })).toHaveLength(1)
    expect(flattenSchemaFields({ type: 'string' })).toEqual([])
    expect(flattenSchemaFields(null)).toEqual([])
  })

  it('stops at circular stubs and at the depth cap', () => {
    const circular = flattenSchemaFields({
      type: 'object',
      properties: { parent: { $ref: '#/components/schemas/Lead', circular: true } },
    })
    expect(circular).toHaveLength(1)
    expect(circular[0]).toMatchObject({ name: 'parent', type: 'object' })

    let deep: JsonValue = { type: 'string' }
    for (let level = 0; level < 12; level += 1) {
      deep = { type: 'object', properties: { child: deep } }
    }
    expect(flattenSchemaFields(deep)).toHaveLength(5)
  })
})

describe('parametersToFields', () => {
  it('maps parameters to rows with their location', () => {
    expect(
      parametersToFields([
        { name: 'event', location: 'query', required: false, schema: { type: 'string', enum: ['created'] }, description: 'Kind' },
      ]),
    ).toEqual([
      expect.objectContaining({ name: 'event', location: 'query', type: 'string', enumValues: ['created'], description: 'Kind', depth: 0 }),
    ])
  })
})
