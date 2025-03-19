/** @format */

import dotenv from "dotenv";
import { Client, GatewayIntentBits } from "discord.js";
import express from "express";
import cors from "cors";
import { createServer } from "http";
import { Server } from "socket.io";

dotenv.config();

const app = express();
app.use(cors());
app.use(express.json());

// ✅ Move API route here, before WebSockets
let clientReady = false; // Ensure this is declared before it's used
console.log(clientReady);

app.get("/api/status", (req, res) => {
  console.log("📡 API Status Endpoint Hit");
  console.log(res);

  res.json({ status: clientReady ? "🟢 Bot is online" : "🔴 Bot is offline" });
});

// ✅ Create HTTP Server and Attach WebSockets
const server = createServer(app);
const io = new Server(server, {
  cors: {
    origin: "http://localhost:5173",
    methods: ["GET", "POST"],
  },
});

// ✅ WebSocket Setup
io.on("connection", (socket) => {
  console.log("🔗 New client connected to WebSocket");

  // Send initial bot status
  socket.emit("botStatus", {
    status: clientReady ? "🟢 Bot is online" : "🔴 Bot is offline",
  });

  socket.on("disconnect", () => {
    console.log("❌ Client disconnected");
  });
});

// ✅ Discord Bot Setup
const DISCORD_BOT_TOKEN = process.env.DISCORD_BOT_TOKEN;

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent,
    GatewayIntentBits.DirectMessages,
  ],
});

client.once("ready", () => {
  console.log(`🤖 Bot is online as ${client.user.tag}`);
  clientReady = true;
  io.emit("botStatus", { status: "🟢 Bot is online" });
});

client.on("disconnect", () => {
  console.log("❌ Bot disconnected!");
  clientReady = false;
  io.emit("botStatus", { status: "🔴 Bot is offline" });
});

// ✅ Start the Bot
client.login(DISCORD_BOT_TOKEN);

// ✅ Start Express & WebSocket Server
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`🚀 Backend server running on http://localhost:${PORT}`);
});
