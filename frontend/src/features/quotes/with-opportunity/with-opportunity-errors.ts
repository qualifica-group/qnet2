import axios from 'axios'

const OPPORTUNITY_PREFIX = 'opportunity.'
const QUOTE_PREFIX = 'quote.'
const EXISTING_OPPORTUNITY_KEY = 'opportunity.existing_opportunity_id'
const DUPLICATE_MESSAGE_KEY = 'opportunity.registry_id'

/** One refused field of a block: its form field (first path segment) and the server's message. */
export interface BlockFieldError {
  field: string
  message: string
}

/** The anti-duplicate refusal of the guard run inside the create (spec 0396 D-6b). */
export interface DuplicateRefusal {
  opportunityId: number
  message: string
}

/** A 422 of `POST /quotes/with-opportunity`, split per block (keys are prefixed `opportunity.`/`quote.`). */
export interface WithOpportunityRefusal {
  opportunity: BlockFieldError[]
  quote: BlockFieldError[]
  duplicate: DuplicateRefusal | null
  /** Every distinct reason, for the foreground toast. */
  messages: string[]
}

function blockErrors(errors: Record<string, string[]>, prefix: string): BlockFieldError[] {
  const seen = new Set<string>()
  const result: BlockFieldError[] = []

  for (const [key, messages] of Object.entries(errors)) {
    const message = messages?.[0]
    if (!key.startsWith(prefix) || !message) {
      continue
    }
    // `offer_lines.0.product_id` lands on the `offer_lines` collection error: rows own no field of their own.
    const field = key.slice(prefix.length).split('.')[0]
    if (!seen.has(field)) {
      seen.add(field)
      result.push({ field, message })
    }
  }

  return result
}

function readDuplicate(errors: Record<string, string[]>): DuplicateRefusal | null {
  const opportunityId = Number(errors[EXISTING_OPPORTUNITY_KEY]?.[0])
  const message = errors[DUPLICATE_MESSAGE_KEY]?.[0]

  return Number.isInteger(opportunityId) && opportunityId > 0 && message ? { opportunityId, message } : null
}

/** Splits a 422 per block; `null` when the failure is any other one. */
export function readWithOpportunityRefusal(error: unknown): WithOpportunityRefusal | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }

  const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
  const duplicate = readDuplicate(errors)
  const opportunity = blockErrors(errors, OPPORTUNITY_PREFIX).filter(
    // The duplicate refusal owns its alert: it is not repeated as a field error.
    (entry) => duplicate === null || (entry.field !== 'existing_opportunity_id' && entry.field !== 'registry_id'),
  )
  const quote = blockErrors(errors, QUOTE_PREFIX)
  const messages = Object.values(errors).flatMap((list) => list?.[0] ?? [])

  return { opportunity, quote, duplicate, messages }
}
