import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { HexColorField } from '@/features/document-layouts/editor/shared/hex-color-field'
import { IntegerField } from '@/features/document-layouts/editor/shared/integer-field'
import {
  FONT_SIZE_MAX,
  FONT_SIZE_MIN,
  MARGIN_TWIPS_MAX,
  MARGIN_TWIPS_MIN,
} from '@/features/document-layouts/layout-config-defaults'
import { PAGE_ORIENTATIONS } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutPage, PageMargins, PageOrientation } from '@/features/document-layouts/layout-config'

interface PageSettingsPanelProps {
  page: DocumentLayoutPage
  onChange: (next: DocumentLayoutPage) => void
  disabled?: boolean
}

/**
 * Orientation, the 4 margins and the default font (AC-124): every numeric
 * field is a bounded `IntegerField`/`HexColorField` (they only call
 * `onChange` once the value is valid, so an out-of-range margin never
 * reaches the emitted config).
 */
export function PageSettingsPanel({ page, onChange, disabled = false }: PageSettingsPanelProps) {
  const { t } = useTranslation()

  function updateMargin(key: keyof PageMargins, value: number) {
    onChange({ ...page, margins: { ...page.margins, [key]: value } })
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-1">
        <Label htmlFor="document-layout-page-orientation" className="text-xs text-muted-foreground">
          {t('documentLayouts.editor.page.orientation')}
        </Label>
        <Select
          value={page.orientation}
          onValueChange={(value) => onChange({ ...page, orientation: value as PageOrientation })}
          disabled={disabled}
        >
          <SelectTrigger id="document-layout-page-orientation" className="h-7 w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {PAGE_ORIENTATIONS.map((orientation) => (
              <SelectItem key={orientation} value={orientation}>
                {t(`documentLayouts.editor.page.orientations.${orientation}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <IntegerField
          label={t('documentLayouts.editor.page.marginTop')}
          value={page.margins.top}
          min={MARGIN_TWIPS_MIN}
          max={MARGIN_TWIPS_MAX}
          step={20}
          onCommit={(value) => updateMargin('top', value)}
          disabled={disabled}
        />
        <IntegerField
          label={t('documentLayouts.editor.page.marginRight')}
          value={page.margins.right}
          min={MARGIN_TWIPS_MIN}
          max={MARGIN_TWIPS_MAX}
          step={20}
          onCommit={(value) => updateMargin('right', value)}
          disabled={disabled}
        />
        <IntegerField
          label={t('documentLayouts.editor.page.marginBottom')}
          value={page.margins.bottom}
          min={MARGIN_TWIPS_MIN}
          max={MARGIN_TWIPS_MAX}
          step={20}
          onCommit={(value) => updateMargin('bottom', value)}
          disabled={disabled}
        />
        <IntegerField
          label={t('documentLayouts.editor.page.marginLeft')}
          value={page.margins.left}
          min={MARGIN_TWIPS_MIN}
          max={MARGIN_TWIPS_MAX}
          step={20}
          onCommit={(value) => updateMargin('left', value)}
          disabled={disabled}
        />
      </div>

      <div className="flex flex-col gap-2 border-t border-border pt-2">
        <p className="text-xs font-medium text-muted-foreground">{t('documentLayouts.editor.page.defaultFont')}</p>
        <div className="flex flex-col gap-1">
          <Label htmlFor="document-layout-font-family" className="text-xs text-muted-foreground">
            {t('documentLayouts.editor.page.fontFamily')}
          </Label>
          <Input
            id="document-layout-font-family"
            value={page.default_font.family}
            disabled={disabled}
            onChange={(event) =>
              onChange({ ...page, default_font: { ...page.default_font, family: event.target.value } })
            }
            className="h-7 text-xs"
          />
        </div>
        <div className="grid grid-cols-2 gap-2">
          <IntegerField
            label={t('documentLayouts.editor.page.fontSize')}
            value={page.default_font.size}
            min={FONT_SIZE_MIN}
            max={FONT_SIZE_MAX}
            onCommit={(size) => onChange({ ...page, default_font: { ...page.default_font, size } })}
            disabled={disabled}
          />
          <HexColorField
            label={t('documentLayouts.editor.page.fontColor')}
            value={page.default_font.color}
            onCommit={(color) => onChange({ ...page, default_font: { ...page.default_font, color } })}
            disabled={disabled}
          />
        </div>
      </div>
    </div>
  )
}
