<template>
  <div class="status-box">
    <h2>Discord Bot Status</h2>
    <p :class="{ online: botStatus.includes('🟢'), offline: botStatus.includes('🔴') }">
      {{ botStatus }}
    </p>
  </div>
</template>

<script setup>
import { useSocketStore } from '@/stores/useSocketStore'
import { storeToRefs } from 'pinia'

const socketStore = useSocketStore()
const { botStatus } = storeToRefs(socketStore) // ✅ Make `botStatus` reactive

console.log('Component Loaded - Initial Bot Status:', botStatus.value) // ✅ Debug Log
</script>

<style scoped>
.status-box {
  text-align: center;
  font-size: 18px;
  padding: 10px;
  background: #333;
  color: white;
  border-radius: 5px;
}
.online {
  color: #0f0;
}
.offline {
  color: #f00;
}
</style>
