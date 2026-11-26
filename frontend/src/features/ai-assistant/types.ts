/** Frozen data contract of spec 0347 (`GET/POST /api/ai-assistant/*`). */
export const AI_MENTION_MODULE_KEYS = [
  'companies',
  'company-sites',
  'referents',
  'registries',
  'leads',
  'opportunities',
  'campaigns',
  'quotes',
  'work-orders',
  'projects',
  'tasks',
  'products',
  'invoices',
  'purchase-invoices',
  'purchase-orders',
  'purchase-requests',
  'contracts',
  'users',
] as const

export type AiMentionModule = (typeof AI_MENTION_MODULE_KEYS)[number]

export type AiMessageRole = 'user' | 'assistant'

export type AiDriverName = 'openai' | 'anthropic' | 'openrouter'

export interface AiConversation {
  id: number
  title: string
  last_message_at: string | null
  created_at: string
}

export interface AiConversationDetail extends AiConversation {
  messages: AiMessage[]
  /** The turn that is not final yet, so a reloaded page resumes the polling (0433). */
  active_turn?: AiTurn | null
  /** True once the conversation was summarized the configured number of times: the chat offers a new one (D-5). */
  propose_new_conversation?: boolean
}

export interface AiMessageMention {
  module: AiMentionModule
  id: number
  label: string
}

export interface AiMessageAttachment {
  id: number
  original_name: string
  mime_type: string
  size: number
}

export interface AiMessage {
  id: number
  role: AiMessageRole
  content: string
  mentions: AiMessageMention[]
  attachments: AiMessageAttachment[]
  model: string | null
  input_tokens: number | null
  output_tokens: number | null
  created_at: string
}

export interface AiAssistantLimits {
  max_message_chars: number
  guide_max_chars: number
  max_mentions: number
  max_files: number
  max_file_size_kb: number
  /** False for a text-only model: the composer offers no attachment (spec 0433). */
  accepts_attachments: boolean
  accepted_mimes: string[]
  /** How often the browser reads the state of a running turn (0433, D-15). */
  poll_interval_ms: number
}

export interface AiAssistantConfig {
  /** Surfaces switched on by the server env (D-16), both off by default. */
  floating_enabled: boolean
  page_enabled: boolean
  configured: boolean
  driver: AiDriverName | null
  model: string | null
  limits: AiAssistantLimits
  mention_modules: AiMentionModule[]
}

export interface AiConversationsPage {
  items: AiConversation[]
  pagination: {
    total: number
    offset: number
    limit: number
    total_pages: number
  }
}

/** A mention picked in the composer, sent as `mentions[N][module|id]`. */
export interface AiMentionInput {
  module: AiMentionModule
  id: number
  label: string
}

export interface SendAiMessageInput {
  content: string
  mentions: AiMentionInput[]
  files: File[]
  pageGuideKey: string | null
  pageGuideText: string | null
  locale: 'it' | 'en'
}

export type AiTurnStatus =
  | 'queued'
  | 'running'
  | 'awaiting_confirmation'
  | 'completed'
  | 'failed'
  | 'cancelled'
  | 'limit_reached'

export type AiTurnStepTool = 'search_operations' | 'list_records' | 'run_read_code' | 'prepare_write'

export interface AiTurnStep {
  index: number
  tool: AiTurnStepTool
  /** Short label already translated by the server in the language of the turn. */
  label: string
  state: 'running' | 'done' | 'failed'
}

export type AiTurnErrorCode =
  | 'model_unavailable'
  | 'connector_unavailable'
  | 'link_lost'
  | 'too_many_steps'
  | 'turn_timeout'
  | 'stream_error'
  | 'switched_off'
  | 'limit_person'
  | 'limit_total'
  | 'internal'

export interface AiTurn {
  id: number
  conversation_id: number
  status: AiTurnStatus
  user_message_id: number
  /** Partial text while `running`, final text once the turn is closed. */
  assistant_text: string
  assistant_message: AiMessage | null
  steps: AiTurnStep[]
  /** The preview card belongs to ticket 05; here the field is carried, never read. */
  preview: unknown
  queue_position: number | null
  error_code: AiTurnErrorCode | null
  error_message: string | null
  created_at: string
  updated_at: string
  finished_at: string | null
}

/** Answer of `POST /conversations/{id}/messages` (202): the saved message and the queued turn. */
export interface AiMessageStarted {
  user_message: AiMessage
  turn: AiTurn
}
