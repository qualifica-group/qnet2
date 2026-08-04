import { Suspense } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Toaster } from '@/components/ui/sonner'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ThemeProvider } from '@/components/theme-provider'
import { AuthProvider } from '@/features/auth/auth-provider'
import { UiScaleProvider } from '@/features/appearance/ui-scale-provider'
import { DateDisplayProvider } from '@/features/appearance/date-display-provider'
import { UserDetailSheetProvider } from '@/features/users/user-detail-sheet'
import { FieldChangeRequestDialogProvider } from '@/features/field-change-requests/field-change-request-dialog'
import { ConfigGate } from '@/features/config/config-gate'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { queryClient } from '@/app/query-client'
import { router } from '@/routes/router'

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <ConfigGate>
          <AuthProvider>
            <UiScaleProvider>
              <TooltipProvider>
                <ConfirmDialogProvider>
                  <UserDetailSheetProvider>
                    <FieldChangeRequestDialogProvider>
                      <Suspense fallback={<FullScreenLoader />}>
                        <DateDisplayProvider>
                          <RouterProvider router={router} />
                        </DateDisplayProvider>
                      </Suspense>
                    </FieldChangeRequestDialogProvider>
                  </UserDetailSheetProvider>
                </ConfirmDialogProvider>
                <Toaster />
              </TooltipProvider>
            </UiScaleProvider>
          </AuthProvider>
        </ConfigGate>
      </ThemeProvider>
    </QueryClientProvider>
  )
}

export default App
