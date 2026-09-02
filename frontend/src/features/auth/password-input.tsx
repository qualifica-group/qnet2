import { useState, type ComponentProps } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'

/**
 * Password field with a reveal toggle. Every prop is forwarded to the inner
 * `Input`, so the `id`/`aria-describedby`/`aria-invalid` that `FormControl`
 * injects still land on the control itself and not on the wrapper.
 */
export function PasswordInput({ className, ...props }: ComponentProps<'input'>) {
  const { t } = useTranslation()
  const [revealed, setRevealed] = useState(false)
  const Icon = revealed ? EyeOff : Eye

  return (
    <div className="relative">
      <Input {...props} type={revealed ? 'text' : 'password'} className={cn('pr-9', className)} />
      <button
        type="button"
        onClick={() => setRevealed((current) => !current)}
        aria-label={t(revealed ? 'auth.hidePassword' : 'auth.showPassword')}
        aria-pressed={revealed}
        className="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-r-md text-muted-foreground outline-none transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
      >
        <Icon className="size-4" aria-hidden />
      </button>
    </div>
  )
}
