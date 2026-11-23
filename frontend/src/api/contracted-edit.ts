import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

/** Spec 0404 E-D4: `code` of the 409 asking the user to confirm an edit of an offer under contract. */
export const CONTRACTED_EDIT_CONFIRMATION_CODE = 'contracted_edit_confirmation_required'

/** Body flag that tells the server the user has accepted the confirmation. */
export const CONTRACTED_EDIT_CONFIRM_FIELD = 'confirm_contracted_edit'

type ContractedEditConfirmer = () => Promise<boolean>

let confirmer: ContractedEditConfirmer | null = null

/** Registered by the UI bridge mounted under the confirm dialog provider. */
export function setContractedEditConfirmer(next: ContractedEditConfirmer | null): void {
  confirmer = next
}

export function getContractedEditConfirmer(): ContractedEditConfirmer | null {
  return confirmer
}

const declinedErrors = new WeakSet<object>()

export function markContractedEditDeclined(error: AxiosError): void {
  declinedErrors.add(error)
}

/** True when the error is a 409 the user chose not to confirm: a cancelled edit, not a failure to report. */
export function isContractedEditDeclined(error: unknown): boolean {
  return typeof error === 'object' && error !== null && declinedErrors.has(error)
}

export function isContractedEditConfirmationError(error: unknown): error is AxiosError<{ code?: string }> {
  return (
    axios.isAxiosError<{ code?: string }>(error) &&
    error.response?.status === 409 &&
    error.response.data?.code === CONTRACTED_EDIT_CONFIRMATION_CODE
  )
}

/**
 * The request body of the already-sent request with the confirmation flag added,
 * or null when it cannot carry one (already confirmed, or a body that is neither
 * JSON nor FormData).
 */
export function withContractedEditConfirmation(config: InternalAxiosRequestConfig): unknown {
  const data: unknown = config.data

  if (data === undefined || data === null || data === '') {
    return { [CONTRACTED_EDIT_CONFIRM_FIELD]: true }
  }
  if (data instanceof FormData) {
    if (data.has(CONTRACTED_EDIT_CONFIRM_FIELD)) return null
    data.append(CONTRACTED_EDIT_CONFIRM_FIELD, '1')
    return data
  }
  if (typeof data !== 'string') return null

  try {
    const body: unknown = JSON.parse(data)
    if (body === null || typeof body !== 'object' || Array.isArray(body)) return null
    if (CONTRACTED_EDIT_CONFIRM_FIELD in body) return null
    return { ...body, [CONTRACTED_EDIT_CONFIRM_FIELD]: true }
  } catch {
    return null
  }
}
