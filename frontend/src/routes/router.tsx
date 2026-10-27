import { createBrowserRouter, Navigate } from 'react-router-dom'
import { lazyRoute } from '@/routes/lazy-route'
import { ProtectedRoute } from '@/routes/protected-route'
import { APP_CHILD_ROUTES } from '@/routes/app-child-routes'
import { AppLayout } from '@/layouts/app-layout'
import {
  UnprefixedRouteGate,
  WorkspaceHomeRedirect,
  WorkspaceRouteGate,
} from '@/features/workspaces/components/workspace-route-gates'

const LoginPage = lazyRoute(() => import('@/pages/login-page'))
const ForgotPasswordPage = lazyRoute(() => import('@/pages/forgot-password-page'))
const ResetPasswordPage = lazyRoute(() => import('@/pages/reset-password-page'))
const SetPasswordPage = lazyRoute(() => import('@/pages/set-password-page'))
const NotFoundPage = lazyRoute(() => import('@/pages/not-found-page'))

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/reset-password',
    element: <ResetPasswordPage />,
  },
  {
    path: '/set-password',
    element: <SetPasswordPage />,
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          // Full view: a URL without the app prefix is sent into the remembered
          // app unless the user may stay on the full menu (spec 0317 D-7).
          {
            element: <UnprefixedRouteGate />,
            children: [
              { index: true, element: <Navigate to="/dashboard" replace /> },
              ...APP_CHILD_ROUTES,
            ],
          },
          // The same pages, scoped to one app (spec 0317 D-7).
          {
            path: 'app/:workspaceSlug',
            element: <WorkspaceRouteGate />,
            children: [
              { index: true, element: <WorkspaceHomeRedirect /> },
              ...APP_CHILD_ROUTES,
            ],
          },
        ],
      },
    ],
  },
  {
    path: '*',
    element: <NotFoundPage />,
  },
])
