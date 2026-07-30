import { useTranslation } from 'react-i18next'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import { SPACER_HEIGHT_MAX, SPACER_HEIGHT_MIN } from '@/features/document-layouts/layout-config-defaults'
import type { SpacerBlock } from '@/features/document-layouts/layout-config'

interface SpacerBlockInspectorProps {
  block: SpacerBlock
  onChange: (next: SpacerBlock) => void
  disabled: boolean
}

export function SpacerBlockInspector({ block, onChange, disabled }: SpacerBlockInspectorProps) {
  const { t } = useTranslation()
  return (
    <IntegerField
      label={t('documentLayouts.editor.spacer.height')}
      value={block.height}
      min={SPACER_HEIGHT_MIN}
      max={SPACER_HEIGHT_MAX}
      onCommit={(height) => onChange({ ...block, height })}
      disabled={disabled}
    />
  )
}
