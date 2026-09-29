<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  LayoutDashboard,
  Users,
  Shield,
  Building2,
  Factory,
  Package,
  Target,
  Activity as ActivityIcon,
  Receipt,
  BarChart3,
  TrendingUp,
  LogOut,
  Menu,
  X,
  ChevronDown,
} from 'lucide-vue-next'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const mobileOpen = ref(false)

const masterOpen = ref(false)

const menus = computed(() => {
  const items: Array<{ label: string; key: string; to?: string; icon: unknown; children?: Array<{ label: string; to: string; icon: unknown }> }> = [
    { label: 'Dashboard', key: 'dashboard', to: '/', icon: LayoutDashboard },
    {
      label: 'Master Data',
      key: 'master',
      icon: Users,
      children: [
        { label: 'Users', to: '/users', icon: Users },
        { label: 'Roles', to: '/roles', icon: Shield },
        { label: 'Companies', to: '/companies', icon: Building2 },
        { label: 'Principals', to: '/principals', icon: Factory },
        { label: 'Products', to: '/products', icon: Package },
      ],
    },
    { label: 'Opportunity', key: 'opportunities', to: '/opportunities', icon: Target },
    { label: 'Activity', key: 'activities', to: '/opportunities', icon: ActivityIcon },
    { label: 'Transaction', key: 'transactions', to: '/transactions', icon: Receipt },
    { label: 'Analytics', key: 'analytics', to: '/analytics', icon: BarChart3 },
    {
      label: 'Forecast',
      key: 'forecast',
      to: '/forecasts',
      icon: TrendingUp,
    },
  ]
  return items.filter((m) => auth.canAccess(m.key))
})

watch(
  () => route.fullPath,
  () => {
    mobileOpen.value = false
    if (route.path.startsWith('/users') || route.path.startsWith('/roles') || route.path.startsWith('/companies') || route.path.startsWith('/principals') || route.path.startsWith('/products')) {
      masterOpen.value = true
    }
  },
  { immediate: true },
)

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const displayRole = computed(() => {
  const map: Record<string, string> = {
    ADMIN: 'Administrator',
    SALES: 'Sales',
    SALES_MANAGER: 'Sales Manager',
    MANAGEMENT: 'Management',
  }
  return map[auth.roleName] ?? auth.roleName
})
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 transition-transform lg:translate-x-0"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-16 items-center justify-between border-b border-slate-800 px-5">
        <div class="flex items-center gap-2">
          <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-sm font-bold text-white">AS</div>
          <span class="text-base font-semibold text-white">ADI SalesTrack</span>
        </div>
        <button class="text-slate-400 hover:text-white lg:hidden" @click="mobileOpen = false">
          <X class="h-5 w-5" />
        </button>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <template v-for="menu in menus" :key="menu.key">
          <div v-if="menu.children" class="mb-1">
            <button
              class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
              @click="masterOpen = !masterOpen"
            >
              <span class="flex items-center gap-3">
                <component :is="menu.icon" class="h-4.5 w-4.5" />
                {{ menu.label }}
              </span>
              <ChevronDown class="h-4 w-4 transition-transform" :class="masterOpen ? 'rotate-180' : ''" />
            </button>
            <div v-show="masterOpen" class="mt-1 space-y-1 pl-4">
              <RouterLink
                v-for="child in menu.children"
                :key="child.to"
                :to="child.to"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-slate-800 hover:text-white"
                active-class="bg-slate-800 text-white"
              >
                <component :is="child.icon" class="h-4 w-4" />
                {{ child.label }}
              </RouterLink>
            </div>
          </div>
          <RouterLink
            v-else
            :to="menu.to!"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
            active-class="bg-primary-600 text-white"
            exact-active-class="bg-primary-600 text-white"
          >
            <component :is="menu.icon" class="h-4.5 w-4.5" />
            {{ menu.label }}
          </RouterLink>
        </template>
      </nav>

      <div class="border-t border-slate-800 p-4">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">
            {{ auth.user?.name?.charAt(0) || 'U' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-white">{{ auth.user?.name }}</p>
            <p class="text-xs text-slate-400">{{ displayRole }}</p>
          </div>
          <button class="text-slate-400 hover:text-white" title="Logout" @click="handleLogout">
            <LogOut class="h-4.5 w-4.5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Overlay -->
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"
      @click="mobileOpen = false"
    />

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
        <button class="text-slate-600 lg:hidden" @click="mobileOpen = true">
          <Menu class="h-5 w-5" />
        </button>
        <h1 class="text-lg font-semibold text-slate-900">{{ (route.meta.title as string) ?? '' }}</h1>
      </header>
      <main class="flex-1 p-4 lg:p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
