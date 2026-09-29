import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function installRouterGuards(router: Router) {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()
    const isPublic = to.meta.public === true

    if (isPublic) {
      if (to.name === 'login' && auth.isAuthenticated) {
        return { name: 'dashboard' }
      }
      return true
    }

    if (!auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (!auth.user) {
      await auth.fetchMe()
    }

    const roles = (to.meta.roles as string[] | undefined) ?? []
    if (roles.length > 0 && !auth.hasRole(roles)) {
      return { name: 'dashboard' }
    }

    return true
  })
}
