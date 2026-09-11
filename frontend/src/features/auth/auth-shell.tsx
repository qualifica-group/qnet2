import type { CSSProperties, ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { TopLoadingBar } from '@/components/top-loading-bar'
import { env } from '@/config/env'
import '@/features/auth/auth-shell.css'

/** Position of an element in the shared entrance cascade (see auth-shell.css). */
function step(index: number): CSSProperties {
  return { '--auth-step': index } as CSSProperties
}

interface AuthShellProps {
  title: string
  description: string
  children: ReactNode
  /** Secondary navigation under the form (forgot password, back to sign in). */
  footer?: ReactNode
}

/**
 * Two-plane shell shared by the public authentication screens: the brand panel
 * on one side, the form on the other. It mirrors the application's own chrome
 * (navy rail against the page surface), so signing in already looks like the
 * product rather than like a detached gate.
 */
export function AuthShell({ title, description, children, footer }: AuthShellProps) {
  // The row template is load-bearing: without it the grid stretches both rows
  // to half the viewport each and the mobile brand band eats the screen.
  return (
    <div className="grid min-h-svh grid-rows-[auto_1fr] lg:grid-cols-[1.15fr_1fr] lg:grid-rows-1">
      <TopLoadingBar />
      <BrandPanel />

      <main className="flex items-center justify-center bg-background px-6 py-12 sm:px-10">
        <div className="w-full max-w-sm">
          <header className="auth-enter mb-7" style={step(3)}>
            <h1 className="text-3xl font-semibold tracking-tight text-foreground">{title}</h1>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{description}</p>
          </header>

          <div className="auth-enter" style={step(4)}>
            {children}
          </div>

          {footer && (
            <div className="auth-enter mt-6 text-sm" style={step(5)}>
              {footer}
            </div>
          )}
        </div>
      </main>
    </div>
  )
}

/**
 * Brand half. It carries `--sidebar`, the one surface token that stays navy in
 * both themes, so the panel keeps the brand regardless of the user's theme.
 * On narrow viewports it collapses to a compact band above the form.
 */
function BrandPanel() {
  const { t } = useTranslation()

  return (
    <aside className="auth-brand-panel flex flex-col justify-between gap-16 bg-sidebar px-6 py-6 text-sidebar-foreground lg:px-12 lg:py-12">
      <div className="auth-enter flex items-center gap-3" style={step(0)}>
        <img
          src="/brands/logo_white.svg"
          alt=""
          aria-hidden
          className="size-8 shrink-0 object-contain lg:size-9"
        />
        <span className="text-lg font-semibold tracking-tight lg:text-xl">{env.appName}</span>
      </div>

      {/* Hidden rather than reflowed on mobile: the band there is chrome, and a
          claim squeezed above the form would push the fields below the fold. */}
      <div className="hidden lg:block">
        <p
          className="auth-enter max-w-[16ch] text-4xl font-semibold tracking-tight text-sidebar-foreground xl:text-5xl"
          style={{ ...step(1), lineHeight: 1.1 }}
        >
          {t('auth.brandClaim')}
        </p>
        <p
          className="auth-enter mt-5 max-w-[44ch] text-sm leading-relaxed text-sidebar-foreground/85"
          style={step(2)}
        >
          {t('auth.brandSupport')}
        </p>
      </div>
    </aside>
  )
}
