import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { forgotPassword } from '@/features/auth/api'
import { AuthNotice } from '@/features/auth/auth-notice'

interface ForgotPasswordValues {
  email: string
}

export function ForgotPasswordForm({ onSuccess }: { onSuccess: () => void }) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () => z.object({ email: z.string().min(1, t('auth.emailRequired')).email(t('auth.emailInvalid')) }),
    [t],
  )

  const form = useForm<ForgotPasswordValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '' },
  })

  const onSubmit = async (values: ForgotPasswordValues) => {
    setServerError(null)
    try {
      await forgotPassword(values)
      onSuccess()
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 429) {
        setServerError(t('auth.tooManyRequests'))
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
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('auth.email')}</FormLabel>
              <FormControl>
                <Input
                  type="email"
                  autoComplete="email"
                  placeholder="name@example.com"
                  {...field}
                />
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
          {form.formState.isSubmitting ? t('auth.sending') : t('auth.sendResetLink')}
        </Button>
      </form>
    </Form>
  )
}
