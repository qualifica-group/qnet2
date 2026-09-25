import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { FilterViewVisibilityPicker } from '@/features/table/filter-view-visibility-picker'
import { RuleRow } from '@/features/table/custom-filters/rule-row'
import {
  blankRuleRow,
  buildRuleBuilderSchema,
  MAX_RULES_PER_GROUP,
  type RuleBuilderFormValues,
} from '@/features/table/custom-filters/rule-schema'
import { formValuesToRules, rulesToFormValues } from '@/features/table/custom-filters/rule-form-mapping'
import { usableRuleColumns } from '@/features/table/custom-filters/rule-types'
import { useCreateFilterView, useUpdateFilterView } from '@/features/table/use-filter-views'
import type { FilterRules, FilterViewVisibility, TableColumn, TableFilterView } from '@/features/table/types'

const EMPTY_VALUES: RuleBuilderFormValues = { and: [], or: [] }

interface RuleBuilderDialogProps {
  domain: string
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The domain's full column catalog; only the usable ones are offered as rule fields. */
  columns: TableColumn[]
  /** `null` for a brand-new custom filter; an owned view to edit its rules otherwise. */
  editingView: TableFilterView | null
  /** Spec 0158 D-3: gates the "Condivisa" visibility option of "Salva come vista". */
  canPublish: boolean
  /** Activates `rules` as the domain's custom filter — called by both Applica and Salva. */
  onApply: (rules: FilterRules, meta: { viewId?: number; name?: string }) => void
}

interface RuleGroupSectionProps {
  headingLabel: string
  addLabel: string
  emptyLabel: string
  count: number
  onAdd: () => void
  children: ReactNode
}

/** One condition group's chrome (heading, add button, rows or an empty hint). */
function RuleGroupSection({ headingLabel, addLabel, emptyLabel, count, onAdd, children }: RuleGroupSectionProps) {
  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
          {headingLabel}
        </span>
        <Button type="button" variant="outline" size="xs" disabled={count >= MAX_RULES_PER_GROUP} onClick={onAdd}>
          <Plus aria-hidden="true" />
          {addLabel}
        </Button>
      </div>
      {count === 0 ? <p className="text-xs text-muted-foreground">{emptyLabel}</p> : children}
    </div>
  )
}

/**
 * The custom filter rule builder (spec 0158): two condition groups — "Tutte
 * queste condizioni (E)" and "Oppure una di queste (O)" — each a list of
 * field/operator/value rows, capped and validated by `buildRuleBuilderSchema`.
 * "Applica" activates the rules without persisting; "Salva come vista" also
 * creates/updates a `TableFilterView` whose `rules` carries them (`filters`/
 * `advancedFilters` saved empty, per contract).
 */
