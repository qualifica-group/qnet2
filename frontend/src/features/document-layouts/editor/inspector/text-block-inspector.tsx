import { AlignCenter, AlignJustify, AlignLeft, AlignRight, Hash, Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import { RunEditor } from '@/features/document-layouts/editor/inspector/run-editor'
import { createDefaultRun } from '@/features/document-layouts/layout-config-defaults'
import { MAX_RUNS_PER_BLOCK } from '@/features/document-layouts/layout-config-defaults'
import { TEXT_ALIGNS } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutRun, RunField, TextAlign, TextBlock } from '@/features/document-layouts/layout-config'

const ALIGN_ICONS: Record<TextAlign, typeof AlignLeft> = {
  left: AlignLeft,
  center: AlignCenter,
  right: AlignRight,
  justify: AlignJustify,
}

interface TextBlockInspectorProps {
  block: TextBlock
  onChange: (next: TextBlock) => void
  /** Registers the currently focused run's caret-insertion function (AC-122). */
  onActivate: (insert: (token: string) => void) => void
  disabled: boolean
}

/**
 * The `text` block editor (AC-121): paragraph-level alignment/spacing/line
 * height, plus a list of per-run controls. Page-numbering (AC-125) is two
 * toolbar buttons that append a `field`-only run — a run with `field` set
 * ignores `text` server-side (spec 0069 `config_schema`).
 */
export function TextBlockInspector({ block, onChange, onActivate, disabled }: TextBlockInspectorProps) {
  const { t } = useTranslation()
  const runsFull = block.runs.length >= MAX_RUNS_PER_BLOCK

  function updateRun(index: number, next: DocumentLayoutRun) {
    onChange({ ...block, runs: block.runs.map((run, i) => (i === index ? next : run)) })
  }

  function removeRun(index: number) {
    onChange({ ...block, runs: block.runs.filter((_, i) => i !== index) })
  }

  function addRun(field: RunField | null = null) {
    if (runsFull) {
      return
    }
    onChange({ ...block, runs: [...block.runs, createDefaultRun(field)] })
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-1">
        {TEXT_ALIGNS.map((align) => {
          const Icon = ALIGN_ICONS[align]
          return (
            <Button
              key={align}
              type="button"
              variant={block.align === align ? 'secondary' : 'ghost'}
              size="icon-xs"
              aria-pressed={block.align === align}
              aria-label={t(`documentLayouts.editor.textBlock.align.${align}`)}
              disabled={disabled}
              onClick={() => onChange({ ...block, align })}
            >
              <Icon aria-hidden="true" />
            </Button>
          )
        })}
      </div>

      <div className="grid grid-cols-3 gap-2">
        <IntegerField
          label={t('documentLayouts.editor.textBlock.spaceBefore')}
          value={block.space_before}
          min={0}
          max={5670}
          step={20}
          onCommit={(space_before) => onChange({ ...block, space_before })}
          disabled={disabled}
        />
        <IntegerField
          label={t('documentLayouts.editor.textBlock.spaceAfter')}
          value={block.space_after}
          min={0}
          max={5670}
          step={20}
          onCommit={(space_after) => onChange({ ...block, space_after })}
          disabled={disabled}
        />
        <div className="flex flex-col gap-1">
          <label className="text-xs text-muted-foreground" htmlFor="text-block-line-height">
            {t('documentLayouts.editor.textBlock.lineHeight')}
          </label>
          <input
            id="text-block-line-height"
            type="number"
            min={1}
            max={3}
            step={0.1}
            value={block.line_height}
            disabled={disabled}
            onChange={(event) => {
              const parsed = Number(event.target.value)
              if (!Number.isNaN(parsed) && parsed >= 1 && parsed <= 3) {
                onChange({ ...block, line_height: parsed })
              }
            }}
            className="h-7 rounded-md border border-field-border bg-field px-2 text-xs"
          />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        {block.runs.map((run, index) => (
          <RunEditor
            key={index}
            run={run}
            onChange={(next) => updateRun(index, next)}
            onRemove={() => removeRun(index)}
            onActivate={onActivate}
            disabled={disabled}
          />
        ))}
      </div>

      <div className="flex flex-wrap gap-1.5">
        <Button type="button" variant="outline" size="xs" disabled={disabled || runsFull} onClick={() => addRun()}>
          <Plus aria-hidden="true" />
          {t('documentLayouts.editor.textBlock.addRun')}
        </Button>
        <Button type="button" variant="outline" size="xs" disabled={disabled || runsFull} onClick={() => addRun('page')}>
          <Hash aria-hidden="true" />
          {t('documentLayouts.editor.textBlock.insertPageNumber')}
        </Button>
        <Button
          type="button"
          variant="outline"
          size="xs"
          disabled={disabled || runsFull}
          onClick={() => addRun('total_pages')}
        >
          <Hash aria-hidden="true" />
          {t('documentLayouts.editor.textBlock.insertTotalPages')}
        </Button>
      </div>
    </div>
  )
}
