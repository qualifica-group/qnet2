import type {
  JsonObject,
  JsonValue,
  OpenApiDocument,
  OpenApiOperation,
  OpenApiParameter,
} from '@/features/api-integrations/openapi-types'

const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'] as const
const SCHEMA_REF_PREFIX = '#/components/schemas/'
const PARAMETER_REF_PREFIX = '#/components/parameters/'
const JSON_MEDIA_TYPE = 'application/json'
const DEFAULT_TAG = 'default'
const SUCCESS_STATUS = /^2\d\d$/

export interface ParsedParameter {
  name: string
  location: string
  required: boolean
  type: string
  description: string | null
}

export interface ParsedOperation {
  id: string
  method: string
  path: string
  tag: string
  summary: string | null
  description: string | null
  parameters: ParsedParameter[]
  requestSchema: JsonValue | null
  responseStatus: string | null
  responseSchema: JsonValue | null
}

export interface OperationGroup {
  tag: string
  operations: ParsedOperation[]
}

function isObject(value: JsonValue | undefined): value is JsonObject {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
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
  if (!isObject(node)) {
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

/** Short human type of a resolved schema: `string`, `integer|null`, `array<object>`, `a | b`. */
export function schemaTypeLabel(schema: JsonValue | undefined): string {
  if (!isObject(schema)) {
    return 'any'
  }
  const type = schema.type
  if (Array.isArray(type)) {
    return type.join('|')
  }
  if (type === 'array') {
    return `array<${schemaTypeLabel(schema.items)}>`
  }
  if (typeof type === 'string') {
    return typeof schema.format === 'string' ? `${type} (${schema.format})` : type
  }
  const union = schema.anyOf ?? schema.oneOf
  if (Array.isArray(union)) {
    return union.map((member) => schemaTypeLabel(member)).join(' | ')
  }
  return isObject(schema.properties) ? 'object' : 'any'
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
      type: schemaTypeLabel(parameter.schema ? resolveSchema(parameter.schema, schemas) : undefined),
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

/**
 * Flattens an OpenAPI document into operations grouped by their first tag
 * (groups and operations keep document order). Everything shown in the
 * documentation tab comes from here: no operation list is hard-coded.
 */
export function parseOpenApiOperations(document: OpenApiDocument): OperationGroup[] {
  const schemas = document.components?.schemas ?? {}
  const groups = new Map<string, ParsedOperation[]>()

  for (const [path, item] of Object.entries(document.paths ?? {})) {
    const shared = Array.isArray(item.parameters) ? item.parameters : []
    for (const method of HTTP_METHODS) {
      const operation = item[method]
      if (!operation || Array.isArray(operation)) {
        continue
      }
      const tag = operation.tags?.[0] ?? DEFAULT_TAG
      const requestSchema = jsonSchemaOf(operation.requestBody?.content)
      const response = pickSuccessResponse(operation, schemas)
      const parsed: ParsedOperation = {
        id: `${method}:${path}`,
        method: method.toUpperCase(),
        path,
        tag,
        summary: operation.summary ?? null,
        description: operation.description ?? null,
        parameters: parseParameters(shared, operation.parameters ?? [], document, schemas),
        requestSchema: requestSchema ? resolveSchema(requestSchema, schemas) : null,
        responseStatus: response.status,
        responseSchema: response.schema,
      }
      groups.set(tag, [...(groups.get(tag) ?? []), parsed])
    }
  }

  return [...groups.entries()].map(([tag, operations]) => ({ tag, operations }))
}

/**
 * Keeps the operations whose path, summary or tag contains `query`
 * (case-insensitive); groups left empty are dropped. A blank query keeps all.
 */
export function filterOperationGroups(groups: OperationGroup[], query: string): OperationGroup[] {
  const needle = query.trim().toLowerCase()
  if (needle === '') {
    return groups
  }
  return groups
    .map((group) => ({
      tag: group.tag,
      operations: group.operations.filter((operation) =>
        [operation.path, operation.summary ?? '', operation.tag].some((field) =>
          field.toLowerCase().includes(needle),
        ),
      ),
    }))
    .filter((group) => group.operations.length > 0)
}
