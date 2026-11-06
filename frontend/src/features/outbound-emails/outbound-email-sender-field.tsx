import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { UserAvatar } from '@/components/user-avatar'
import type { ComposeContext } from '@/features/outbound-emails/types'
import type { ComposerFormValues } from '@/features/outbound-emails/outbound-email-schema'

interface OutboundEmailSenderFieldProps {
  control: Control<ComposerFormValues>
  composeContext: ComposeContext | undefined
  disabled: boolean
  rowClassName: string
  labelClassName: string
  hintClassName: string
}

/**
 * The "Da" line of the composer envelope (spec 0361): the sender's name plus
 * a picker over `sender_options` when the user has more than one address,
 * the plain login email otherwise.
 */
export function OutboundEmailSenderField({
  control,
  composeContext,
  disabled,
  rowClassName,
  labelClassName,
  hintClassName,
}: OutboundEmailSenderFieldProps) {
  const { t } = useTranslation()
  const options = composeContext?.sender_options ?? []

  return (
    <FormField
      control={control}
      name="from_address"
      render={({ field }) => (
        <FormItem className={rowClassName}>
          <FormLabel className={labelClassName}>{t('outboundEmails.composer.from')}</FormLabel>
          <div className="flex min-h-9 min-w-0 items-center gap-2 text-sm">
            {composeContext ? (
              <>
                <UserAvatar name={composeContext.sender.name} size="sm" />
                <span className="shrink-0 truncate font-medium">{composeContext.sender.name}</span>
                {options.length > 1 ? (
                  <Select value={field.value ?? composeContext.sender.email} onValueChange={field.onChange} disabled={disabled}>
                    <FormControl>
                      <SelectTrigger
                        size="sm"
                        aria-label={t('outboundEmails.composer.from')}
                        className="h-7 min-w-0 border-0 bg-transparent px-1.5 text-muted-foreground shadow-none"
                      >
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {options.map((address) => (
                        <SelectItem key={address} value={address}>
                          {address}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                ) : (
                  <span className="truncate text-muted-foreground">{composeContext.sender.email}</span>
                )}
              </>
            ) : null}
          </div>
          <FormMessage className={hintClassName} />
        </FormItem>
      )}
    />
  )
}
