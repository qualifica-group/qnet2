import { Suspense, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router-dom'
import { Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import { AI_ASSISTANT_PERMISSION, AI_ASSISTANT_ROUTE } from '@/features/ai-assistant/routes'
import { stripWorkspacePrefix } from '@/features/workspaces/workspace-path'
import { cn } from '@/lib/utils'
import { lazyRoute } from '@/routes/lazy-route'

const FloatingAiPanel = lazyRoute(() => import('@/features/ai-assistant/components/floating-ai-panel'))

/**
 * Floating assistant: a launcher at the bottom right that opens a chat window
 * (full screen below `sm`). Only for users with `ai-assistant.use` (AC-019)
 * and hidden on the assistant page itself, which already is the chat.
 */
export function FloatingAiAssistant() {
  return (
    <Can permission={AI_ASSISTANT_PERMISSION}>
      <FloatingAiAssistantLauncher />
    </Can>
  )
}

function FloatingAiAssistantLauncher() {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)
  const [hasOpened, setHasOpened] = useState(false)

  if (stripWorkspacePrefix(pathname).startsWith(AI_ASSISTANT_ROUTE)) {
    return null
  }

  return (
    <>
      {hasOpened ? (
        <Suspense fallback={null}>
          <FloatingAiPanel open={open} onClose={() => setOpen(false)} />
        </Suspense>
      ) : null}
      <Button
        type="button"
        size="icon-lg"
        aria-label={open ? t('aiAssistant.closeButton') : t('aiAssistant.openButton')}
        aria-expanded={open}
        className={cn('fixed right-4 bottom-4 z-40 rounded-full shadow-lg', open && 'max-sm:hidden')}
        onClick={() => {
          setHasOpened(true)
          setOpen((current) => !current)
        }}
      >
        <Sparkles className="size-4" />
      </Button>
    </>
  )
}
