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

export type AiDriverName = 'openai' | 'anthropic'

export interface AiConversation {
  id: number
  title: string
  last_message_at: string | null
  created_at: string
}

export interface AiConversationDetail extends AiConversation {
  messages: AiMessage[]
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
  accepted_mimes: string[]
}

export interface AiAssistantConfig {
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

/** SSE events of `POST /conversations/{id}/messages`, discriminated on `event`. */
export type AiStreamEvent =
  | { event: 'message.created'; data: { user_message: AiMessage } }
  | { event: 'delta'; data: { text: string } }
  | {
      event: 'message.completed'
      data: { assistant_message: AiMessage; conversation: AiConversation }
    }
  | { event: 'error'; data: { message: string } }
