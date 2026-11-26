/** Contract of GET /api/ai-assistant/costs (spec 0433): figures and counts only, never message text. Money is a decimal string in dollars. */
export interface AiCostTotals {
  cost_usd: string
  requests: number
  input_tokens: number
  output_tokens: number
  cache_read_tokens: number
  reasoning_tokens: number
}

export interface AiCostDay {
  date: string
  cost_usd: string
  requests: number
}

export interface AiCostFunction {
  business_function_id: number | null
  name: string | null
  cost_usd: string
  requests: number
}

export interface AiCostUser {
  user_id: number
  name: string | null
  cost_usd: string
  requests: number
  input_tokens: number
  output_tokens: number
}

export interface AiCostModel {
  model_id: string
  cost_usd: string
  requests: number
  input_tokens: number
  output_tokens: number
}

export interface AiCostCaps {
  month: string
  spent_total_usd: string
  spend_cap_total_usd: string | null
  spend_cap_person_usd: string | null
}

export interface AiCostReport {
  period: { from: string; to: string }
  totals: AiCostTotals
  by_day: AiCostDay[]
  by_function: AiCostFunction[]
  by_user: AiCostUser[]
  by_model: AiCostModel[]
  caps: AiCostCaps
}

/** Inclusive period, both `YYYY-MM-DD`. */
export interface AiCostPeriod {
  from: string
  to: string
}
