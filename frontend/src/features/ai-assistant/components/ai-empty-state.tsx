import { useTranslation } from 'react-i18next'
import { Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/button'

const SUGGESTION_KEYS = ['usePage', 'summarize', 'draft'] as const

interface Props {
  onPickSuggestion: (text: string) => void
}

/** Empty conversation: a short hint and a few starter prompts that fill the draft. */
export function AiEmptyState({ onPickSuggestion }: Props) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-3 p-4 text-center">
      <Sparkles className="size-6 text-muted-foreground" aria-hidden="true" />
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-semibold">{t('aiAssistant.empty.title')}</h3>
        <p className="text-xs text-muted-foreground">{t('aiAssistant.empty.description')}</p>
      </div>
      <ul className="flex w-full max-w-sm flex-col gap-1.5">
        {SUGGESTION_KEYS.map((key) => {
          const text = t(`aiAssistant.empty.suggestions.${key}`)
          return (
            <li key={key}>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-auto w-full justify-start whitespace-normal py-1.5 text-left text-xs"
                onClick={() => onPickSuggestion(text)}
              >
                {text}
              </Button>
            </li>
          )
        })}
      </ul>
      <p className="max-w-sm text-[0.7rem] text-muted-foreground">{t('aiAssistant.composer.privacyNote')}</p>
    </div>
  )
}
