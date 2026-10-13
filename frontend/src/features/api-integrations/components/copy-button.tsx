import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Copy } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'

const COPIED_FEEDBACK_MS = 1500

interface CopyButtonProps {
  value: string
  /** Accessible name (and tooltip). */
  label: string
  /** Toast shown after a successful copy. */
  successMessage: string
  /** Visible text: with it the button is a labelled chip, without it an icon button. */
  children?: ReactNode
  className?: string
}

/** Copies `value` to the clipboard with a toast and a short check-mark feedback. */
export function CopyButton({ value, label, successMessage, children, className }: CopyButtonProps) {
  const { t } = useTranslation()
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      toast.success(successMessage)
      window.setTimeout(() => setCopied(false), COPIED_FEEDBACK_MS)
    } catch {
      toast.error(t('apiIntegrations.docs.copyError'))
    }
  }

  const Icon = copied ? Check : Copy

  return (
    <Button
      type="button"
      variant={children ? 'outline' : 'ghost'}
      size={children ? 'xs' : 'icon-xs'}
      aria-label={label}
      title={label}
      onClick={copy}
      className={className}
    >
      <Icon className="size-3.5" aria-hidden />
      {children}
    </Button>
  )
}
