import { useConfig } from '@/features/config/use-config'

/**
 * Whether the project chat is switched on for the installation (CHAT_ENABLED,
 * served by GET /api/config, spec 0432). Off, the dock asks no channel and
 * keeps only the AI assistant; the API answers 404 anyway.
 */
export function useChatEnabled(): boolean {
  return useConfig().data?.features?.chat === true
}
