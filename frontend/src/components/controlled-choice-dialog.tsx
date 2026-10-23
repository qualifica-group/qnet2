import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'

export interface ControlledChoiceOption<TValue extends string> {
  value: TValue
  label: string
  description?: string
}

interface ControlledChoiceDialogProps<TValue extends string> {
  open: boolean
  title: string
  description?: string
  /** Counts or context shown above the options (e.g. how many quotes the choice touches). */
  summary?: ReactNode
  /** Accessible name of the radiogroup. */
  choiceLabel: string
  options: ControlledChoiceOption<TValue>[]
  confirmLabel: string
  /** Shown on the confirm button while `pending`. */
  pendingLabel?: string
  pending?: boolean
  /** `true`: Cancel button, X and Esc close the dialog. `false`: the user must choose. */
  dismissible: boolean
  onConfirm: (value: TValue) => void
  onOpenChange: (open: boolean) => void
}

/**
 * Question with a forced answer: explicit options, none preselected, and a
 * confirm button that stays disabled until one is chosen. Typing a word is
 * never required. The selection resets each time the dialog opens.
 */
export function ControlledChoiceDialog<TValue extends string>(props: ControlledChoiceDialogProps<TValue>) {
  const { open, dismissible, onOpenChange } = props
  return (
    <Dialog open={open} onOpenChange={(next) => (dismissible ? onOpenChange(next) : undefined)}>
      <DialogContent
        size="sm"
        showCloseButton={dismissible}
        onEscapeKeyDown={(event) => !dismissible && event.preventDefault()}
        onInteractOutside={(event) => !dismissible && event.preventDefault()}
      >
        {/* Mounted only while open, so the choice starts empty every time. */}
        {open ? <ChoiceBody {...props} /> : null}
      </DialogContent>
    </Dialog>
  )
}

function ChoiceBody<TValue extends string>({
  title,
  description,
  summary,
  choiceLabel,
  options,
  confirmLabel,
  pendingLabel,
  pending = false,
  dismissible,
  onConfirm,
  onOpenChange,
}: ControlledChoiceDialogProps<TValue>) {
  const { t } = useTranslation()
  const [choice, setChoice] = useState<TValue | null>(null)
  const groupId = useId()

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
        <DialogDescription className={description ? undefined : 'sr-only'}>{description ?? title}</DialogDescription>
      </DialogHeader>
      {summary ? <div className="rounded-md bg-surface p-3 text-sm">{summary}</div> : null}
      <RadioGroup
        aria-label={choiceLabel}
        value={choice ?? ''}
        onValueChange={(value) => setChoice(value as TValue)}
        disabled={pending}
      >
        {options.map((option) => {
          const itemId = `${groupId}-${option.value}`
          return (
            <div key={option.value} className="flex items-start gap-2.5 rounded-md border border-border bg-card p-2.5">
              <RadioGroupItem id={itemId} value={option.value} className="mt-0.5" />
              <Label htmlFor={itemId} className="flex flex-col items-start gap-0.5 text-sm font-medium">
                {option.label}
                {option.description ? (
                  <span className="text-xs font-normal text-muted-foreground">{option.description}</span>
                ) : null}
              </Label>
            </div>
          )
        })}
      </RadioGroup>
      <DialogFooter>
        {dismissible ? (
          <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={pending}>
            {t('common.cancel')}
          </Button>
        ) : null}
        <Button type="button" size="sm" disabled={choice === null || pending} onClick={() => choice && onConfirm(choice)}>
          {pending && pendingLabel ? pendingLabel : confirmLabel}
        </Button>
      </DialogFooter>
    </>
  )
}
