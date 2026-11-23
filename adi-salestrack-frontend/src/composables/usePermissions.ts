import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
  const auth = useAuthStore()

  const isAdmin = computed(() => auth.isAdmin)
  const isSales = computed(() => auth.isSales)
  const isSalesManager = computed(() => auth.isSalesManager)
  const isManagement = computed(() => auth.isManagement)

  const canManageMaster = computed(() => auth.isAdmin)
  const canManageOpportunity = computed(() => auth.isAdmin || auth.isSales || auth.isSalesManager)
  const canManageActivity = computed(() => auth.isAdmin || auth.isSales || auth.isSalesManager)
  const canManageTransaction = computed(() => auth.isAdmin || auth.isSales || auth.isSalesManager)
  const canCreateForecast = computed(() => auth.isAdmin || auth.isSalesManager)
  const canViewForecast = computed(() => auth.isAdmin || auth.isSalesManager || auth.isManagement)
  const canViewRecommendations = computed(() => auth.isAdmin || auth.isSalesManager || auth.isManagement)
  const canViewAnalytics = computed(() => auth.isAdmin || auth.isSales || auth.isSalesManager || auth.isManagement)
  const isReadOnly = computed(() => auth.isManagement)

  return {
    auth,
    isAdmin,
    isSales,
    isSalesManager,
    isManagement,
    canManageMaster,
    canManageOpportunity,
    canManageActivity,
    canManageTransaction,
    canCreateForecast,
    canViewForecast,
    canViewRecommendations,
    canViewAnalytics,
    isReadOnly,
  }
}
