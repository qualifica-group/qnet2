import { isJsonObject } from '@/features/api-integrations/openapi-types'
import type {
  JsonObject,
  JsonValue,
  OpenApiDocument,
  OpenApiOperation,
  OpenApiParameter,
} from '@/features/api-integrations/openapi-types'

/** Display order of the methods (also the order of the filter chips). */
export const API_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as const

export type ApiMethod = (typeof API_METHODS)[number]

const SCHEMA_REF_PREFIX = '#/components/schemas/'
const PARAMETER_REF_PREFIX = '#/components/parameters/'
const JSON_MEDIA_TYPE = 'application/json'
const DEFAULT_TAG = 'default'
const SUCCESS_STATUS = /^2\d\d$/

export interface ParsedParameter {
  name: string
  location: string
  required: boolean
  schema: JsonValue | null
  description: string | null
}

export interface ParsedOperation {
  /** The OpenAPI `operationId` (also the deep-link hash), `method:path` when absent. */
  id: string
  method: ApiMethod
  path: string
  tag: string
  summary: string | null
  description: string | null
  parameters: ParsedParameter[]
  requestSchema: JsonValue | null
  responseStatus: string | null
  responseSchema: JsonValue | null
}

/**
 * Inlines every `$ref` into components.schemas. A reference met again while
 * its own expansion is still open (a cycle) is left as a marked stub so the
 * output stays finite.
 */
export function resolveSchema(
  node: JsonValue,
  schemas: Record<string, JsonObject>,
  stack: readonly string[] = [],
): JsonValue {
  if (Array.isArray(node)) {
    return node.map((item) => resolveSchema(item, schemas, stack))
  }
  if (!isJsonObject(node)) {
    return node
  }
  const ref = node.$ref
  if (typeof ref === 'string' && ref.startsWith(SCHEMA_REF_PREFIX)) {
    const name = ref.slice(SCHEMA_REF_PREFIX.length)
    const target = schemas[name]
    if (!target) {
      return { $ref: ref }
    }
    if (stack.includes(name)) {
      return { $ref: ref, circular: true }
    }
    return resolveSchema(target, schemas, [...stack, name])
  }
  return Object.fromEntries(
    Object.entries(node).map(([key, value]) => [key, resolveSchema(value, schemas, stack)]),
  )
}

function resolveParameter(
  parameter: OpenApiParameter,
  document: OpenApiDocument,
): OpenApiParameter | null {
  if (parameter.$ref?.startsWith(PARAMETER_REF_PREFIX)) {
    return document.components?.parameters?.[parameter.$ref.slice(PARAMETER_REF_PREFIX.length)] ?? null
  }
  return parameter
}

function parseParameters(
  shared: OpenApiParameter[],
  own: OpenApiParameter[],
  document: OpenApiDocument,
  schemas: Record<string, JsonObject>,
): ParsedParameter[] {
  const merged = new Map<string, ParsedParameter>()
  for (const raw of [...shared, ...own]) {
    const parameter = resolveParameter(raw, document)
    if (!parameter?.name || !parameter.in) {
      continue
    }
    const location = parameter.in
    merged.set(`${location}:${parameter.name}`, {
      name: parameter.name,
      location,
      required: parameter.required === true || location === 'path',
      schema: parameter.schema ? resolveSchema(parameter.schema, schemas) : null,
      description: parameter.description ?? null,
    })
  }
  return [...merged.values()]
}

function jsonSchemaOf(content: Record<string, { schema?: JsonObject }> | undefined): JsonObject | null {
  return content?.[JSON_MEDIA_TYPE]?.schema ?? null
}

function pickSuccessResponse(
  operation: OpenApiOperation,
  schemas: Record<string, JsonObject>,
): { status: string | null; schema: JsonValue | null } {
  const status = Object.keys(operation.responses ?? {}).find((code) => SUCCESS_STATUS.test(code))
  if (!status) {
    return { status: null, schema: null }
  }
  const schema = jsonSchemaOf(operation.responses?.[status]?.content)
  return { status, schema: schema ? resolveSchema(schema, schemas) : null }
}

/** Drops the generated `GET /api/path — ` lead-in of a summary, keeping only the human sentence. */
export function cleanSummary(summary: string | undefined): string | null {
  const text = (summary ?? '')
    .replace(/^(GET|POST|PUT|PATCH|DELETE)\s+\S+\s*(\u2014\s*)?/, '')
    .replace(/\s+/g, ' ')
    .trim()
  return text === '' ? null : text
}

function methodRank(method: ApiMethod): number {
  return API_METHODS.indexOf(method)
}

/**
 * Flattens an OpenAPI document into its operations, sorted by path and then
 * by method. Everything shown in the documentation tab comes from here: no
 * operation list is hard-coded.
 */
export function parseOpenApiOperations(document: OpenApiDocument): ParsedOperation[] {
  const schemas = document.components?.schemas ?? {}
  const operations: ParsedOperation[] = []

  for (const [path, item] of Object.entries(document.paths ?? {})) {
    const shared = Array.isArray(item.parameters) ? item.parameters : []
    for (const method of API_METHODS) {
      const operation = item[method.toLowerCase()]
      if (!operation || Array.isArray(operation)) {
        continue
      }
      const requestSchema = jsonSchemaOf(operation.requestBody?.content)
      const response = pickSuccessResponse(operation, schemas)
      operations.push({
        id: operation.operationId ?? `${method}:${path}`,
        method,
        path,
        tag: operation.tags?.[0] ?? DEFAULT_TAG,
        summary: cleanSummary(operation.summary),
        description: operation.description?.trim() || null,
        parameters: parseParameters(shared, operation.parameters ?? [], document, schemas),
        requestSchema: requestSchema ? resolveSchema(requestSchema, schemas) : null,
        responseStatus: response.status,
        responseSchema: response.schema,
      })
    }
  }

  return operations.sort(
    (a, b) => a.path.localeCompare(b.path) || methodRank(a.method) - methodRank(b.method),
  )
}

export interface OperationFilters {
  query: string
  methods: ReadonlySet<ApiMethod>
}

/** Case-insensitive match on path, summary, tag or operation id; a blank query matches all. */
export function matchesQuery(operation: ParsedOperation, query: string): boolean {
  const needle = query.trim().toLowerCase()
  return (
    needle === '' ||
    [operation.path, operation.summary ?? '', operation.tag, operation.id].some((field) =>
      field.toLowerCase().includes(needle),
    )
  )
}

/** Applies the text query and the method chips (an empty method set means "all methods"). */
export function filterOperations(
  operations: ParsedOperation[],
  { query, methods }: OperationFilters,
): ParsedOperation[] {
  return operations.filter(
    (operation) =>
      (methods.size === 0 || methods.has(operation.method)) && matchesQuery(operation, query),
  )
}
