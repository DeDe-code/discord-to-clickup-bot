import { defineStore } from 'pinia'
import { ref, onMounted, onUnmounted } from 'vue'
import { io } from 'socket.io-client'
import axios from 'axios'

export const useSocketStore = defineStore('socket', () => {
  const botStatus = ref('Connecting...') // ✅ Make reactive
  const messages = ref([]) // ✅ Ensure messages array is initialized
  let socket

  async function fetchBotStatus() {
    try {
      const response = await axios.get('/api/status', {
        params: {
          api_key: import.meta.env.VITE_API_SECRET, // 🔐 Match your .env API_SECRET
        },
      })

      console.log('📡 API Response - Bot Status:', response.data.status)
      botStatus.value = response.data.status
    } catch (error) {
      console.error('❌ Failed to fetch bot status', error)
      botStatus.value = '🔴 Bot is offline'
    }
  }

  onMounted(() => {
    socket = io() // ✅ Connect to WebSocket

    socket.on('connect', () => {
      console.log('✅ WebSocket connected')
    })

    socket.on('botStatus', (data) => {
      console.log('📢 WebSocket Update - Bot Status:', data.status)
      botStatus.value = data.status // ✅ Updates in real-time
    })

    socket.on('newMessage', (data) => {
      console.log('📩 New Message Received:', data)
      messages.value.unshift({
        username: data.username,
        content: data.content,
        timestamp: new Date().toLocaleTimeString(),
      })
    })

    socket.on('disconnect', () => {
      console.log('❌ WebSocket disconnected')
      botStatus.value = '🔴 Bot is offline'
    })

    fetchBotStatus() // ✅ Fetch initial status in case WebSockets fail
  })

  onUnmounted(() => {
    if (socket) socket.disconnect()
  })

  return { botStatus, messages } // ✅ Return messages
})
