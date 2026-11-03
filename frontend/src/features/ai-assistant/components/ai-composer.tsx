import { useCallback, useId, useRef, type ChangeEvent, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { AtSign, Paperclip, SendHorizontal, Square } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { AiComposerChips } from '@/features/ai-assistant/components/ai-composer-chips'
import { AiMentionPicker } from '@/features/ai-assistant/components/ai-mention-picker'
import type { AiComposerController } from '@/features/ai-assistant/hooks/use-ai-composer'
import { moduleOptionId, useAiMentionPicker } from '@/features/ai-assistant/hooks/use-ai-mention-picker'
import type { AiMentionModule } from '@/features/ai-assistant/types'

interface Props {
  composer: AiComposerController
  allowedModules: readonly AiMentionModule[]
}

/** Message composer: draft, attachments, "@" mentions and send/stop (AC-021..AC-023). */
export function AiComposer({ composer, allowedModules }: Props) {
  const { t } = useTranslation()
  const { form, textareaRef, limits, files, mentions, content, isSending, failure } = composer
  const ids = { content: useId(), files: useId(), mentions: useId(), hint: useId(), modules: useId() }
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const { errors } = form.formState

  const removeToken = useCallback(
    (token: { start: number; end: number }) => {
      const current = form.getValues('content')
      form.setValue('content', current.slice(0, token.start) + current.slice(token.end), { shouldDirty: true })
    },
    [form],
  )
  const picker = useAiMentionPicker({
    allowedModules,
    canAddMention: mentions.length < limits.max_mentions,
    onRemoveToken: removeToken,
  })
  const contentField = form.register('content')
  // A local model may accept no attachments at all (spec 0347 D-15): then the button is not offered.
  const canAttach = limits.max_files > 0 && limits.accepted_mimes.length > 0
  const moduleStageOpen = picker.stage.kind === 'module'

  const dismissPicker = () => {
    picker.close()
    textareaRef.current?.focus()
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (picker.handleTextareaKeyDown(event)) {
      return
    }
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault()
      void composer.submit()
    }
  }

  const handleFiles = (event: ChangeEvent<HTMLInputElement>) => {
    composer.addFiles(Array.from(event.target.files ?? []))
    event.target.value = ''
  }

  const describedBy = [errors.content ? ids.content : null, ids.hint].filter(Boolean).join(' ')

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        void composer.submit()
      }}
      className="relative flex flex-col gap-1.5 border-t border-border bg-surface p-2"
      noValidate
    >
      {failure ? (
        <p role="alert" className="rounded-md border border-destructive/40 bg-card px-2 py-1 text-xs text-destructive">
          {t(`aiAssistant.errors.${failure}`)}
        </p>
      ) : null}

      <AiComposerChips
        files={files}
        mentions={mentions}
        disabled={isSending}
        onRemoveFile={composer.removeFile}
        onRemoveMention={composer.removeMention}
      />

      <AiMentionPicker
        picker={picker}
        moduleListId={ids.modules}
        onPick={(mention) => {
          composer.addMention(mention)
          dismissPicker()
        }}
        onDismiss={dismissPicker}
      />

      <Textarea
        {...contentField}
        ref={(element) => {
          contentField.ref(element)
          textareaRef.current = element
        }}
        rows={1}
        readOnly={isSending}
        aria-label={t('aiAssistant.composer.label')}
        placeholder={t('aiAssistant.composer.placeholder')}
        aria-invalid={Boolean(errors.content)}
        aria-describedby={describedBy}
        aria-controls={moduleStageOpen ? ids.modules : undefined}
        aria-activedescendant={moduleStageOpen && picker.modules.length > 0 ? moduleOptionId(ids.modules, picker.activeIndex) : undefined}
        aria-autocomplete="list"
        className="max-h-32 min-h-9 resize-none py-1.5 text-sm"
        onChange={(event) => {
          void contentField.onChange(event)
          picker.detectTrigger(event.target.value, event.target.selectionStart)
        }}
        onKeyDown={handleKeyDown}
      />

      {errors.content ? (
        <span id={ids.content} role="alert" className="text-xs text-destructive">
          {errors.content.message}
        </span>
      ) : null}
      {errors.files ? (
        <span id={ids.files} role="alert" className="text-xs text-destructive">
          {errors.files.message ?? errors.files.root?.message}
        </span>
      ) : null}
      {errors.mentions ? (
        <span id={ids.mentions} role="alert" className="text-xs text-destructive">
          {errors.mentions.message ?? errors.mentions.root?.message}
        </span>
      ) : null}

      <div className="flex items-center gap-1">
        {canAttach ? (
          <>
            <input
              ref={fileInputRef}
              type="file"
              multiple
              hidden
              tabIndex={-1}
              accept={limits.accepted_mimes.join(',')}
              onChange={handleFiles}
              aria-label={t('aiAssistant.composer.attach')}
            />
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              disabled={isSending}
              aria-label={t('aiAssistant.composer.attach')}
              aria-invalid={Boolean(errors.files)}
              aria-describedby={errors.files ? ids.files : undefined}
              onClick={() => fileInputRef.current?.click()}
            >
              <Paperclip className="size-3.5" />
            </Button>
          </>
        ) : null}
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          disabled={isSending || mentions.length >= limits.max_mentions}
          aria-label={t('aiAssistant.composer.mention')}
          aria-invalid={Boolean(errors.mentions)}
          aria-describedby={errors.mentions ? ids.mentions : undefined}
          onClick={() => {
            picker.openFromButton()
            textareaRef.current?.focus()
          }}
        >
          <AtSign className="size-3.5" />
        </Button>
        <p id={ids.hint} className="ml-1 hidden min-w-0 flex-1 truncate text-[0.7rem] text-muted-foreground sm:block">
          {t('aiAssistant.composer.hint')}
        </p>
        <div className="ml-auto">
          {isSending ? (
            <Button type="button" size="sm" variant="secondary" onClick={composer.cancel}>
              <Square className="size-3.5" />
              {t('aiAssistant.composer.stop')}
            </Button>
          ) : (
            <Button type="submit" size="sm" disabled={content.trim() === ''}>
              <SendHorizontal className="size-3.5" />
              {t('aiAssistant.composer.send')}
            </Button>
          )}
        </div>
      </div>
    </form>
  )
}
