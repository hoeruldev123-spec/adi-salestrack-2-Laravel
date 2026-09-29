import { createRouter, createWebHistory } from 'vue-router'
import { installRouterGuards } from './guards'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { title: 'Login', public: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardView.vue'),
          meta: { title: 'Dashboard', roles: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'] },
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/master/UsersView.vue'),
          meta: { title: 'Users', roles: ['ADMIN'], menu: 'master' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/views/master/RolesView.vue'),
          meta: { title: 'Roles', roles: ['ADMIN'], menu: 'master' },
        },
        {
          path: 'companies',
          name: 'companies',
          component: () => import('@/views/master/CompaniesView.vue'),
          meta: { title: 'Companies', roles: ['ADMIN'], menu: 'master' },
        },
        {
          path: 'principals',
          name: 'principals',
          component: () => import('@/views/master/PrincipalsView.vue'),
          meta: { title: 'Principals', roles: ['ADMIN'], menu: 'master' },
        },
        {
          path: 'products',
          name: 'products',
          component: () => import('@/views/master/ProductsView.vue'),
          meta: { title: 'Products', roles: ['ADMIN'], menu: 'master' },
        },
        {
          path: 'opportunities',
          name: 'opportunities',
          component: () => import('@/views/opportunity/OpportunityListView.vue'),
          meta: { title: 'Daftar Opportunity', roles: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'opportunities' },
        },
        {
          path: 'opportunities/new',
          name: 'opportunity-new',
          component: () => import('@/views/opportunity/OpportunityFormView.vue'),
          meta: { title: 'Buat Opportunity', roles: ['ADMIN', 'SALES', 'SALES_MANAGER'], menu: 'opportunities' },
        },
        {
          path: 'opportunities/:id(\\d+)',
          name: 'opportunity-detail',
          component: () => import('@/views/opportunity/OpportunityDetailView.vue'),
          meta: { title: 'Detail Opportunity', roles: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'opportunities' },
        },
        {
          path: 'opportunities/:id(\\d+)/edit',
          name: 'opportunity-edit',
          component: () => import('@/views/opportunity/OpportunityFormView.vue'),
          meta: { title: 'Edit Opportunity', roles: ['ADMIN', 'SALES', 'SALES_MANAGER'], menu: 'opportunities' },
        },
        {
          path: 'transactions',
          name: 'transactions',
          component: () => import('@/views/transaction/TransactionListView.vue'),
          meta: { title: 'Sales Transaction', roles: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'transactions' },
        },
        {
          path: 'transactions/new',
          name: 'transaction-new',
          component: () => import('@/views/transaction/TransactionFormView.vue'),
          meta: { title: 'Buat Transaction', roles: ['ADMIN', 'SALES', 'SALES_MANAGER'], menu: 'transactions' },
        },
        {
          path: 'analytics',
          name: 'analytics',
          component: () => import('@/views/analytics/AnalyticsView.vue'),
          meta: { title: 'Analytics', roles: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'analytics' },
        },
        {
          path: 'forecasts',
          name: 'forecasts',
          component: () => import('@/views/forecast/ForecastListView.vue'),
          meta: { title: 'Forecast', roles: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'forecast' },
        },
        {
          path: 'forecasts/new',
          name: 'forecast-new',
          component: () => import('@/views/forecast/ForecastFormView.vue'),
          meta: { title: 'Buat Forecast Run', roles: ['ADMIN', 'SALES_MANAGER'], menu: 'forecast' },
        },
        {
          path: 'forecasts/:id(\\d+)',
          name: 'forecast-detail',
          component: () => import('@/views/forecast/ForecastDetailView.vue'),
          meta: { title: 'Detail Forecast', roles: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'forecast' },
        },
        {
          path: 'forecasts/:id(\\d+)/scenarios/new',
          name: 'scenario-new',
          component: () => import('@/views/forecast/ScenarioFormView.vue'),
          meta: { title: 'Buat Scenario', roles: ['ADMIN', 'SALES_MANAGER'], menu: 'forecast' },
        },
        {
          path: 'forecasts/:id(\\d+)/evaluation',
          name: 'forecast-evaluation',
          component: () => import('@/views/forecast/ForecastEvaluationView.vue'),
          meta: { title: 'Evaluasi Forecast', roles: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'], menu: 'forecast' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/error/NotFoundView.vue'),
      meta: { public: true },
    },
  ],
})

installRouterGuards(router)

export default router
