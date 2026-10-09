/** Minimal OpenAPI 3.1 subset the documentation tab reads. */

export type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue }

export type JsonObject = { [key: string]: JsonValue }

export interface OpenApiParameter {
  name?: string
  in?: string
  required?: boolean
  description?: string
  schema?: JsonObject
  $ref?: string
}

export interface OpenApiOperation {
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
  info?: { title?: string; version?: string }
  servers?: { url: string }[]
  paths?: Record<string, OpenApiPathItem>
  components?: {
    schemas?: Record<string, JsonObject>
    parameters?: Record<string, OpenApiParameter>
  }
}
