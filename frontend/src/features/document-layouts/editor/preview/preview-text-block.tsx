import { twipsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import { PreviewRun } from '@/features/document-layouts/editor/preview/preview-run'
import type { TextBlock } from '@/features/document-layouts/layout-config'

const TEXT_ALIGN_CSS: Record<TextBlock['align'], string> = {
  left: 'left',
  center: 'center',
  right: 'right',
  justify: 'justify',
}

interface PreviewTextBlockProps {
  block: TextBlock
  labelFor: (variable: string) => string
}

export function PreviewTextBlock({ block, labelFor }: PreviewTextBlockProps) {
  return (
    <p
      style={{
        textAlign: TEXT_ALIGN_CSS[block.align] as 'left' | 'center' | 'right' | 'justify',
        marginTop: twipsToPx(block.space_before),
        marginBottom: twipsToPx(block.space_after),
        lineHeight: block.line_height,
      }}
      className="text-[10px] break-words text-foreground"
    >
      {block.runs.length === 0 ? (
        <span>&nbsp;</span>
      ) : (
        block.runs.map((run, index) => <PreviewRun key={index} run={run} labelFor={labelFor} />)
      )}
    </p>
  )
}
