/** Minimal OpenAPI 3.1 subset the documentation tab reads. */

export type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue }

export type JsonObject = { [key: string]: JsonValue }

export function isJsonObject(value: JsonValue | undefined): value is JsonObject {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export interface OpenApiParameter {
  name?: string
  in?: string
  required?: boolean
  description?: string
  schema?: JsonObject
  $ref?: string
}

export interface OpenApiOperation {
  operationId?: string
  tags?: string[]
  summary?: string
  description?: string
  parameters?: OpenApiParameter[]
  requestBody?: { content?: Record<string, { schema?: JsonObject }> }
  responses?: Record<string, { content?: Record<string, { schema?: JsonObject }> }>
}

export interface OpenApiPathItem {
  parameters?: OpenApiParameter[]
  [method: string]: OpenApiOperation | OpenApiParameter[] | undefined
}

export interface OpenApiDocument {
  openapi: string
  info?: { title?: string; version?: string; description?: string }
  servers?: { url: string }[]
  /** One tag per module (first path segment), with its description. */
  tags?: { name: string; description?: string }[]
  paths?: Record<string, OpenApiPathItem>
  components?: {
    schemas?: Record<string, JsonObject>
    parameters?: Record<string, OpenApiParameter>
  }
}
