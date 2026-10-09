import { describe, expect, it } from 'vitest'
import {
  filterOperationGroups,
  parseOpenApiOperations,
  resolveSchema,
  schemaTypeLabel,
} from '@/features/api-integrations/openapi-operations'
import type { JsonObject, OpenApiDocument } from '@/features/api-integrations/openapi-types'

const document: OpenApiDocument = {
  openapi: '3.1.0',
  servers: [{ url: 'https://qnet.test/api' }],
  paths: {
    '/leads': {
      get: {
        tags: ['Leads'],
        summary: 'List leads',
        parameters: [
          { name: 'page', in: 'query', schema: { type: 'integer' } },
          { $ref: '#/components/parameters/PerPage' },
        ],
        responses: {
          '200': {
            content: {
              'application/json': {
                schema: {
                  type: 'object',
                  properties: { data: { type: 'array', items: { $ref: '#/components/schemas/Lead' } } },
                },
              },
            },
          },
          '401': {},
        },
      },
      post: {
        tags: ['Leads'],
        summary: 'Create lead',
        requestBody: {
          content: { 'application/json': { schema: { $ref: '#/components/schemas/StoreLead' } } },
        },
        responses: { '201': { content: { 'application/json': { schema: { $ref: '#/components/schemas/Lead' } } } } },
      },
    },
    '/leads/{lead}': {
      parameters: [{ name: 'lead', in: 'path', schema: { type: 'integer' } }],
      get: { tags: ['Leads'], responses: { '200': {} } },
    },
    '/sources': {
      get: { tags: ['Lookups'], responses: { '200': {} } },
    },
  },
  components: {
    parameters: { PerPage: { name: 'per_page', in: 'query', required: false, schema: { type: 'integer' } } },
    schemas: {
      Lead: {
        type: 'object',
        properties: { id: { type: 'integer' }, parent: { $ref: '#/components/schemas/Lead' } },
      },
      StoreLead: { type: 'object', required: ['registry_id'], properties: { registry_id: { type: 'integer' } } },
    },
  },
}

describe('parseOpenApiOperations', () => {
  const groups = parseOpenApiOperations(document)

  it('groups operations by tag in document order', () => {
    expect(groups.map((group) => group.tag)).toEqual(['Leads', 'Lookups'])
    expect(groups[0].operations.map((op) => `${op.method} ${op.path}`)).toEqual([
      'GET /leads',
      'POST /leads',
      'GET /leads/{lead}',
    ])
  })

  it('resolves component parameters and merges path-level ones', () => {
    const list = groups[0].operations[0]
    expect(list.parameters).toEqual([
      { name: 'page', location: 'query', required: false, type: 'integer', description: null },
      { name: 'per_page', location: 'query', required: false, type: 'integer', description: null },
    ])
    expect(groups[0].operations[2].parameters[0]).toMatchObject({ name: 'lead', location: 'path', required: true })
  })

  it('resolves the request body and the first 2xx response schema', () => {
    const [list, create] = groups[0].operations
    expect(create.requestSchema).toMatchObject({ required: ['registry_id'] })
    expect(list.responseStatus).toBe('200')
    expect(create.responseStatus).toBe('201')
    expect(list.requestSchema).toBeNull()
  })

  it('returns an empty list for a document without paths', () => {
    expect(parseOpenApiOperations({ openapi: '3.1.0' })).toEqual([])
  })
})

describe('filterOperationGroups', () => {
  const groups = parseOpenApiOperations(document)

  it('keeps everything for a blank query', () => {
    expect(filterOperationGroups(groups, '  ')).toBe(groups)
  })

  it('matches the path, case-insensitively, and drops empty groups', () => {
    const result = filterOperationGroups(groups, 'SOURCES')
    expect(result.map((group) => group.tag)).toEqual(['Lookups'])
  })

  it('matches the summary and keeps only the matching operations', () => {
    const result = filterOperationGroups(groups, 'create lead')
    expect(result).toHaveLength(1)
    expect(result[0].operations.map((op) => `${op.method} ${op.path}`)).toEqual(['POST /leads'])
  })

  it('matches the tag', () => {
    expect(filterOperationGroups(groups, 'lookups')[0].operations).toHaveLength(1)
  })

  it('returns no group when nothing matches', () => {
    expect(filterOperationGroups(groups, 'zzz')).toEqual([])
  })
})

describe('resolveSchema', () => {
  it('marks a self-referencing schema instead of looping', () => {
    const resolved = resolveSchema({ $ref: '#/components/schemas/Lead' }, document.components?.schemas ?? {}) as JsonObject
    const properties = resolved.properties as JsonObject
    expect(properties.parent).toEqual({ $ref: '#/components/schemas/Lead', circular: true })
  })

  it('keeps an unknown reference as is', () => {
    expect(resolveSchema({ $ref: '#/components/schemas/Missing' }, {})).toEqual({
      $ref: '#/components/schemas/Missing',
    })
  })
})

describe('schemaTypeLabel', () => {
  it('describes scalars, nullable unions, arrays and anyOf', () => {
    expect(schemaTypeLabel({ type: 'string', format: 'date-time' })).toBe('string (date-time)')
    expect(schemaTypeLabel({ type: ['integer', 'null'] })).toBe('integer|null')
    expect(schemaTypeLabel({ type: 'array', items: { type: 'integer' } })).toBe('array<integer>')
    expect(schemaTypeLabel({ anyOf: [{ type: 'string' }, { type: 'integer' }] })).toBe('string | integer')
    expect(schemaTypeLabel(undefined)).toBe('any')
  })
})
