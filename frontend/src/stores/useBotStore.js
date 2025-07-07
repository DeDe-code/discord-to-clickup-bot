import { defineStore } from 'pinia'
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

export const useSocketStore = defineStore('botData', () => {
  const botStatus = ref('Connecting...') // ✅ Make reactive
  const messages = ref([]) // ✅ Ensure messages array is initialized
  let pollingInterval
  let lastMessageId = 0

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

  async function fetchMessages() {
    try {
      const response = await axios.get('/api/messages', {
        params: {
          api_key: import.meta.env.VITE_API_SECRET,
          limit: 50,
          after: lastMessageId
        },
      })

      if (response.data.messages && response.data.messages.length > 0) {
        const newMessages = response.data.messages.map(msg => ({
          id: msg.id,
          username: msg.username,
          content: msg.content,
          timestamp: new Date(msg.created_at).toLocaleTimeString(),
        }))
        
        // Add new messages to the beginning of the array
        messages.value.unshift(...newMessages.reverse())
        
        // Update last message ID
        lastMessageId = Math.max(...newMessages.map(m => m.id))
        
        console.log('📩 New Messages Received:', newMessages.length)
      }
    } catch (error) {
      console.error('❌ Failed to fetch messages', error)
    }
  }

  function startPolling() {
    // Poll for status and messages every 5 seconds
    pollingInterval = setInterval(async () => {
      await fetchBotStatus()
      await fetchMessages()
    }, 5000)
  }

  onMounted(() => {
    console.log('✅ Starting HTTP polling for status and messages')
    
    // Initial fetch
    fetchBotStatus()
    fetchMessages()
    
    // Start polling
    startPolling()
  })

  onUnmounted(() => {
    if (pollingInterval) {
      clearInterval(pollingInterval)
    }
  })

  return { botStatus, messages } // ✅ Return messages
})
