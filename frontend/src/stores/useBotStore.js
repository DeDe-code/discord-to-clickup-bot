import { defineStore } from 'pinia'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

export const useBotStore = defineStore('botData', () => {
  // State
  const botStatus = ref('🔄 Checking...')
  const clickupAuth = ref('🔄 Checking...')
  const messages = ref([])
  const isConnected = ref(false)
  const error = ref(null)

  // Polling intervals
  let statusInterval = null
  let messageCheckInterval = null
  let lastMessageId = 0

  // API configuration
  const apiConfig = {
    headers: {
      'X-API-Key': import.meta.env.VITE_API_SECRET,
    },
  }

  // Computed
  const isOnline = computed(() => botStatus.value.includes('🟢'))
  const hasMessages = computed(() => messages.value.length > 0)

  // Check for new messages (lightweight check)
  async function checkForNewMessages() {
    try {
      const response = await axios.get('/api/check-new-messages', apiConfig)

      if (response.data.hasNewMessages) {
        console.log('📨 New messages available, fetching...')
        await fetchMessages()
      }
    } catch (err) {
      console.error('❌ Failed to check for new messages:', err)
    }
  }

  // Fetch bot status
  async function fetchBotStatus() {
    try {
      const response = await axios.get('/api/status', apiConfig)

      botStatus.value = response.data.status
      clickupAuth.value = response.data.clickupAuth || '� Checking...'
      isConnected.value = true
      error.value = null

      console.log('📡 Bot Status:', response.data.status)

      // If status indicates new messages, fetch them
      if (response.data.hasNewMessages) {
        await fetchMessages()
      }
    } catch (err) {
      console.error('❌ Failed to fetch bot status:', err)
      isConnected.value = false
      error.value = err.message
      botStatus.value = '❌ Connection failed'
    }
  }

  // Fetch messages
  async function fetchMessages() {
    try {
      const response = await axios.get('/api/messages', {
        ...apiConfig,
        params: {
          limit: 50,
          after: 0, // Always get latest messages, let frontend filter duplicates
        },
      })

      if (Array.isArray(response.data) && response.data.length > 0) {
        const newMessages = response.data.map((msg) => ({
          id: msg.id,
          username: msg.username,
          content: msg.content,
          timestamp: new Date(msg.timestamp).toLocaleTimeString(),
          fullTimestamp: msg.timestamp,
        }))

        // Sort by timestamp (newest first)
        newMessages.sort((a, b) => new Date(b.fullTimestamp) - new Date(a.fullTimestamp))

        // Only add truly new messages
        const existingIds = new Set(messages.value.map((m) => m.id))
        const actuallyNewMessages = newMessages.filter((msg) => !existingIds.has(msg.id))

        if (actuallyNewMessages.length > 0) {
          // Add new messages and sort the entire array
          messages.value = [...actuallyNewMessages, ...messages.value].sort(
            (a, b) => new Date(b.fullTimestamp) - new Date(a.fullTimestamp),
          )

          // Update last message ID to the most recent
          if (messages.value.length > 0) {
            lastMessageId = Math.max(...messages.value.map((m) => parseInt(m.id)))
          }

          console.log(
            `📩 Added ${actuallyNewMessages.length} new messages (Total: ${messages.value.length})`,
          )
          console.log(
            '📨 New messages:',
            actuallyNewMessages.map((m) => `${m.username}: ${m.content}`),
          )
        } else if (messages.value.length === 0) {
          // First load - add all messages
          messages.value = newMessages
          if (newMessages.length > 0) {
            lastMessageId = Math.max(...newMessages.map((m) => parseInt(m.id)))
          }
          console.log(`📩 Loaded ${newMessages.length} messages initially`)
        }
      }
    } catch (err) {
      console.error('❌ Failed to fetch messages:', err)
    }
  }

  // Smart polling - adjust frequency based on bot status
  function startPolling() {
    // Initial fetch
    fetchBotStatus()
    fetchMessages()

    // Status polling - less frequent (every 15 seconds)
    statusInterval = setInterval(fetchBotStatus, 15000)

    // Message check polling - more frequent when bot is online
    const startMessagePolling = () => {
      if (messageCheckInterval) clearInterval(messageCheckInterval)

      const frequency = isOnline.value ? 5000 : 15000 // 5s when online, 15s when offline
      messageCheckInterval = setInterval(() => {
        console.log('🔄 Polling for messages...')
        fetchMessages()
      }, frequency) // Direct polling for now

      console.log(
        `🔄 Message polling frequency: ${frequency / 1000}s (Bot ${isOnline.value ? 'online' : 'offline'})`,
      )
    }

    startMessagePolling()

    // Adjust message polling frequency when status changes
    let lastOnlineStatus = isOnline.value
    const _statusWatcher = setInterval(() => {
      if (lastOnlineStatus !== isOnline.value) {
        lastOnlineStatus = isOnline.value
        startMessagePolling()
      }
    }, 2000)
  }

  function stopPolling() {
    if (statusInterval) {
      clearInterval(statusInterval)
      statusInterval = null
    }
    if (messageCheckInterval) {
      clearInterval(messageCheckInterval)
      messageCheckInterval = null
    }
  }

  onMounted(() => {
    console.log('✅ Starting intelligent polling for status and messages')
    startPolling()
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
    // State
    botStatus,
    clickupAuth,
    messages,
    isConnected,
    error,
    // Computed
    isOnline,
    hasMessages,
    // Actions
    fetchBotStatus,
    fetchMessages,
    checkForNewMessages,
    startPolling,
    stopPolling,
  }
})
