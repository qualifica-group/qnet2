import type { JsonValue } from '@/features/api-integrations/openapi-types'
import type { ParsedOperation } from '@/features/api-integrations/openapi-operations'
import { buildExampleFromSchema } from '@/features/api-integrations/schema-example'

export const CODE_LANGUAGES = ['curl', 'javascript'] as const

export type CodeLanguage = (typeof CODE_LANGUAGES)[number]

export type CodeSamples = Record<CodeLanguage, string>

export interface RequestSpec {
  method: string
  url: string
  headers: Record<string, string>
  body: JsonValue | null
}

const PATH_PARAMETER = /\{([^}]+)\}/g

function shellQuote(value: string): string {
  return `'${value.replace(/'/g, `'\\''`)}'`
}

function buildCurl({ method, url, headers, body }: RequestSpec): string {
  const lines = [`curl${method === 'GET' ? '' : ` -X ${method}`} "${url}"`]
  for (const [name, value] of Object.entries(headers)) {
    lines.push(`-H "${name}: ${value}"`)
  }
  if (body !== null) {
    lines.push(`-d ${shellQuote(JSON.stringify(body, null, 2))}`)
  }
  return lines.join(' \\\n  ')
}

function buildFetch({ method, url, headers, body }: RequestSpec): string {
  const headerLines = Object.entries(headers).map(([name, value]) => `    "${name}": "${value}",`)
  const options = [`  method: "${method}",`, '  headers: {', ...headerLines, '  },']
  if (body !== null) {
    const json = JSON.stringify(body, null, 2).replace(/\n/g, '\n  ')
    options.push(`  body: JSON.stringify(${json}),`)
  }
  return [`const response = await fetch("${url}", {`, ...options, '});', 'const data = await response.json();'].join('\n')
}

/** The same request rendered as a curl command and as a `fetch` call. */
export function buildRequestSamples(spec: RequestSpec): CodeSamples {
  return { curl: buildCurl(spec), javascript: buildFetch(spec) }
}

/** `{lead}` becomes `<lead>`: braces would be globbed by curl, angle brackets read as "fill me in". */
export function buildOperationUrl(baseUrl: string, path: string): string {
  return `${baseUrl.replace(/\/+$/, '')}${path.replace(PATH_PARAMETER, '<$1>')}`
}

/** Ready-to-run samples of an operation: base URL, bearer key and a body generated from its schema. */
export function buildOperationSamples(
  operation: ParsedOperation,
  baseUrl: string,
  keyPlaceholder: string,
): CodeSamples {
  const body = operation.requestSchema ? buildExampleFromSchema(operation.requestSchema) : null
  return buildRequestSamples({
    method: operation.method,
    url: buildOperationUrl(baseUrl, operation.path),
    headers: {
      Authorization: `Bearer ${keyPlaceholder}`,
      Accept: 'application/json',
      ...(body === null ? {} : { 'Content-Type': 'application/json' }),
    },
    body,
  })
}
