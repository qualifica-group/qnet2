export const aiAssistantKeys = {
  all: ['ai-assistant'] as const,
  config: ['ai-assistant', 'config'] as const,
  conversations: ['ai-assistant', 'conversations'] as const,
  /** Prefix of every cached conversation detail. */
  conversationDetails: ['ai-assistant', 'conversation'] as const,
  conversation: (id: number) => ['ai-assistant', 'conversation', id] as const,
  mentionSearch: (module: string, term: string) =>
    ['ai-assistant', 'mention-search', module, term] as const,
}
