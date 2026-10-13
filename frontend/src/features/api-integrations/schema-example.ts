import { isJsonObject } from '@/features/api-integrations/openapi-types'
import type { JsonObject, JsonValue } from '@/features/api-integrations/openapi-types'
import { mergeAllOf } from '@/features/api-integrations/schema-fields'

const MAX_EXAMPLE_DEPTH = 6

function typeOf(schema: JsonObject): string | null {
  const types = Array.isArray(schema.type) ? schema.type : [schema.type]
  return types.find((type): type is string => typeof type === 'string' && type !== 'null') ?? null
}

function exampleOf(schema: JsonValue | null | undefined, depth: number): JsonValue {
  if (!isJsonObject(schema) || depth > MAX_EXAMPLE_DEPTH) {
    return null
  }
  const merged = mergeAllOf(schema)
  if ('example' in merged) {
    return merged.example
  }
  if ('default' in merged) {
    return merged.default
  }
  if (Array.isArray(merged.enum) && merged.enum.length > 0) {
    return merged.enum[0]
  }
  const union = merged.anyOf ?? merged.oneOf
  if (Array.isArray(union)) {
    const member = union.find((item) => !(isJsonObject(item) && item.type === 'null'))
    return exampleOf(member, depth + 1)
  }

  const type = typeOf(merged) ?? (isJsonObject(merged.properties) ? 'object' : null)
  switch (type) {
    case 'object':
      return Object.fromEntries(
        Object.entries(isJsonObject(merged.properties) ? merged.properties : {}).map(
          ([name, property]) => [name, exampleOf(property, depth + 1)],
        ),
      )
    case 'array':
      return [exampleOf(merged.items, depth + 1)]
    case 'string':
      return 'string'
    case 'integer':
    case 'number':
      return 0
    case 'boolean':
      return true
    default:
      return null
  }
}

/**
 * Builds a sample JSON value from a resolved schema: `example`/`default` win,
 * then the first enum value, then a placeholder per type (`"string"`, `0`,
 * `true`); arrays carry one element.
 */
export function buildExampleFromSchema(schema: JsonValue | null | undefined): JsonValue {
  return exampleOf(schema, 0)
}
