import { Layers } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control, FieldErrors } from 'react-hook-form'
import { ContractProgramCommonValues } from '@/features/contracts/contract-program-common-values'
import { ContractProgramGroupCard } from '@/features/contracts/contract-program-group-card'
import type { ContractProgramFormValues, ContractProgramGroup } from '@/features/contracts/contract-program-schema'
import type { ContractProgrammableLine } from '@/features/contracts/types'

interface ContractProgramGroupsPanelProps {
  control: Control<ContractProgramFormValues>
  errors: FieldErrors<ContractProgramFormValues>
  groups: ContractProgramGroup[]
  lines: ContractProgrammableLine[]
  atLimit: boolean
  collapsedKeys: string[]
  onToggleGroup: (key: string, open: boolean) => void
  onApplyToAll: () => void
  onDuplicate: (index: number) => void
  onRemove: (index: number) => void
  onRemoveLine: (index: number, lineId: number) => void
}

/** Right-hand panel of the "Programma" dialog: common values, then one card per work order to create (spec 0215 D-4). */
export function ContractProgramGroupsPanel({
  control,
  errors,
  groups,
  lines,
  atLimit,
  collapsedKeys,
  onToggleGroup,
  onApplyToAll,
  onDuplicate,
  onRemove,
  onRemoveLine,
}: ContractProgramGroupsPanelProps) {
  const { t } = useTranslation()

  return (
    <section
      aria-label={t('contracts.actions.programDialog.groupsTitle')}
      className="flex min-w-0 flex-col gap-3 rounded-lg border bg-surface p-3"
    >
      <h3 className="text-sm font-semibold">{t('contracts.actions.programDialog.groupsTitle')}</h3>

      <ContractProgramCommonValues control={control} hasGroups={groups.length > 0} onApplyToAll={onApplyToAll} />

      {groups.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed bg-card px-4 py-6 text-center">
          <span className="flex size-8 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <Layers className="size-3.5" aria-hidden="true" />
          </span>
          <p className="text-xs font-medium">{t('contracts.actions.programDialog.groupsEmptyTitle')}</p>
          <p className="max-w-sm text-xs text-muted-foreground">
            {t('contracts.actions.programDialog.groupsEmptyHint')}
          </p>
        </div>
      ) : (
        groups.map((group, index) => (
          <ContractProgramGroupCard
            key={group.key}
            index={index}
            group={group}
            lines={lines}
            control={control}
            open={!collapsedKeys.includes(group.key)}
            onOpenChange={(open) => onToggleGroup(group.key, open)}
            hasError={Boolean(errors.groups?.[index])}
            canDuplicate={!atLimit}
            onDuplicate={() => onDuplicate(index)}
            onRemove={() => onRemove(index)}
            onRemoveLine={(lineId) => onRemoveLine(index, lineId)}
          />
        ))
      )}
    </section>
  )
}
