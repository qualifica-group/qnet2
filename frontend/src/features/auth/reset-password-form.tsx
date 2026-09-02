import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { resetPassword } from '@/features/auth/api'
import { AuthNotice } from '@/features/auth/auth-notice'
import { PasswordInput } from '@/features/auth/password-input'

interface ResetPasswordFormProps {
  token: string
  email: string
  onSuccess: () => void
}

interface ResetPasswordValues {
  password: string
  confirmPassword: string
}

export function ResetPasswordForm({ token, email, onSuccess }: ResetPasswordFormProps) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z
        .object({
          password: z.string().min(8, t('auth.passwordMinLength')),
          confirmPassword: z.string().min(1, t('auth.passwordRequired')),
        })
        .refine((values) => values.password === values.confirmPassword, {
          path: ['confirmPassword'],
          message: t('auth.passwordsDontMatch'),
        }),
    [t],
  )

  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', confirmPassword: '' },
  })

  const onSubmit = async (values: ResetPasswordValues) => {
    setServerError(null)
    try {
      await resetPassword({
        token,
        email,
        password: values.password,
        password_confirmation: values.confirmPassword,
      })
      onSuccess()
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        const errors = error.response.data?.errors as Record<string, string[]> | undefined
        if (errors?.password?.length) {
          form.setError('password', { message: errors.password[0] })
        }
        // An invalid/expired token is reported by the backend under `email`.
        if (errors?.email?.length) {
          setServerError(t('auth.resetLinkInvalid'))
        }
      } else {
        setServerError(t('auth.genericError'))
      }
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
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

        {serverError && <AuthNotice tone="error">{serverError}</AuthNotice>}

        <Button
          type="submit"
          size="lg"
          className="w-full transition-transform active:translate-y-px"
          disabled={form.formState.isSubmitting}
        >
          {form.formState.isSubmitting ? t('auth.resetting') : t('auth.resetPasswordSubmit')}
        </Button>
      </form>
    </Form>
  )
}
