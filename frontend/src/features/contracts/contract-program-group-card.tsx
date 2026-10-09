import { AlertCircle, ChevronDown, Copy, Trash2, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  automaticTitlePreview,
  automaticTitleProducts,
  groupDisplayTitle,
} from '@/features/contracts/contract-program-groups'
import { ContractProgramSharedFields } from '@/features/contracts/contract-program-shared-fields'
import type { ContractProgramFormValues, ContractProgramGroup } from '@/features/contracts/contract-program-schema'
import type { ContractProgrammableLine } from '@/features/contracts/types'

interface ContractProgramGroupCardProps {
  index: number
  group: ContractProgramGroup
  lines: ContractProgrammableLine[]
  control: Control<ContractProgramFormValues>
  open: boolean
  onOpenChange: (open: boolean) => void
  hasError: boolean
  canDuplicate: boolean
  onDuplicate: () => void
  onRemove: () => void
  onRemoveLine: (lineId: number) => void
}

/**
 * One work order to create (spec 0215 D-4): a collapsible card whose header
 * reads "G1 · title or automatic preview · N lines" and flags errors even
 * while collapsed; the body edits the group's own values and lists its lines.
 */
export function ContractProgramGroupCard({
  index,
  group,
  lines,
  control,
  open,
  onOpenChange,
  hasError,
  canDuplicate,
  onDuplicate,
  onRemove,
  onRemoveLine,
}: ContractProgramGroupCardProps) {
  const { t } = useTranslation()
  const name = t('contracts.actions.programDialog.groupName', { number: index + 1 })
  const products = automaticTitleProducts(group, lines)
  const groupLines = group.quote_line_ids.flatMap((id) => lines.find((line) => line.id === id) ?? [])

  return (
    <Collapsible open={open} onOpenChange={onOpenChange} asChild>
      <section aria-label={name} className="rounded-lg border bg-card">
        <div className="flex items-center gap-1 p-1">
          <CollapsibleTrigger
            aria-label={t('contracts.actions.programDialog.groupToggle', { name })}
            className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1 text-left text-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <ChevronDown className={`size-3.5 shrink-0 transition-transform ${open ? '' : '-rotate-90'}`} aria-hidden="true" />
            <Badge variant="secondary">{t('contracts.actions.programDialog.groupShort', { number: index + 1 })}</Badge>
            <span className="min-w-0 flex-1 truncate font-medium">{groupDisplayTitle(group, lines)}</span>
            <span className="shrink-0 text-muted-foreground">
              {t('contracts.actions.programDialog.groupSummary', { count: group.quote_line_ids.length })}
            </span>
            {hasError ? (
              <AlertCircle
                className="size-3.5 shrink-0 text-destructive"
                role="img"
                aria-label={t('contracts.actions.programDialog.groupHasErrors')}
              />
            ) : null}
          </CollapsibleTrigger>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            disabled={!canDuplicate}
            aria-label={t('contracts.actions.programDialog.groupDuplicate')}
            title={t('contracts.actions.programDialog.groupDuplicate')}
            onClick={onDuplicate}
          >
            <Copy />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('contracts.actions.programDialog.groupRemove')}
            title={t('contracts.actions.programDialog.groupRemove')}
            onClick={onRemove}
          >
            <Trash2 />
          </Button>
        </div>

        <CollapsibleContent className="flex flex-col gap-3 border-t p-3">
          <FormField
            control={control}
            name={`groups.${index}.title`}
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('workOrders.form.title')}</FormLabel>
                <FormControl>
                  <Input
                    autoComplete="off"
                    placeholder={t('contracts.actions.programDialog.titleAutomatic', {
                      title: automaticTitlePreview(products),
                    })}
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <ContractProgramSharedFields control={control} prefix={`groups.${index}`} required />

          <FormField
            control={control}
            name={`groups.${index}.quote_line_ids`}
            render={() => (
              <FormItem>
                <FormLabel required>{t('contracts.actions.programDialog.groupLinesTitle')}</FormLabel>
                {groupLines.length === 0 ? (
                  <p className="text-xs text-muted-foreground">{t('contracts.actions.programDialog.groupNoLines')}</p>
                ) : (
                  <ul className="min-w-0 divide-y rounded-md border text-xs">
                    {groupLines.map((line) => (
                      <li key={line.id} className="flex items-center gap-2 px-2 py-1">
                        <span className="min-w-0 flex-1 truncate">{line.product?.name ?? '—'}</span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label={t('contracts.actions.programDialog.removeLine', {
                            name: line.product?.name ?? line.id,
                          })}
                          onClick={() => onRemoveLine(line.id)}
                        >
                          <X />
                        </Button>
                      </li>
                    ))}
                  </ul>
                )}
                <FormMessage />
              </FormItem>
            )}
          />
        </CollapsibleContent>
      </section>
    </Collapsible>
  )
}
