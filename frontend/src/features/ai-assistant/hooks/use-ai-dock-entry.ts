import { useLocation } from 'react-router-dom'
import { useAbilities } from '@/features/auth/use-abilities'
import { useAiAssistantConfig } from '@/features/ai-assistant/hooks/use-ai-assistant-config'
import { AI_ASSISTANT_PERMISSION, AI_ASSISTANT_ROUTE } from '@/features/ai-assistant/routes'
import { stripWorkspacePrefix } from '@/features/workspaces/workspace-path'

/**
 * Whether the assistant appears as a conversation of the chat dock (spec
 * 0432, replacing the floating launcher): the same rules the launcher had,
 * `ai-assistant.use` (AC-019), the server's floating switch (D-16), and not
 * on the assistant page itself, which already is the chat. The config is not
 * asked without the permission.
 */
export function useAiDockEntry(): boolean {
  const { can } = useAbilities()
  const { pathname } = useLocation()
  const allowed = can(AI_ASSISTANT_PERMISSION)
  const config = useAiAssistantConfig({ enabled: allowed })

  return allowed && config.data?.floating_enabled === true && !stripWorkspacePrefix(pathname).startsWith(AI_ASSISTANT_ROUTE)
}
