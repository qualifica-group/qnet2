/**
 * Standard response envelope returned by the backend (BaseApiController).
 * Every successful endpoint wraps its payload in this shape.
 */
export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

/**
 * Error payload returned by the backend on validation/failure responses.
 */
export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
  /** Present on every 403 (spec 0327): the missing permission key, or a perimeter denial. */
  authorization?: { reason: 'missing_permission' | 'denied'; permission: string | null }
}

/**
 * Envelope variant used by authorization-aware endpoints (spec 0004): carries
 * the standard `data` payload plus a `permissions` block as a top-level
 * sibling (`BaseApiController::okWithPermissions`). Generic over the
 * permissions shape so this module stays agnostic of any feature's type.
 */
export interface ApiResponseWithPermissions<T, P> extends ApiResponse<T> {
  permissions: P
}
