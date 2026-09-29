<script setup lang="ts">
import { computed, isVNode } from 'vue'
import Skeleton from './Skeleton.vue'
import EmptyState from './EmptyState.vue'
import { Pencil, Trash2, Eye, Inbox } from 'lucide-vue-next'
import type { VNode } from 'vue'

export interface ColumnDef {
  key: string
  label: string
  align?: 'left' | 'right' | 'center'
  width?: string
  sortable?: boolean
  render?: (row: Record<string, unknown>) => unknown
}

const props = withDefaults(
  defineProps<{
    columns: ColumnDef[]
    rows: unknown[]
    loading?: boolean
    emptyTitle?: string
    emptySubtitle?: string
    actionLabel?: string
    showView?: boolean
    showEdit?: boolean
    showDelete?: boolean
    rowKey?: string
  }>(),
  {
    loading: false,
    emptyTitle: 'Belum ada data',
    emptySubtitle: 'Mulai dengan menambahkan data baru',
    actionLabel: '',
    showView: false,
    showEdit: true,
    showDelete: true,
    rowKey: 'id',
  },
)

const emit = defineEmits<{
  view: [row: Record<string, unknown>]
  edit: [row: Record<string, unknown>]
  delete: [row: Record<string, unknown>]
  action: []
  'update:sort': [key: string]
}>()

const hasActions = computed(() => props.showView || props.showEdit || props.showDelete)

function cellValue(row: Record<string, unknown>, col: ColumnDef): unknown {
  if (col.render) return col.render(row)
  return row[col.key]
}

function isVNodeLike(v: unknown): v is VNode {
  return isVNode(v)
}
</script>

<template>
  <div>
    <div v-if="loading" class="border-t border-slate-100">
      <Skeleton :rows="5" :cols="columns.length" />
    </div>

    <EmptyState
      v-else-if="rows.length === 0"
      :title="emptyTitle"
      :subtitle="emptySubtitle"
      :action-label="actionLabel"
      @action="emit('action')"
    >
      <slot name="empty-icon"><Inbox class="h-6 w-6" /></slot>
    </EmptyState>

    <div v-else class="overflow-x-auto">
      <table class="table-base">
        <thead>
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :class="{
                'text-right': col.align === 'right',
                'text-center': col.align === 'center',
              }"
              :style="col.width ? { width: col.width } : {}"
            >
              {{ col.label }}
            </th>
            <th v-if="hasActions" class="w-24 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="String((row as Record<string, unknown>)[rowKey])">
            <td
              v-for="col in columns"
              :key="col.key"
              :class="{
                'text-right': col.align === 'right',
                'text-center': col.align === 'center',
              }"
            >
              <template v-if="isVNodeLike(cellValue(row as Record<string, unknown>, col))">
                <component :is="cellValue(row as Record<string, unknown>, col)" />
              </template>
              <template v-else>{{ cellValue(row as Record<string, unknown>, col) }}</template>
            </td>
            <td v-if="hasActions" class="text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  v-if="showView"
                  class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-primary-600"
                  title="Lihat"
                  @click="emit('view', row as Record<string, unknown>)"
                >
                  <Eye class="h-4 w-4" />
                </button>
                <button
                  v-if="showEdit"
                  class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-blue-600"
                  title="Edit"
                  @click="emit('edit', row as Record<string, unknown>)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  v-if="showDelete"
                  class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-red-600"
                  title="Hapus"
                  @click="emit('delete', row as Record<string, unknown>)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
