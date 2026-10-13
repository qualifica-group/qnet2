import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ContractProgramSharedFields } from '@/features/contracts/contract-program-shared-fields'
import type { ContractProgramFormValues } from '@/features/contracts/contract-program-schema'

interface ContractProgramCommonValuesProps {
  control: Control<ContractProgramFormValues>
  hasGroups: boolean
  onApplyToAll: () => void
}

/** "Valori comuni" (spec 0215 D-3/D-7): defaults of the NEXT groups, collapsible, with the explicit "apply to all" override. */
export function ContractProgramCommonValues({ control, hasGroups, onApplyToAll }: ContractProgramCommonValuesProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(true)

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="rounded-lg border bg-card">
      <CollapsibleTrigger className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
        <ChevronDown className={`size-3.5 transition-transform ${open ? '' : '-rotate-90'}`} aria-hidden="true" />
        {t('contracts.actions.programDialog.commonTitle')}
      </CollapsibleTrigger>
      <CollapsibleContent className="flex flex-col gap-3 border-t p-3">
        <p className="text-xs text-muted-foreground">{t('contracts.actions.programDialog.commonHelp')}</p>
        <ContractProgramSharedFields control={control} prefix="common" required={false} />
        <Button
          type="button"
          variant="outline"
          size="xs"
          className="self-start bg-card"
          disabled={!hasGroups}
          onClick={onApplyToAll}
        >
          {t('contracts.actions.programDialog.applyToAll')}
        </Button>
      </CollapsibleContent>
    </Collapsible>
  )
}
