import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { changePassword } from '@/features/auth/api'
import { AuthNotice } from '@/features/auth/auth-notice'
import { PasswordInput } from '@/features/auth/password-input'
import { applyServerValidationErrors } from '@/features/auth/form-errors'

interface PasswordValues {
  current_password: string
  password: string
  confirmPassword: string
}

export function PasswordForm() {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z
        .object({
          current_password: z.string().min(1, t('settings.currentPasswordRequired')),
          password: z.string().min(8, t('settings.passwordMinLength')),
          confirmPassword: z.string().min(1, t('settings.confirmPasswordRequired')),
        })
        .refine((values) => values.password === values.confirmPassword, {
          path: ['confirmPassword'],
          message: t('settings.passwordsDontMatch'),
        }),
    [t],
  )

  const form = useForm<PasswordValues>({
    resolver: zodResolver(schema),
    defaultValues: { current_password: '', password: '', confirmPassword: '' },
  })

  const onSubmit = async (values: PasswordValues) => {
    setServerError(null)
    try {
      await changePassword({
        current_password: values.current_password,
        password: values.password,
        password_confirmation: values.confirmPassword,
      })
      form.reset()
      toast.success(t('settings.passwordChanged'))
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['current_password', 'password'])) {
        setServerError(t('settings.genericError'))
      }
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <FormField
          control={form.control}
          name="current_password"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('settings.currentPassword')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete="current-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('settings.newPassword')}</FormLabel>
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
              <FormLabel required>{t('settings.confirmPassword')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        {serverError && <AuthNotice tone="error">{serverError}</AuthNotice>}

        <Button type="submit" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting
            ? t('settings.changingPassword')
            : t('settings.changePassword')}
        </Button>
      </form>
    </Form>
  )
}
