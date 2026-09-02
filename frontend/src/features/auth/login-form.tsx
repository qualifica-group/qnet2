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
import { AuthNotice } from '@/features/auth/auth-notice'
import { PasswordInput } from '@/features/auth/password-input'
import { useAuth } from '@/features/auth/use-auth'

interface LoginValues {
  email: string
  password: string
}

export function LoginForm({ onSuccess }: { onSuccess: () => void }) {
  const { t } = useTranslation()
  const { login } = useAuth()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(
    () =>
      z.object({
        email: z.string().refine(
          (value) => value === '' || z.string().email().safeParse(value).success,
          { message: t('auth.emailInvalid') },
        ),
        password: z.string(),
      }),
    [t],
  )

  const form = useForm<LoginValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = async (values: LoginValues) => {
    setServerError(null)
    try {
      await login(values)
      onSuccess()
    } catch (error) {
      if (axios.isAxiosError(error) && [401, 422].includes(error.response?.status ?? 0)) {
        setServerError(t('auth.invalidCredentials'))
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
              <FormLabel>{t('auth.email')}</FormLabel>
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

        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('auth.password')}</FormLabel>
              <FormControl>
                <PasswordInput autoComplete="current-password" {...field} />
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
          {form.formState.isSubmitting ? t('auth.signingIn') : t('auth.signIn')}
        </Button>
      </form>
    </Form>
  )
}
