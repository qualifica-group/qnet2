import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { setFirstPassword } from '@/features/auth/api'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { PasswordInput } from '@/features/auth/password-input'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'

const PASSWORD_MIN_LENGTH = 8

interface FirstPasswordValues {
  password: string
  confirmPassword: string
}

/**
 * Welcome window of the first access (spec 0177 rev. 2): recommends replacing
 * the temporary password, never blocks. Dismissal is local state, so it comes
 * back on every sign-in or page reload while `must_set_password` is true.
 */
export function FirstAccessDialog() {
  const { user, impersonator } = useAuth()
  const [dismissed, setDismissed] = useState(false)

  if (!user || !user.must_set_password || impersonator) {
    return null
  }

  return (
    <Dialog open={!dismissed} onOpenChange={(open) => setDismissed(!open)}>
      <DialogContent className="max-w-md">
        <FirstAccessForm name={user.name} onDismiss={() => setDismissed(true)} />
      </DialogContent>
    </Dialog>
  )
}

interface FirstAccessFormProps {
  name: string
  onDismiss: () => void
}

function FirstAccessForm({ name, onDismiss }: FirstAccessFormProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const schema = useMemo(
    () =>
      z
        .object({
          password: z.string().min(PASSWORD_MIN_LENGTH, t('auth.passwordMinLength')),
          confirmPassword: z.string().min(1, t('auth.passwordRequired')),
        })
        .refine((values) => values.password === values.confirmPassword, {
          path: ['confirmPassword'],
          message: t('auth.passwordsDontMatch'),
        }),
    [t],
  )

  const form = useForm<FirstPasswordValues>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', confirmPassword: '' },
  })

  const mutation = useMutation({
    mutationFn: (values: FirstPasswordValues) =>
      setFirstPassword({ password: values.password, password_confirmation: values.confirmPassword }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: authKeys.me })
      toast.success(t('auth.firstAccessSaved'))
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, ['password'])) {
        toast.error(t('auth.genericError'))
      }
    },
  })

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        className="space-y-4"
        noValidate
      >
        <DialogHeader>
          <DialogTitle>{t('auth.welcomeTitle', { name })}</DialogTitle>
          <DialogDescription>{t('auth.welcomeText')}</DialogDescription>
        </DialogHeader>

        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('auth.newPassword')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="confirmPassword"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('auth.confirmPassword')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <DialogFooter className="gap-2 sm:gap-0">
          <Button type="button" variant="secondary" onClick={onDismiss}>
            {t('auth.firstAccessLater')}
          </Button>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? t('auth.firstAccessSaving') : t('auth.firstAccessSave')}
          </Button>
        </DialogFooter>
      </form>
    </Form>
  )
}
