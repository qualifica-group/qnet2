/** Contracts of `/api/ai-assistant/{settings,models,models/providers}` (spec 0433), super-admin only. */

export type RoutingSort = 'throughput' | 'price' | 'latency'
export type DataCollection = 'deny' | 'allow'

export interface AiRouting {
  zdr: boolean
  data_collection: DataCollection
  require_parameters: boolean
  sort: RoutingSort
}

export interface AiAssistantSettings {
  enabled: boolean
  model_id: string
  instructions: string
  instructions_max_chars: number
  spend_cap_person_usd: string | null
  spend_cap_total_usd: string | null
  routing: AiRouting
  /** Whether the server holds the OpenRouter key; the value never reaches the browser. */
  openrouter_key_present: boolean
  updated_at: string | null
  updated_by: { id: number; name: string } | null
}

export type UpdateAiAssistantSettingsPayload = Pick<
  AiAssistantSettings,
  'enabled' | 'model_id' | 'instructions' | 'spend_cap_person_usd' | 'spend_cap_total_usd' | 'routing'
>

/** Prices are dollars per million tokens, as strings. */
export interface AiModelPricing {
  prompt: string | null
  completion: string | null
  input_cache_read: string | null
  request: string | null
}

export interface AiModelOption {
  id: string
  name: string
  context_length: number
  pricing: AiModelPricing
  selected: boolean
}

export interface AiModelsPage {
  items: AiModelOption[]
  pagination: { total: number | null; offset: number; limit: number }
}

export interface AiModelProvider {
  name: string
  tag: string
  zdr: boolean
  uptime_pct: number | null
  throughput_tps: number | null
  latency_ms: number | null
  pricing: Pick<AiModelPricing, 'prompt' | 'completion' | 'input_cache_read'>
}
