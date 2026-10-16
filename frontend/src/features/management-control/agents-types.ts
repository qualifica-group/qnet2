/* Spec 0272: GET /api/management-control/agents. */

export const AGENTS_TOPS = [10, 20, 50] as const
export type AgentsTop = (typeof AGENTS_TOPS)[number]

/** Default number of agents shown, the rest being summed in the "others" row. */
export const DEFAULT_AGENTS_TOP: AgentsTop = 20

/** Commission figures of an agent, or of a group of agents. */
export interface AgentFigures {
  /** Signed sum of the accruals dated in the period. */
  accrued: number
  /** As of today: accruals not yet in a settlement. */
  to_settle: number
  /** As of today: net payable of the settlements issued or with the document received. */
  in_statement: number
  /** As of today: net payable of the approved settlements, minus what their purchase invoice has already paid. */
  approved_unpaid: number
  /** Paid in the period, on the purchase invoice of the settlements. */
  paid: number
}

export interface AgentRow extends AgentFigures {
  referent: { id: number; name: string }
  /** Settlements issued and still waiting for the agent document. */
  pending_document_count: number
}

export interface AgentTotals extends AgentFigures {
  agents_count: number
  pending_document_count: number
}

/** The agents beyond the top N, summed. */
export interface AgentOthers extends AgentFigures {
  agents_count: number
  pending_document_count: number
}

export interface AgentMonthRow {
  /** `YYYY-MM`. */
  month: string
  accrued: number
  paid: number
}

export interface Agents {
  applied: {
    date_from: string | null
    date_to: string | null
    company_id: number[]
    top: number
  }
  today: string
  totals: AgentTotals
  agents: AgentRow[]
  others: AgentOthers | null
  /** Every month of the period, in order. */
  by_month: AgentMonthRow[]
}

/** Filter state of the agents page (UI side; mapped to the request by `toAgentsParams`). */
export interface AgentsFilters {
  dateFrom: string
  dateTo: string
  companyIds: number[]
  top: AgentsTop
}

/** Request query parameters of the agents, named exactly as the contract. */
export interface AgentsParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  top: AgentsTop
}
