/** Shapes of the frozen spec 0210 admin contract (`/api/api-clients`). */

export interface ApiClientCreator {
  id: number
  name: string
}

/** Technical user (spec 0210) that authors the writes made with the key alone. */
export type ApiClientServiceUser = ApiClientCreator

/** HTTP methods a client may be allowed (spec 0392): PUT also covers PATCH. */
export type ApiClientMethod = 'GET' | 'POST' | 'PUT' | 'DELETE'

/** `ApiClientResource`: never carries the plain-text key nor its hash. */
export interface ApiClient {
  id: number
  name: string
  description: string | null
  rate_limit_per_minute: number | null
  effective_rate_limit_per_minute: number
  allowed_methods: ApiClientMethod[]
  expires_at: string | null
  is_active: boolean
  is_expired: boolean
  key_last_four: string | null
  last_used_at: string | null
  service_user: ApiClientServiceUser
  created_by: ApiClientCreator | null
  created_at: string
  updated_at: string
}

/** Create and rotate-key responses: the only places the plain-text key travels. */
export interface ApiClientWithKey {
  client: ApiClient
  plain_text_key: string
}

/** Body of POST (all fields) and PATCH (any subset) `/api-clients`. */
export interface ApiClientPayload {
  name?: string
  description?: string | null
  rate_limit_per_minute?: number | null
  allowed_methods?: ApiClientMethod[]
  expires_at?: string | null
  is_active?: boolean
}

export type ApiDocKind = 'openapi' | 'postman'

/** State of the generated documentation: it is produced only on demand (Refresh, nightly, deploy). */
export interface ApiDocsStatus {
  status: 'ready' | 'generating' | 'missing'
  /** ISO 8601 instant of the document being served, null when none exists yet. */
  generated_at: string | null
}
