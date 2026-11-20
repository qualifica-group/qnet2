import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Mail, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import type { WorkflowStatusNotify } from '@/features/workflow-statuses/types'
import { statusNotifyRecipientCount } from '@/features/workflow-statuses/workflow-status-notify'
import { WorkflowStatusNotifyDialog } from '@/features/workflow-statuses/workflow-status-notify-dialog'

interface WorkflowStatusNotifyControlProps {
  rowId: string
  statusName: string
  value: WorkflowStatusNotify | null
  disabled: boolean
  onChange: (notify: WorkflowStatusNotify | null) => void
}

/**
 * The "send an email" switch of a working status (spec 0419), next to the
 * mandatory note: switching it on opens the configuration, switching it off
 * removes it; a configured email shows its subject and recipient count.
 */
export function WorkflowStatusNotifyControl({ rowId, statusName, value, disabled, onChange }: WorkflowStatusNotifyControlProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const switchId = `workflow-status-notify-${rowId}`

  const save = (notify: WorkflowStatusNotify) => {
    onChange(notify)
    setOpen(false)
  }

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-2">
        <Switch
          id={switchId}
          checked={value !== null}
          disabled={disabled}
          onCheckedChange={(checked) => (checked ? setOpen(true) : onChange(null))}
        />
        <Label htmlFor={switchId} className="text-xs font-normal text-muted-foreground">
          {t('workflowStatuses.notify.enabled')}
        </Label>
        {value !== null ? (
          <Button
            type="button"
            variant="ghost"
            size="xs"
            className="ml-auto text-xs"
            disabled={disabled}
            onClick={() => setOpen(true)}
          >
            <Pencil aria-hidden="true" className="size-3.5" />
            {t('workflowStatuses.notify.edit')}
          </Button>
        ) : null}
      </div>
      {value !== null ? (
        <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
          <Mail aria-hidden="true" className="size-3.5 shrink-0" />
          <span className="truncate">
            {t('workflowStatuses.notify.summary', { subject: value.subject, count: statusNotifyRecipientCount(value) })}
          </span>
        </p>
      ) : null}
      <WorkflowStatusNotifyDialog open={open} statusName={statusName} value={value} onOpenChange={setOpen} onSave={save} />
    </div>
  )
}
