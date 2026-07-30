import { useTranslation } from 'react-i18next'
import { twipsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import type { DividerBlock, SpacerBlock } from '@/features/document-layouts/layout-config'

export function PreviewSpacerBlock({ block }: { block: SpacerBlock }) {
  return <div style={{ height: block.height }} aria-hidden="true" />
}

export function PreviewDividerBlock({ block }: { block: DividerBlock }) {
  return (
    <hr
      style={{
        width: `${block.width_pct}%`,
        borderBottomWidth: Math.max(1, block.thickness / 8),
        borderColor: `#${block.color}`,
        marginTop: twipsToPx(block.space_before),
        marginBottom: twipsToPx(block.space_after),
      }}
    />
  )
}

export function PreviewPageBreakBlock() {
  const { t } = useTranslation()
  return (
    <div className="my-1 border-t border-dashed border-border py-0.5 text-center text-[9px] text-muted-foreground">
      {t('documentLayouts.editor.preview.pageBreak')}
    </div>
  )
}
