import { describe, expect, it } from 'vitest'
import {
  cleanSummary,
  filterOperations,
  parseOpenApiOperations,
  resolveSchema,
  type ApiMethod,
} from '@/features/api-integrations/openapi-operations'
import type { JsonObject, OpenApiDocument } from '@/features/api-integrations/openapi-types'

const document: OpenApiDocument = {
  openapi: '3.1.0',
  servers: [{ url: 'https://qnet.test/api' }],
  paths: {
    '/leads': {
      get: {
        tags: ['Leads'],
        operationId: 'lead.index',
        summary: 'GET /api/leads — list the leads',
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

const ids = (operations: { method: string; path: string }[]) =>
  operations.map((op) => `${op.method} ${op.path}`)

describe('parseOpenApiOperations', () => {
  const operations = parseOpenApiOperations(document)

  it('flattens the operations sorted by path, then by method', () => {
    expect(ids(operations)).toEqual(['GET /leads', 'POST /leads', 'GET /leads/{lead}', 'GET /sources'])
  })

  it('uses the operationId as id, falling back to method:path', () => {
    expect(operations[0].id).toBe('lead.index')
    expect(operations[1].id).toBe('POST:/leads')
  })

  it('resolves component parameters and merges path-level ones', () => {
    expect(operations[0].parameters).toEqual([
      { name: 'page', location: 'query', required: false, schema: { type: 'integer' }, description: null },
      { name: 'per_page', location: 'query', required: false, schema: { type: 'integer' }, description: null },
    ])
    expect(operations[2].parameters[0]).toMatchObject({ name: 'lead', location: 'path', required: true })
  })

  it('resolves the request body and the first 2xx response schema', () => {
    const [list, create] = operations
    expect(create.requestSchema).toMatchObject({ required: ['registry_id'] })
    expect(list.responseStatus).toBe('200')
    expect(create.responseStatus).toBe('201')
    expect(list.requestSchema).toBeNull()
  })

  it('cleans the generated summary lead-in', () => {
    expect(operations[0].summary).toBe('list the leads')
    expect(operations[1].summary).toBe('Create lead')
  })

  it('returns an empty list for a document without paths', () => {
    expect(parseOpenApiOperations({ openapi: '3.1.0' })).toEqual([])
  })
})

describe('cleanSummary', () => {
  it('drops the method and path lead-in and collapses whitespace', () => {
    expect(cleanSummary('POST /api/leads — create a\nnew lead')).toBe('create a new lead')
    expect(cleanSummary('GET /api/activity-log/{resource}/{id}')).toBeNull()
    expect(cleanSummary(undefined)).toBeNull()
    expect(cleanSummary('Plain sentence')).toBe('Plain sentence')
  })
})

describe('filterOperations', () => {
  const operations = parseOpenApiOperations(document)
  const none = new Set<ApiMethod>()

  it('keeps everything for a blank query and no method', () => {
    expect(filterOperations(operations, { query: '  ', methods: none })).toHaveLength(4)
  })

  it('matches the path, case-insensitively', () => {
    expect(ids(filterOperations(operations, { query: 'SOURCES', methods: none }))).toEqual(['GET /sources'])
  })

  it('matches the summary, the tag and the operation id', () => {
    expect(ids(filterOperations(operations, { query: 'create lead', methods: none }))).toEqual(['POST /leads'])
    expect(filterOperations(operations, { query: 'lookups', methods: none })).toHaveLength(1)
    expect(ids(filterOperations(operations, { query: 'lead.index', methods: none }))).toEqual(['GET /leads'])
  })

  it('combines the text query with the method chips', () => {
    const post = new Set<ApiMethod>(['POST'])
    expect(ids(filterOperations(operations, { query: 'leads', methods: post }))).toEqual(['POST /leads'])
    expect(filterOperations(operations, { query: 'sources', methods: post })).toEqual([])
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
