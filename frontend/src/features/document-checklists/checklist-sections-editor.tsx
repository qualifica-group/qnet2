import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { CHECKLIST_MAX_SECTIONS, type DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'
import { useChecklistSections } from '@/features/document-checklists/use-checklist-sections'
import { cn } from '@/lib/utils'

const ICON_BUTTON_CLASS = 'size-7 bg-card'

interface SectionRowProps {
  index: number
  count: number
  itemCount: number
  onMoveUp: () => void
  onMoveDown: () => void
  onRemove: () => void
}

/** One group: name and description inputs, how many items use it, up/down and delete. */
function SectionRow({ index, count, itemCount, onMoveUp, onMoveDown, onRemove }: SectionRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<DocumentChecklistFormValues>()
  const heading = t('documentChecklists.section.heading', { position: index + 1 })
  const label = (action: string) => `${action}: ${heading}`

  return (
    <li className="rounded-lg border bg-card p-3">
      <div className="mb-2 flex items-center gap-1.5">
        <h4 className="min-w-0 flex-1 truncate text-sm font-semibold">{heading}</h4>
        <span className="text-xs text-muted-foreground">{t('documentChecklists.section.itemCount', { count: itemCount })}</span>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={ICON_BUTTON_CLASS}
          aria-label={label(t('documentChecklists.actions.moveUp'))}
          disabled={index === 0}
          onClick={onMoveUp}
        >
          <ArrowUp className="size-3.5" aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={ICON_BUTTON_CLASS}
          aria-label={label(t('documentChecklists.actions.moveDown'))}
          disabled={index === count - 1}
          onClick={onMoveDown}
        >
          <ArrowDown className="size-3.5" aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={cn(ICON_BUTTON_CLASS, 'text-destructive')}
          aria-label={label(t('documentChecklists.actions.removeSection'))}
          onClick={onRemove}
        >
          <Trash2 className="size-3.5" aria-hidden="true" />
        </Button>
      </div>
      <div className="grid grid-cols-1 gap-3 @lg:grid-cols-2">
        <FormField
          control={control}
          name={`sections.${index}.name`}
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">
                {t('documentChecklists.section.name')}
              </FormLabel>
              <FormControl>
                <Input {...field} className="h-8" />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <FormField
          control={control}
          name={`sections.${index}.description`}
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel className="text-xs">{t('documentChecklists.section.description')}</FormLabel>
              <FormControl>
                <Input {...field} className="h-8" />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      </div>
    </li>
  )
}

/** The "Gruppi di documenti" card body (spec 0427): each group becomes a tab in the customer portal. */
export function ChecklistSectionsEditor() {
  const { t } = useTranslation()
  const { control, setValue, formState } = useFormContext<DocumentChecklistFormValues>()
  const { fields, canAdd, itemCount, addSection, removeSection, moveUp, moveDown } = useChecklistSections(control, setValue)
  const listError = formState.errors.sections?.root?.message ?? formState.errors.sections?.message

  return (
    <div className="flex flex-col gap-3">
      <p className="text-xs text-muted-foreground">{t('documentChecklists.section.help')}</p>
      {fields.length === 0 ? (
        <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
          {t('documentChecklists.section.emptyList')}
        </p>
      ) : (
        <ol className="flex flex-col gap-3">
          {fields.map((field, index) => (
            <SectionRow
              key={field.id}
              index={index}
              count={fields.length}
              itemCount={itemCount(field.key)}
              onMoveUp={() => moveUp(index)}
              onMoveDown={() => moveDown(index)}
              onRemove={() => removeSection(index)}
            />
          ))}
        </ol>
      )}
      {listError ? (
        <p role="alert" className="text-sm text-destructive">
          {listError}
        </p>
      ) : null}
      <div className="flex items-center gap-3">
        <Button type="button" variant="secondary" size="sm" onClick={addSection} disabled={!canAdd}>
          <Plus className="size-3.5" aria-hidden="true" />
          {t('documentChecklists.actions.addSection')}
        </Button>
        {canAdd ? null : (
          <span className="text-xs text-muted-foreground">
            {t('documentChecklists.section.maxReached', { max: CHECKLIST_MAX_SECTIONS })}
          </span>
        )}
      </div>
    </div>
  )
}
