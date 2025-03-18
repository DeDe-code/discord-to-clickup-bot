/** @format */

import dotenv from "dotenv";
import { Client, GatewayIntentBits } from "discord.js";
import axios from "axios";
import express from "express";
import cors from "cors";

dotenv.config();
console.log(
  "✅ Loaded DISCORD_BOT_TOKEN:",
  process.env.DISCORD_BOT_TOKEN || "❌ NOT FOUND"
);

const app = express();
app.use(cors());
app.use(express.json());

const DISCORD_BOT_TOKEN = process.env.DISCORD_BOT_TOKEN;
const CLICKUP_API_TOKEN = process.env.CLICKUP_API_TOKEN;
const CLICKUP_TEAM_ID = process.env.CLICKUP_TEAM_ID; // Replace with your ClickUp Team ID
const CLICKUP_WEBHOOK_URL = process.env.CLICKUP_WEBHOOK_URL; // Optional if using a webhook

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent,
    GatewayIntentBits.DirectMessages, // Required for DM tracking
  ],
});

// ✅ Bot Ready
client.once("ready", () => {
  console.log(`🤖 Bot is online as ${client.user.tag}`);
});

// ✅ Handle New Messages
client.on("messageCreate", async (message) => {
  if (message.author.bot) return; // Ignore bot messages

  let notificationType = null;

  // ✅ Check if the message is a DM (Direct Message)
  if (!message.guild) {
    notificationType = "Direct Message";
  }

  // ✅ Check if the message mentions a user
  if (message.mentions.users.size > 0) {
    notificationType = "Mention";
  }

  // ❌ Do nothing if it's a regular message (not a DM or mention)
  if (!notificationType) return;

  console.log(
    `📩 New ${notificationType}: ${message.author.username}: ${message.content}`
  );

  // ✅ Send ClickUp Notification
  await sendClickUpNotification(message, notificationType);
});

// ✅ Function to Send Notification to ClickUp
async function sendClickUpNotification(message, type) {
  try {
    const notificationText =
      type === "Direct Message"
        ? `📩 **${message.author.username}** sent a DM: ${message.content}`
        : `📢 **${message.author.username}** mentioned a user: ${message.content}`;

    const response = await axios.post(
      "https://api.clickup.com/api/v2/notification",
      {
        team_id: CLICKUP_TEAM_ID,
        notification: {
          type: "custom",
          title: `New ${type} in Discord`,
          body: notificationText,
          url: `https://discord.com/channels/${message.guild?.id}/${message.channel.id}`,
        },
      },
      {
        headers: {
          Authorization: CLICKUP_API_TOKEN,
          "Content-Type": "application/json",
        },
      }
    );

    console.log(`✅ ClickUp notification sent for ${type}:`, response.data);
  } catch (error) {
    console.error(
      `❌ Error sending ClickUp notification (${type}):`,
      error.response?.data || error.message
    );
  }
}

// ✅ API for Vue to fetch bot status
app.get("/status", (req, res) => {
  res.json({ status: "Bot is running", bot: client.user?.tag });
});

// ✅ Start the Bot
client.login(DISCORD_BOT_TOKEN);

// ✅ Start Express Server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Backend server running on port ${PORT}`);
});
