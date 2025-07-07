<template>
  <div class="message-list">
    <h2>🔔 Recent Notifications</h2>
    <ul v-if="messages.length > 0">
      <li v-for="(msg, index) in messages" :key="index">
        <strong>[{{ msg.timestamp }}] {{ msg.username }}</strong
        >: {{ msg.content }}
      </li>
    </ul>
    <p v-else>No notifications yet...</p>
  </div>
</template>

<script setup>
import { useBotStore } from '@/stores/useBotStore'
import { storeToRefs } from 'pinia'

const botStore = useBotStore()
const { messages } = storeToRefs(botStore) // ✅ Ensure reactivity
</script>

<style scoped>
.message-list {
  background: #222;
  color: white;
  padding: 10px;
  border-radius: 5px;
  margin-top: 10px;
}
ul {
  list-style-type: none;
  padding: 0;
}
li {
  padding: 5px 0;
}
</style>
