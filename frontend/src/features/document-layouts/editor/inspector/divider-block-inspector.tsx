import { useTranslation } from 'react-i18next'
import { HexColorField } from '@/features/document-layouts/editor/shared/hex-color-field'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import {
  DIVIDER_THICKNESS_MAX,
  DIVIDER_THICKNESS_MIN,
  WIDTH_PCT_MAX,
  WIDTH_PCT_MIN,
} from '@/features/document-layouts/layout-config-defaults'
import type { DividerBlock } from '@/features/document-layouts/layout-config'

interface DividerBlockInspectorProps {
  block: DividerBlock
  onChange: (next: DividerBlock) => void
  disabled: boolean
}

/** The `divider` block editor (D-11): width, thickness (eighths of a point) and color of the separator rule. */
export function DividerBlockInspector({ block, onChange, disabled }: DividerBlockInspectorProps) {
  const { t } = useTranslation()
  return (
    <div className="grid grid-cols-2 gap-2">
      <IntegerField
        label={t('documentLayouts.editor.divider.widthPct')}
        value={block.width_pct}
        min={WIDTH_PCT_MIN}
        max={WIDTH_PCT_MAX}
        onCommit={(width_pct) => onChange({ ...block, width_pct })}
        disabled={disabled}
      />
      <IntegerField
        label={t('documentLayouts.editor.divider.thickness')}
        value={block.thickness}
        min={DIVIDER_THICKNESS_MIN}
        max={DIVIDER_THICKNESS_MAX}
        onCommit={(thickness) => onChange({ ...block, thickness })}
        disabled={disabled}
      />
      <IntegerField
        label={t('documentLayouts.editor.divider.spaceBefore')}
        value={block.space_before}
        min={0}
        max={5670}
        step={20}
        onCommit={(space_before) => onChange({ ...block, space_before })}
        disabled={disabled}
      />
      <IntegerField
        label={t('documentLayouts.editor.divider.spaceAfter')}
        value={block.space_after}
        min={0}
        max={5670}
        step={20}
        onCommit={(space_after) => onChange({ ...block, space_after })}
        disabled={disabled}
      />
      <HexColorField
        label={t('documentLayouts.editor.divider.color')}
        value={block.color}
        onCommit={(color) => onChange({ ...block, color })}
        disabled={disabled}
      />
    </div>
  )
}
