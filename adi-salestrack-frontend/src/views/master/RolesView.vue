<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { rolesApi } from '@/api/roles'
import { useFormat } from '@/composables/useFormat'
import Skeleton from '@/components/common/Skeleton.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { Shield } from 'lucide-vue-next'

const { formatDate } = useFormat()
const roles = ref<Awaited<ReturnType<typeof rolesApi.list>>>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    roles.value = await rolesApi.list()
  } catch (e) {
    error.value = (e as Error).message || 'Gagal memuat data role'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="card overflow-hidden">
    <div v-if="loading" class="border-t border-slate-100"><Skeleton :rows="5" :cols="4" /></div>
    <EmptyState v-else-if="roles.length === 0" title="Belum ada role" subtitle="Role akan muncul di sini" />
    <div v-else-if="error" class="p-6 text-sm text-red-600">{{ error }}</div>
    <div v-else class="overflow-x-auto">
      <table class="table-base">
        <thead>
          <tr>
            <th>Role</th>
            <th>Deskripsi</th>
            <th>Dibuat</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in roles" :key="r.id">
            <td>
              <div class="flex items-center gap-2">
                <Shield class="h-4 w-4 text-primary-500" />
                <span class="font-medium">{{ r.name }}</span>
              </div>
            </td>
            <td>{{ r.description || '-' }}</td>
            <td>{{ formatDate((r as Record<string, unknown>).created_at as string) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