export function RuleBuilderDialog({
  domain,
  open,
  onOpenChange,
  columns,
  editingView,
  canPublish,
  onApply,
}: RuleBuilderDialogProps) {
  const { t } = useTranslation()
  const createView = useCreateFilterView(domain)
  const updateView = useUpdateFilterView(domain)

  const usableColumns = useMemo(() => usableRuleColumns(columns), [columns])
  const schema = useMemo(() => buildRuleBuilderSchema(usableColumns, t), [usableColumns, t])
  const firstFieldId = usableColumns[0]?.id ?? ''

  const form = useForm<RuleBuilderFormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY_VALUES,
  })
  const andArray = useFieldArray({ control: form.control, name: 'and' })
  const orArray = useFieldArray({ control: form.control, name: 'or' })

  const [saveOpen, setSaveOpen] = useState(false)
  const [name, setName] = useState('')
  const [visibility, setVisibility] = useState<FilterViewVisibility>('private')
  const isSaving = createView.isPending || updateView.isPending

  // Resets the form/save panel to the editing target every time the dialog
  // opens — a plain state sync (react-hooks.md), not a data fetch.
  useEffect(() => {
    if (!open) {
      return
    }
    form.reset(editingView?.rules ? rulesToFormValues(editingView.rules) : { and: [blankRuleRow(firstFieldId)], or: [] })
    setSaveOpen(false)
    setName(editingView?.name ?? '')
    setVisibility(editingView?.visibility ?? 'private')
    // eslint-disable-next-line react-hooks/exhaustive-deps -- resets only when the dialog opens or the editing target changes
  }, [open, editingView])

  const handleFieldChange = (group: 'and' | 'or', index: number) => {
    form.setValue(`${group}.${index}.operator`, '')
    form.setValue(`${group}.${index}.value`, '')
    form.setValue(`${group}.${index}.valueTo`, '')
    form.setValue(`${group}.${index}.values`, [])
  }

  const submitApply = form.handleSubmit((values) => {
    onApply(formValuesToRules(values, usableColumns), { viewId: editingView?.id, name: editingView?.name })
    onOpenChange(false)
  })

  const submitSave = form.handleSubmit(async (values) => {
    if (name.trim().length === 0) {
      return
    }
    const rules = formValuesToRules(values, usableColumns)
    const input = { name: name.trim(), filters: {}, advancedFilters: {}, visibility, rules }
    try {
      const saved = editingView
        ? await updateView.mutateAsync({ id: editingView.id, input })
        : await createView.mutateAsync(input)
      toast.success(t('table.viewSaved'))
      onApply(rules, { viewId: saved.id, name: saved.name })
      onOpenChange(false)
    } catch (error) {
      const isDuplicateName =
        axios.isAxiosError(error) && error.response?.status === 422 && Boolean(error.response.data?.errors?.name)
      const isForbidden = axios.isAxiosError(error) && error.response?.status === 403
      toast.error(
        t(isDuplicateName ? 'table.duplicateViewName' : isForbidden ? 'table.customFilters.publishForbidden' : 'table.viewSaveError'),
      )
    }
  })

  const atLeastOneError = form.formState.errors.and?.message

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="lg" className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('table.customFilters.builderTitle')}</DialogTitle>
          <DialogDescription>{t('table.customFilters.builderDescription')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form className="flex flex-col gap-4">
            <RuleGroupSection
              headingLabel={t('table.customFilters.andHeading')}
              addLabel={t('table.customFilters.addCondition')}
              emptyLabel={t('table.customFilters.groupEmpty')}
              count={andArray.fields.length}
              onAdd={() => andArray.append(blankRuleRow(firstFieldId))}
            >
              {andArray.fields.map((field, index) => (
                <RuleRow
                  key={field.id}
                  control={form.control}
                  group="and"
                  index={index}
                  domain={domain}
                  columns={usableColumns}
                  fieldValue={form.watch(`and.${index}.field`)}
                  operatorValue={form.watch(`and.${index}.operator`)}
                  onFieldChange={() => handleFieldChange('and', index)}
                  onRemove={() => andArray.remove(index)}
                />
              ))}
            </RuleGroupSection>

            <RuleGroupSection
              headingLabel={t('table.customFilters.orHeading')}
              addLabel={t('table.customFilters.addCondition')}
              emptyLabel={t('table.customFilters.groupEmpty')}
              count={orArray.fields.length}
              onAdd={() => orArray.append(blankRuleRow(firstFieldId))}
            >
              {orArray.fields.map((field, index) => (
                <RuleRow
                  key={field.id}
                  control={form.control}
                  group="or"
                  index={index}
                  domain={domain}
                  columns={usableColumns}
                  fieldValue={form.watch(`or.${index}.field`)}
                  operatorValue={form.watch(`or.${index}.operator`)}
                  onFieldChange={() => handleFieldChange('or', index)}
                  onRemove={() => orArray.remove(index)}
                />
              ))}
            </RuleGroupSection>

            {atLeastOneError ? (
              <p role="alert" className="text-xs text-destructive">
                {atLeastOneError}
              </p>
            ) : null}
          </form>
        </Form>

        {saveOpen ? (
          <div className="flex flex-col gap-2 rounded-lg border border-border bg-muted/40 p-3">
            <Input
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder={t('table.viewNamePlaceholder')}
              aria-label={t('table.viewNamePlaceholder')}
              autoComplete="off"
              maxLength={80}
              className="h-8"
            />
            <FilterViewVisibilityPicker value={visibility} onChange={setVisibility} canPublish={canPublish} />
          </div>
        ) : null}

        <DialogFooter>
          {saveOpen ? (
            <Button type="button" variant="outline" onClick={() => setSaveOpen(false)} disabled={isSaving}>
              {t('common.cancel')}
            </Button>
          ) : (
            <Button type="button" variant="outline" onClick={() => setSaveOpen(true)}>
              {t('table.saveView')}
            </Button>
          )}
          {saveOpen ? (
            <Button type="button" onClick={() => void submitSave()} disabled={isSaving || name.trim().length === 0}>
              {t('table.save')}
            </Button>
          ) : (
            <Button type="button" onClick={() => void submitApply()}>
              {t('table.advancedFilters.apply')}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
