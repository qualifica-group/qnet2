import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AttributeLayoutRowEditor } from '@/features/attributes/layout-configurator/attribute-layout-row-editor'
import type { LayoutConfiguratorActions } from '@/features/attributes/layout-configurator/use-layout-configurator-actions'
import {
  LAYOUT_COLUMNS_OPTIONS,
  LAYOUT_SECTION_VARIANTS,
  type LayoutColumns,
  type LayoutSection,
  type LayoutSectionVariant,
} from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutSectionEditorProps {
  section: LayoutSection
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  isFirst: boolean
  isLast: boolean
  disabled: boolean
  actions: LayoutConfiguratorActions
}

const DESCRIPTION_MAX_LENGTH = 500
const TITLE_MAX_LENGTH = 191

/**
 * One section's full editing surface (spec 0062 AC-010): title/description,
 * appearance (`ConfigSectionVariant`), grid columns, collapsible/advanced
 * flags, move/remove, and its rows (each an `AttributeLayoutRowEditor`).
 *
 * Hosted inside the category edit form's `bg-card` FormSection (ui-design.md
 * §1-bis rung 3, the frontmost rung): repeating `bg-card` here would read as
 * a flush duplicate of the container it sits on, so the box is set apart with
 * the `--muted` tint instead — the same sub-block language as the palette and
 * preview panel it sits alongside.
 */
export function AttributeLayoutSectionEditor({
  section,
  attributesByCode,
  isFirst,
  isLast,
  disabled,
  actions,
}: AttributeLayoutSectionEditorProps) {
  const { t } = useTranslation('attributeLayout')
  const titleId = `attribute-layout-section-title-${section.id}`
  const descriptionId = `attribute-layout-section-description-${section.id}`
  const collapsibleId = `attribute-layout-section-collapsible-${section.id}`
  const defaultCollapsedId = `attribute-layout-section-default-collapsed-${section.id}`

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-3">
      <div className="flex items-start gap-2">
        <div className="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
          <div className="flex flex-col gap-1">
            <Label htmlFor={titleId}>{t('configurator.sectionTitleLabel')}</Label>
            <Input
              id={titleId}
              value={section.title}
              maxLength={TITLE_MAX_LENGTH}
              placeholder={t('configurator.sectionTitlePlaceholder')}
              disabled={disabled}
              onChange={(event) => actions.updateSection(section.id, { title: event.target.value })}
            />
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor={descriptionId}>{t('configurator.sectionDescriptionLabel')}</Label>
            <Textarea
              id={descriptionId}
              value={section.description ?? ''}
              rows={1}
              maxLength={DESCRIPTION_MAX_LENGTH}
              className="min-h-9 text-sm"
              disabled={disabled}
              onChange={(event) =>
                actions.updateSection(section.id, { description: event.target.value === '' ? null : event.target.value })
              }
            />
          </div>
        </div>
        <div className="flex shrink-0 flex-col">
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('configurator.moveSectionUpLabel')}
            disabled={disabled || isFirst}
            onClick={() => actions.moveSectionUp(section.id)}
          >
            <ChevronUp aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('configurator.moveSectionDownLabel')}
            disabled={disabled || isLast}
            onClick={() => actions.moveSectionDown(section.id)}
          >
            <ChevronDown aria-hidden="true" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="text-muted-foreground hover:text-destructive"
            aria-label={t('configurator.removeSectionLabel')}
            disabled={disabled}
            onClick={() => actions.removeSection(section.id)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-1.5">
          <Label htmlFor={`attribute-layout-section-variant-${section.id}`} className="text-xs font-normal text-muted-foreground">
            {t('configurator.sectionVariantLabel')}
          </Label>
          <Select
            value={section.variant}
            onValueChange={(value) => actions.updateSection(section.id, { variant: value as LayoutSectionVariant })}
            disabled={disabled}
          >
            <SelectTrigger id={`attribute-layout-section-variant-${section.id}`} size="sm" className="h-7 w-32 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {LAYOUT_SECTION_VARIANTS.map((variant) => (
                <SelectItem key={variant} value={variant}>
                  {t(`configurator.variant.${variant}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-1.5">
          <Label htmlFor={`attribute-layout-section-columns-${section.id}`} className="text-xs font-normal text-muted-foreground">
            {t('configurator.sectionColumnsLabel')}
          </Label>
          <Select
            value={String(section.columns)}
            onValueChange={(value) => actions.updateSection(section.id, { columns: Number(value) as LayoutColumns })}
            disabled={disabled}
          >
            <SelectTrigger id={`attribute-layout-section-columns-${section.id}`} size="sm" className="h-7 w-16 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {LAYOUT_COLUMNS_OPTIONS.map((columns) => (
                <SelectItem key={columns} value={String(columns)}>
                  {columns}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-1.5">
          <Switch
            id={collapsibleId}
            checked={section.collapsible}
            disabled={disabled}
            onCheckedChange={(checked) => actions.updateSection(section.id, { collapsible: checked })}
          />
          <Label htmlFor={collapsibleId} className="text-xs font-normal text-muted-foreground">
            {t('configurator.sectionCollapsibleLabel')}
          </Label>
        </div>

        <div className="flex items-center gap-1.5">
          <Switch
            id={defaultCollapsedId}
            checked={section.default_collapsed}
            disabled={disabled || !section.collapsible}
            onCheckedChange={(checked) => actions.updateSection(section.id, { default_collapsed: checked })}
          />
          <Label htmlFor={defaultCollapsedId} className="text-xs font-normal text-muted-foreground">
            {t('configurator.sectionDefaultCollapsedLabel')}
          </Label>
        </div>

      </div>

      <div className="flex flex-col gap-2">
        {section.rows.map((row, index) => (
          <AttributeLayoutRowEditor
            key={row.id}
            row={row}
            attributesByCode={attributesByCode}
            disabled={disabled}
            isFirst={index === 0}
            isLast={index === section.rows.length - 1}
            onWidthChange={(code, width) => actions.updateItemWidth(code, width)}
            onRemoveItem={(code) => actions.removeItemToPalette(code)}
            onRemoveRow={() => actions.removeRow(section.id, row.id)}
            onMoveUp={() => actions.moveRowUp(section.id, row.id)}
            onMoveDown={() => actions.moveRowDown(section.id, row.id)}
          />
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="border-dashed text-muted-foreground hover:text-foreground"
          disabled={disabled}
          onClick={() => actions.addRow(section.id)}
        >
          <Plus aria-hidden="true" />
          {t('configurator.addRow')}
        </Button>
      </div>
    </div>
  )
}
