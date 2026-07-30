import { useRef } from 'react'
import { Bold, Italic, Trash2, Underline } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { HexColorField } from '@/features/document-layouts/editor/shared/hex-color-field'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import { useCaretInsertion } from '@/features/document-layouts/editor/variables/use-caret-insertion'
import { FONT_SIZE_MAX, FONT_SIZE_MIN } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutRun } from '@/features/document-layouts/layout-config'

const DEFAULT_RUN_COLOR = '000000'

interface RunEditorProps {
  run: DocumentLayoutRun
  onChange: (next: DocumentLayoutRun) => void
  onRemove: () => void
  /** Called with this run's caret-insertion function whenever its text control gains focus (AC-122). */
  onActivate: (insert: (token: string) => void) => void
  disabled: boolean
}

/**
 * One run's controls (AC-121): text (or a read-only note when `field` is
 * set, AC-125), bold/italic/underline toggles, font family, size and color.
 * Registers itself as the variable picker's active insertion target on
 * focus via `onActivate` — caret tracking itself is `use-caret-insertion.ts`.
 */
export function RunEditor({ run, onChange, onRemove, onActivate, disabled }: RunEditorProps) {
  const { t } = useTranslation()
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const { trackSelection, insertAtCaret } = useCaretInsertion(textareaRef, run.text, (text) => onChange({ ...run, text }))

  const colorEnabled = run.color !== null

  return (
    <div className="flex flex-col gap-1.5 rounded-md border border-border p-2">
      {run.field ? (
        <p className="rounded bg-muted px-2 py-1 text-xs text-muted-foreground">
          {t(`documentLayouts.editor.textBlock.fieldRun.${run.field}`)}
        </p>
      ) : (
        <Textarea
          ref={textareaRef}
          value={run.text}
          disabled={disabled}
          rows={2}
          onChange={(event) => onChange({ ...run, text: event.target.value })}
          onSelect={trackSelection}
          onFocus={() => onActivate(insertAtCaret)}
          aria-label={t('documentLayouts.editor.textBlock.runText')}
          className="text-xs"
        />
      )}

      <div className="flex flex-wrap items-center gap-1">
        <Button
          type="button"
          variant={run.bold ? 'secondary' : 'ghost'}
          size="icon-xs"
          aria-pressed={run.bold}
          aria-label={t('documentLayouts.editor.textBlock.bold')}
          disabled={disabled}
          onClick={() => onChange({ ...run, bold: !run.bold })}
        >
          <Bold aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant={run.italic ? 'secondary' : 'ghost'}
          size="icon-xs"
          aria-pressed={run.italic}
          aria-label={t('documentLayouts.editor.textBlock.italic')}
          disabled={disabled}
          onClick={() => onChange({ ...run, italic: !run.italic })}
        >
          <Italic aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant={run.underline ? 'secondary' : 'ghost'}
          size="icon-xs"
          aria-pressed={run.underline}
          aria-label={t('documentLayouts.editor.textBlock.underline')}
          disabled={disabled}
          onClick={() => onChange({ ...run, underline: !run.underline })}
        >
          <Underline aria-hidden="true" />
        </Button>
        <Input
          value={run.font ?? ''}
          disabled={disabled}
          placeholder={t('documentLayouts.editor.textBlock.font')}
          aria-label={t('documentLayouts.editor.textBlock.font')}
          onChange={(event) => onChange({ ...run, font: event.target.value || null })}
          className="h-6 w-24 text-xs"
        />
        <IntegerField
          label=""
          ariaLabel={t('documentLayouts.editor.textBlock.size')}
          className={cn('w-16')}
          value={run.size ?? FONT_SIZE_MIN}
          min={FONT_SIZE_MIN}
          max={FONT_SIZE_MAX}
          onCommit={(size) => onChange({ ...run, size })}
          disabled={disabled}
        />
        <label className="flex items-center gap-1 text-xs text-muted-foreground">
          <input
            type="checkbox"
            checked={colorEnabled}
            disabled={disabled}
            onChange={(event) =>
              onChange({ ...run, color: event.target.checked ? DEFAULT_RUN_COLOR : null })
            }
          />
          {t('documentLayouts.editor.textBlock.color')}
        </label>
        {colorEnabled && (
          <HexColorField
            label=""
            ariaLabel={t('documentLayouts.editor.textBlock.color')}
            value={run.color ?? DEFAULT_RUN_COLOR}
            onCommit={(color) => onChange({ ...run, color })}
            disabled={disabled}
          />
        )}
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="ml-auto text-muted-foreground hover:text-destructive"
          aria-label={t('documentLayouts.editor.textBlock.removeRun')}
          disabled={disabled}
          onClick={onRemove}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>
    </div>
  )
}
