import { useTranslation } from 'react-i18next'
import { Check, Loader2, Pencil } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { AttributeLayoutRows } from '@/features/attributes/attribute-layout-rows'
import type { AttributeLayoutFormShape, LayoutSection } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * The inline-edit key of a whole section's popup: never a form path, so no
 * single attribute row opens alongside it.
 */
function attributeSectionEditingKey(sectionId: string): string {
  return `attribute_sections.${sectionId}`
}

type DialogSize = 'md' | 'lg' | 'xl'

/** A Tabella (e.g. "Verifiche ispettive") needs the room of tabular content, a wide section that of its columns. */
function dialogSize(section: LayoutSection, attributesByCode: ReadonlyMap<string, EffectiveAttribute>): DialogSize {
  const hasTable = section.rows.some((row) =>
    row.items.some((item) => attributesByCode.get(item.attribute_code)?.type === 'table'),
  )
  if (hasTable) {
    return 'xl'
  }
  return section.columns >= 2 ? 'lg' : 'md'
}

interface AttributeSectionEditDialogProps<TFieldValues extends AttributeLayoutFormShape> {
  section: LayoutSection
  /** The section's attributes as the field leaf edits them; those absent are not in the popup. */
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  /** Shown but not editable (a Commessa value owned by an integration). */
  readOnlyCodes?: ReadonlySet<string>
  control: Control<TFieldValues>
  inline: InlineEdit
}

/**
 * The "edit the whole section" action of a flexible-fields section on a
 * record detail (Offerta, Commessa; user directive 2026-11-27): a pencil in
 * the section's heading band opens a popup with every field of the section,
 * laid out as the form lays it (`AttributeLayoutRows`). It is one more editor
 * of the record's in-place editing: Save submits the edit form, whose diff
 * PATCH sends every value changed here at once; Cancel, Esc or closing the
 * popup discards them.
 */
export function AttributeSectionEditDialog<TFieldValues extends AttributeLayoutFormShape>({
  section,
  attributesByCode,
  readOnlyCodes,
  control,
  inline,
}: AttributeSectionEditDialogProps<TFieldValues>) {
  const { t } = useTranslation()
  const editingKey = attributeSectionEditingKey(section.id)
  const open = inline.editingField === editingKey
  const label = t('attributes.layout.editSection', { section: section.title })

  // A save in flight keeps the popup: its result (success or error) lands here.
  const handleOpenChange = (next: boolean) => {
    if (!next && !inline.isSaving) {
      inline.cancel()
    }
  }

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="shrink-0 text-muted-foreground"
        aria-label={label}
        title={label}
        disabled={inline.isSaving}
        onClick={() => inline.start(editingKey)}
      >
        <Pencil className="size-3.5" aria-hidden="true" />
      </Button>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent size={dialogSize(section, attributesByCode)}>
          <DialogHeader>
            <DialogTitle>{section.title}</DialogTitle>
            <DialogDescription>{section.description ?? t('attributes.layout.editSectionDescription')}</DialogDescription>
          </DialogHeader>
          {/* Scrolls on this inner wrapper: the content node must not clip portaled pickers (dialog.tsx). */}
          <div className="@container -mx-1 flex max-h-[70vh] flex-col gap-3 overflow-y-auto px-1 py-0.5">
            <AttributeLayoutRows
              section={section}
              attributesByCode={attributesByCode}
              control={control}
              disabled={inline.isSaving}
              readOnly={false}
              readOnlyCodes={readOnlyCodes}
            />
          </div>
          {inline.error ? (
            <p role="alert" className="text-xs font-medium text-destructive">
              {inline.error}
            </p>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" size="sm" onClick={inline.cancel} disabled={inline.isSaving}>
              {inline.cancelLabel}
            </Button>
            <Button type="button" size="sm" onClick={inline.save} disabled={inline.isSaving}>
              {inline.isSaving ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Check aria-hidden="true" />}
              {inline.confirmLabel}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
