<template>
  <div class="container">
    <h1>🚀 Discord to ClickUp Bot</h1>
    <BotStatus />
    <MessageList />
  </div>
</template>

<script setup>
import BotStatus from '@/components/BotStatus.vue'
import MessageList from '@/components/MessageList.vue'
</script>

<style>
body {
  font-family: Arial, sans-serif;
  background: #181818;
  color: white;
  text-align: center;
  padding: 20px;
}
.container {
  max-width: 600px;
  margin: auto;
}
</style>
