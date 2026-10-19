import axios from 'axios'

/** How a rejected history write is told to the user (spec 0272): forbidden, not-the-last, field errors or other. */
export type ContractErrorKind = 'forbidden' | 'conflict' | 'validation' | 'unknown'

export interface ContractError {
  kind: ContractErrorKind
  /** Backend 422 `errors` bag, keyed by request field. */
  fieldErrors: Record<string, string[]>
}

const HTTP_FORBIDDEN = 403
const HTTP_CONFLICT = 409
const HTTP_UNPROCESSABLE = 422

export function classifyContractError(error: unknown): ContractError {
  if (!axios.isAxiosError(error)) {
    return { kind: 'unknown', fieldErrors: {} }
  }
  const data = error.response?.data as { errors?: Record<string, string[]> } | undefined
  switch (error.response?.status) {
    case HTTP_FORBIDDEN:
      return { kind: 'forbidden', fieldErrors: {} }
    case HTTP_CONFLICT:
      return { kind: 'conflict', fieldErrors: {} }
    case HTTP_UNPROCESSABLE:
      return { kind: 'validation', fieldErrors: data?.errors ?? {} }
    default:
      return { kind: 'unknown', fieldErrors: {} }
  }
}
