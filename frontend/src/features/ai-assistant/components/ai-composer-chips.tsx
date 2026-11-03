import { useTranslation } from 'react-i18next'
import { Paperclip, X } from 'lucide-react'
import { getAiMentionModule } from '@/features/ai-assistant/mention-modules'
import type { AiMentionInput } from '@/features/ai-assistant/types'

const CHIP_CLASS =
  'inline-flex max-w-full items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-xs'
const REMOVE_CLASS =
  'rounded-sm text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none'

interface Props {
  files: File[]
  mentions: AiMentionInput[]
  disabled: boolean
  onRemoveFile: (index: number) => void
  onRemoveMention: (index: number) => void
}

/** Removable chips of the attachments and mentions staged in the composer. */
export function AiComposerChips({ files, mentions, disabled, onRemoveFile, onRemoveMention }: Props) {
  const { t } = useTranslation()
  if (files.length === 0 && mentions.length === 0) {
    return null
  }
  return (
    <ul className="flex flex-wrap gap-1" aria-label={`${t('aiAssistant.composer.mentionsLabel')} / ${t('aiAssistant.composer.attachments')}`}>
      {mentions.map((mention, index) => {
        const definition = getAiMentionModule(mention.module)
        return (
          <li key={`${mention.module}-${mention.id}`} className={CHIP_CLASS}>
            <definition.icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span className="truncate">{mention.label}</span>
            <button
              type="button"
              disabled={disabled}
              className={REMOVE_CLASS}
              aria-label={t('aiAssistant.composer.removeMention', { label: mention.label })}
              onClick={() => onRemoveMention(index)}
            >
              <X className="size-3.5" aria-hidden="true" />
            </button>
          </li>
        )
      })}
      {files.map((file, index) => (
        <li key={`${file.name}-${index}`} className={CHIP_CLASS}>
          <Paperclip className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span className="truncate">{file.name}</span>
          <button
            type="button"
            disabled={disabled}
            className={REMOVE_CLASS}
            aria-label={t('aiAssistant.composer.removeAttachment', { name: file.name })}
            onClick={() => onRemoveFile(index)}
          >
            <X className="size-3.5" aria-hidden="true" />
          </button>
        </li>
      ))}
    </ul>
  )
}
