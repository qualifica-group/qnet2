import { describe, expect, it } from 'vitest'
import { buildOperationSamples, buildOperationUrl, buildRequestSamples } from '@/features/api-integrations/code-samples'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'

const operation: ParsedOperation = {
  id: 'lead.update',
  method: 'PUT',
  path: '/leads/{lead}',
  tag: 'Lead',
  summary: null,
  description: null,
  parameters: [],
  requestSchema: { type: 'object', properties: { name: { type: 'string' } } },
  responseStatus: null,
  responseSchema: null,
}

describe('buildOperationUrl', () => {
  it('joins base and path turning {param} into <param>', () => {
    expect(buildOperationUrl('https://qnet.test/api/', '/leads/{lead}')).toBe('https://qnet.test/api/leads/<lead>')
  })
})

describe('buildRequestSamples', () => {
  it('omits -X for GET and the body when absent', () => {
    const { curl, javascript } = buildRequestSamples({
      method: 'GET',
      url: 'https://qnet.test/api/leads',
      headers: { Authorization: 'Bearer k' },
      body: null,
    })
    expect(curl).toBe('curl "https://qnet.test/api/leads" \\\n  -H "Authorization: Bearer k"')
    expect(javascript).toContain('method: "GET"')
    expect(javascript).not.toContain('body:')
  })

  it('quotes single quotes of the body for the shell', () => {
    const { curl } = buildRequestSamples({ method: 'POST', url: 'u', headers: {}, body: { name: "O'Hara" } })
    expect(curl).toContain(`'\\''`)
  })
})

describe('buildOperationSamples', () => {
  it('compiles base URL, bearer key and a body generated from the schema', () => {
    const { curl, javascript } = buildOperationSamples(operation, 'https://qnet.test/api', '<key>')
    expect(curl).toContain('curl -X PUT "https://qnet.test/api/leads/<lead>"')
    expect(curl).toContain('-H "Authorization: Bearer <key>"')
    expect(curl).toContain('-H "Content-Type: application/json"')
    expect(curl).toContain('"name": "string"')
    expect(javascript).toContain('fetch("https://qnet.test/api/leads/<lead>"')
    expect(javascript).toContain('body: JSON.stringify(')
  })
})
