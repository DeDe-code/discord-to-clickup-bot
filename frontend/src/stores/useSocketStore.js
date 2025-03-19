import { defineStore } from 'pinia'
import { ref, onMounted, onUnmounted } from 'vue'
import { io } from 'socket.io-client'

export const useSocketStore = defineStore('socket', () => {
  const messages = ref([])
  const botStatus = ref('Connecting...')
  let socket

  onMounted(() => {
    socket = io('http://localhost:3000') // Connect to backend WebSocket

    socket.on('connect', () => {
      botStatus.value = '🟢 Bot is online'
    })

    socket.on('disconnect', () => {
      botStatus.value = '🔴 Bot is offline'
    })

    socket.on('newMessage', (data) => {
      messages.value.unshift({
        server: data.server,
        channel: data.channel,
        username: data.username,
        content: data.content,
        timestamp: new Date().toLocaleTimeString(),
      })
    })
  })

  onUnmounted(() => {
    if (socket) socket.disconnect()
  })

  return { botStatus, messages }
})
