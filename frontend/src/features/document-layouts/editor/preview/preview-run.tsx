import type { CSSProperties } from 'react'
import { splitVariableSegments } from '@/features/document-layouts/editor/preview/variable-chip-utils'
import type { DocumentLayoutRun, RunField } from '@/features/document-layouts/layout-config'

/** Placeholder value shown in place of an actual computed Word field (AC-125: rendered as "1", not raw text). */
const PAGE_FIELD_PLACEHOLDERS: Record<RunField, string> = { page: '1', total_pages: '1' }

interface PreviewRunProps {
  run: DocumentLayoutRun
  labelFor: (variable: string) => string
}

function runStyle(run: DocumentLayoutRun): CSSProperties {
  return {
    fontWeight: run.bold ? 700 : undefined,
    fontStyle: run.italic ? 'italic' : undefined,
    textDecoration: run.underline ? 'underline' : undefined,
    fontFamily: run.font ?? undefined,
    fontSize: run.size ? `${run.size}pt` : undefined,
    color: run.color ? `#${run.color}` : undefined,
  }
}

/** Renders one formatted run: a computed-field placeholder (AC-125) or its text with variable chips (AC-127). */
export function PreviewRun({ run, labelFor }: PreviewRunProps) {
  const style = runStyle(run)

  if (run.field) {
    return (
      <span style={style} className="rounded bg-muted px-1 text-muted-foreground" aria-label={run.field}>
        {PAGE_FIELD_PLACEHOLDERS[run.field]}
      </span>
    )
  }

  const segments = splitVariableSegments(run.text, labelFor)
  return (
    <span style={style}>
      {segments.map((segment, index) =>
        segment.kind === 'text' ? (
          <span key={index}>{segment.value}</span>
        ) : (
          <span key={index} className="rounded bg-primary/10 px-1 text-primary">
            {segment.label}
          </span>
        ),
      )}
    </span>
  )
}
