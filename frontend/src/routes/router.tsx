/* eslint-disable react-refresh/only-export-components -- route config module, not a component file */
import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from '@/routes/protected-route'
import { AppLayout } from '@/layouts/app-layout'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'
import { buildModuleRoutes } from '@/features/modules/module-routes'

const LoginPage = lazy(() => import('@/pages/login-page'))
const ForgotPasswordPage = lazy(() => import('@/pages/forgot-password-page'))
const ResetPasswordPage = lazy(() => import('@/pages/reset-password-page'))
const DashboardPage = lazy(() => import('@/pages/dashboard-page'))
const UsersPage = lazy(() => import('@/pages/users-page'))
const RolesPage = lazy(() => import('@/pages/roles-page'))
const CompaniesPage = lazy(() => import('@/pages/companies-page'))
const CompanySitesPage = lazy(() => import('@/pages/company-sites-page'))
const BusinessFunctionsPage = lazy(() => import('@/pages/business-functions-page'))
const ReferentsPage = lazy(() => import('@/pages/referents-page'))
const ReferentDetailPage = lazy(() => import('@/pages/referent-detail-page'))
const ReferentFormPage = lazy(() => import('@/pages/referent-form-page'))
const RegistriesPage = lazy(() => import('@/pages/registries-page'))
const RegistryDetailPage = lazy(() => import('@/pages/registry-detail-page'))
const RegistryFormPage = lazy(() => import('@/pages/registry-form-page'))
const ReferentTypesPage = lazy(() => import('@/pages/referent-types-page'))
const OperationalSitesPage = lazy(() => import('@/pages/operational-sites-page'))
const AttributesPage = lazy(() => import('@/pages/attributes-page'))
const CustomFieldsPage = lazy(() => import('@/pages/custom-fields-page'))
const ProductCategoriesPage = lazy(() => import('@/pages/product-categories-page'))
const SectorsPage = lazy(() => import('@/pages/sectors-page'))
const ProductsPage = lazy(() => import('@/pages/products-page'))
const ProductDetailPage = lazy(() => import('@/pages/product-detail-page'))
const ProductFormPage = lazy(() => import('@/pages/product-form-page'))
const SourcesPage = lazy(() => import('@/pages/sources-page'))
const VatRatesPage = lazy(() => import('@/pages/vat-rates-page'))
const TagsPage = lazy(() => import('@/pages/tags-page'))
const PipelineStatusesPage = lazy(() => import('@/pages/pipeline-statuses-page'))
const ProjectsPage = lazy(() => import('@/pages/projects-page'))
const CampaignsPage = lazy(() => import('@/pages/campaigns-page'))
const LeadsPage = lazy(() => import('@/pages/leads-page'))
const OpportunitiesPage = lazy(() => import('@/pages/opportunities-page'))
const OpportunityStatusesPage = lazy(() => import('@/pages/opportunity-statuses-page'))
const OpportunityWorkflowsPage = lazy(() => import('@/pages/opportunity-workflows-page'))
const RequestManagementPage = lazy(() => import('@/pages/request-management-page'))
const RewardTypesPage = lazy(() => import('@/pages/reward-types-page'))
const RequestManagementDetailPage = lazy(() => import('@/pages/request-management-detail-page'))
const LeadImportPage = lazy(() => import('@/pages/lead-import-page'))
const LeadImportHistoryPage = lazy(() => import('@/pages/lead-import-history-page'))
const LeadImportDetailPage = lazy(() => import('@/pages/lead-import-detail-page'))
const MigrationsPage = lazy(() => import('@/features/migrations/migrations-page'))
const SettingsPage = lazy(() => import('@/pages/settings-page'))
const NotFoundPage = lazy(() => import('@/pages/not-found-page'))

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
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <Navigate to="/dashboard" replace /> },
          {
            path: 'dashboard',
            element: <DashboardPage />,
          },
          {
            path: 'users',
            element: <UsersPage />,
          },
          {
            path: 'roles',
            element: <RolesPage />,
          },
          {
            path: 'companies',
            element: <CompaniesPage />,
          },
          {
            path: 'company-sites',
            element: <CompanySitesPage />,
          },
          {
            path: 'business-functions',
            element: <BusinessFunctionsPage />,
          },
          {
            path: 'referents',
            element: <ReferentsPage />,
          },
          {
            path: 'referents/new',
            element: <ReferentFormPage />,
          },
          {
            path: 'referents/:id',
            element: <ReferentDetailPage />,
          },
          {
            path: 'referents/:id/edit',
            element: <ReferentFormPage />,
          },
          {
            path: 'registries',
            element: <RegistriesPage />,
          },
          {
            path: 'registries/new',
            element: <RegistryFormPage />,
          },
          {
            path: 'registries/:id',
            element: <RegistryDetailPage />,
          },
          {
            path: 'registries/:id/edit',
            element: <RegistryFormPage />,
          },
          {
            path: 'referent-types',
            element: <ReferentTypesPage />,
          },
          {
            path: 'operational-sites',
            element: <OperationalSitesPage />,
          },
          {
            path: 'attributes',
            element: <AttributesPage />,
          },
          {
            path: 'custom-fields',
            element: <CustomFieldsPage />,
          },
          {
            path: 'product-categories',
            element: <ProductCategoriesPage />,
          },
          {
            path: 'sectors',
            element: <SectorsPage />,
          },
          {
            path: 'products',
            element: <ProductsPage />,
          },
          {
            path: 'products/new',
            element: <ProductFormPage />,
          },
          {
            path: 'products/:id',
            element: <ProductDetailPage />,
          },
          {
            path: 'products/:id/edit',
            element: <ProductFormPage />,
          },
          {
            path: 'sources',
            element: <SourcesPage />,
          },
          {
            path: 'vat-rates',
            element: <VatRatesPage />,
          },
          {
            path: 'tags',
            element: <TagsPage />,
          },
          {
            path: 'pipeline-statuses',
            element: <PipelineStatusesPage />,
          },
          {
            path: 'projects',
            element: <ProjectsPage />,
          },
          {
            path: 'campaigns',
            element: <CampaignsPage />,
          },
          {
            path: 'leads',
            element: <LeadsPage />,
          },
          {
            path: 'opportunities',
            element: <OpportunitiesPage />,
          },
          {
            path: 'opportunity-statuses',
            element: <OpportunityStatusesPage />,
          },
          {
            path: 'opportunity-workflows',
            element: <OpportunityWorkflowsPage />,
          },
          {
            path: 'reward-types',
            element: <RewardTypesPage />,
          },
          {
            path: 'request-management',
            element: <RequestManagementPage />,
          },
          // `request-management` no longer sets `generateRoutes: false` (spec
          // 0057 D-6: the module now has a real `/new` create form), so
          // `buildModuleRoutes()` below ALSO generates a generic `:id` route
          // for this domain. This manual declaration is kept and stays FIRST
          // in the array on purpose: react-router ranks equal-specificity
          // static routes by declaration order, so this bespoke page (no
          // `bg-card` frame around the panel's own background, no dead-end
          // "Edit" button — see its own comment) always wins the match and the
          // generic `ModuleDetailPage` entry for the same path is never
          // reached. `:id/edit`/`:id/duplicate`/`new` are NOT declared here:
          // those three are genuinely new and come from the generated set.
          {
            path: 'request-management/:id',
            element: <RequestManagementDetailPage />,
          },
          // Deep-link routes (`new`/`:id`/`:id/edit`) of every registered
          // module — projects/campaigns/leads/opportunities in Wave 0 — are
          // generated from the single module registry (spec 0042, AC-012/
          // AC-022), not declared by hand here.
          ...buildModuleRoutes(),
          {
            path: 'imports',
            element: <LeadImportHistoryPage />,
          },
          {
            path: 'imports/new',
            element: <LeadImportPage />,
          },
          {
            path: 'imports/:runId',
            element: <LeadImportDetailPage />,
          },
          {
            element: <MigrationRouteGuard />,
            children: [
              {
                path: 'migrations',
                element: <MigrationsPage />,
              },
            ],
          },
          {
            path: 'settings',
            element: <SettingsPage />,
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
