import { isJsonObject } from '@/features/api-integrations/openapi-types'
import type { JsonObject, JsonValue } from '@/features/api-integrations/openapi-types'
import type { ParsedParameter } from '@/features/api-integrations/openapi-operations'

/** Deepest nesting level shown in the field table (root fields are depth 0). */
const MAX_FIELD_DEPTH = 5
const NULL_TYPE = 'null'

export interface SchemaDescription {
  type: string
  nullable: boolean
  enumValues: JsonValue[] | null
}

export interface SchemaField extends SchemaDescription {
  /** Dotted path from the root, unique within one table. */
  path: string
  name: string
  depth: number
  required: boolean
  description: string | null
  /** Where a parameter travels (`query`, `path`, ...); null for body fields. */
  location: string | null
}

function stringList(value: JsonValue | undefined): string[] {
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []
}

/** Collapses `allOf` into one schema: properties merged, `required` united, first-seen keys kept. */
export function mergeAllOf(schema: JsonObject): JsonObject {
  const { allOf, ...rest } = schema
  if (!Array.isArray(allOf)) {
    return schema
  }
  return allOf.reduce<JsonObject>((merged, member) => {
    if (!isJsonObject(member)) {
      return merged
    }
    const part = mergeAllOf(member)
    const properties =
      isJsonObject(merged.properties) || isJsonObject(part.properties)
        ? {
            ...(isJsonObject(merged.properties) ? merged.properties : {}),
            ...(isJsonObject(part.properties) ? part.properties : {}),
          }
        : undefined
    const required = [...new Set([...stringList(merged.required), ...stringList(part.required)])]
    return {
      ...part,
      ...merged,
      ...(properties ? { properties } : {}),
      ...(required.length > 0 ? { required } : {}),
    }
  }, rest)
}

function isNullSchema(schema: JsonValue): boolean {
  return isJsonObject(schema) && schema.type === NULL_TYPE
}

/** The non-null members of an `anyOf`/`oneOf` union, or null when the schema has none. */
function unionMembers(schema: JsonObject): JsonValue[] | null {
  const union = schema.anyOf ?? schema.oneOf
  return Array.isArray(union) ? union.filter((member) => !isNullSchema(member)) : null
}

function hasNullMember(schema: JsonObject): boolean {
  const union = schema.anyOf ?? schema.oneOf
  return Array.isArray(union) && union.some(isNullSchema)
}

/** Human type of a resolved schema plus its nullability and enum values. */
export function describeSchema(schema: JsonValue | null | undefined): SchemaDescription {
  if (!isJsonObject(schema)) {
    return { type: 'any', nullable: false, enumValues: null }
  }
  const merged = mergeAllOf(schema)
  const enumValues = Array.isArray(merged.enum) ? merged.enum : null
  const types = (Array.isArray(merged.type) ? merged.type : [merged.type]).filter(
    (type): type is string => typeof type === 'string',
  )
  const nullable =
    merged.nullable === true || types.includes(NULL_TYPE) || hasNullMember(merged)
  const concrete = types.filter((type) => type !== NULL_TYPE)

  const members = unionMembers(merged)
  if (concrete.length === 0 && members && members.length > 0) {
    const described = members.map((member) => describeSchema(member))
    return {
      type: [...new Set(described.map((item) => item.type))].join(' | '),
      nullable: nullable || described.some((item) => item.nullable),
      enumValues: described.length === 1 ? described[0].enumValues : enumValues,
    }
  }

  const [first] = concrete
  let type = 'any'
  if (first === 'array') {
    type = `array<${describeSchema(merged.items).type}>`
  } else if (first) {
    type = typeof merged.format === 'string' ? `${first} (${merged.format})` : first
  } else if (isJsonObject(merged.properties) || merged.circular === true) {
    type = 'object'
  }
  return { type: concrete.length > 1 ? concrete.join('|') : type, nullable, enumValues }
}

/** The object schema whose properties become table rows (unwraps arrays and single-member unions). */
function objectSchemaOf(schema: JsonValue | undefined): JsonObject | null {
  if (!isJsonObject(schema)) {
    return null
  }
  const merged = mergeAllOf(schema)
  if (isJsonObject(merged.properties)) {
    return merged
  }
  if (merged.type === 'array') {
    return objectSchemaOf(merged.items)
  }
  const members = unionMembers(merged)
  return members?.length === 1 ? objectSchemaOf(members[0]) : null
}

function collectFields(schema: JsonValue | undefined, depth: number, prefix: string): SchemaField[] {
  const object = objectSchemaOf(schema)
  if (!object || !isJsonObject(object.properties)) {
    return []
  }
  const required = new Set(stringList(object.required))
  return Object.entries(object.properties).flatMap(([name, property]) => {
    const path = prefix === '' ? name : `${prefix}.${name}`
    const described = describeSchema(property)
    const field: SchemaField = {
      ...described,
      path,
      name,
      depth,
      required: required.has(name),
      description:
        isJsonObject(property) && typeof property.description === 'string' ? property.description : null,
      location: null,
    }
    const children = depth + 1 < MAX_FIELD_DEPTH ? collectFields(property, depth + 1, path) : []
    return [field, ...children]
  })
}

/**
 * Flattens a resolved schema into table rows in reading order. Nested objects
 * (also through arrays) follow their parent with a larger `depth`; the cap
 * keeps the output finite, `$ref` cycles are already cut by `resolveSchema`.
 */
export function flattenSchemaFields(schema: JsonValue | null): SchemaField[] {
  return schema ? collectFields(schema, 0, '') : []
}

export function parametersToFields(parameters: ParsedParameter[]): SchemaField[] {
  return parameters.map((parameter) => ({
    ...describeSchema(parameter.schema),
    path: `${parameter.location}:${parameter.name}`,
    name: parameter.name,
    depth: 0,
    required: parameter.required,
    description: parameter.description,
    location: parameter.location,
  }))
}
