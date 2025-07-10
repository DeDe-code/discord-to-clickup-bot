# Discord to ClickUp Bot - Egyszerűsített Web-alapú Verzió

## Áttekintés

Ez egy egyszerűsített megoldás a Discord-ClickUp bot kezelésére webes felületen keresztül. A bot WebSocket kapcsolatot használ a Discord Gateway-jel, és minden beérkező üzenetet közvetlenül továbbít a ClickUp-nak.

## Főbb jellemzők

- **Webes felület**: A bot állapotának megtekintése és vezérlése webböngészőből
- **WebSocket kapcsolat**: Valós idejű Discord üzenetfigyelés
- **Egyszerű telepítés**: Nincs szükség CLI hozzáférésre
- **Cache-alapú állapot**: A bot állapota cache-ben tárolódik (nincs adatbázis)
- **Háttér feldolgozás**: A WebSocket kapcsolat háttérben fut

## Telepítés

### 1. Fájlok feltöltése

Töltse fel a `backend/laravel-server` mappát a hosting szolgáltatóra.

### 2. Környezeti változók beállítása

Szerkessze a `.env` fájlt:

```env
APP_NAME="Discord to ClickUp Bot"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=http://your-domain.com

# Discord Bot Settings
DISCORD_BOT_TOKEN=your_discord_bot_token_here
DISCORD_WATCHED_CHANNEL_IDS=["channel_id_1","channel_id_2"]

# ClickUp Settings
CLICKUP_CLIENT_ID=your_clickup_client_id
CLICKUP_CLIENT_SECRET=your_clickup_client_secret
CLICKUP_REDIRECT_URI=http://your-domain.com/api/auth/clickup/callback

# API Security
API_SECRET=your_secret_key_here

# Cache Settings
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

### 3. Függőségek telepítése

Ha van composer hozzáférés:

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Jogosultságok beállítása

Győződjön meg róla, hogy a következő mappák írhatók:

- `storage/`
- `bootstrap/cache/`

## Használat

### Webes felület elérése

Nyissa meg a weboldalt a böngészőben: `http://your-domain.com`

### Bot vezérlés

1. **Állapot megtekintése**: A főoldalon látható a WebSocket kapcsolat állapota
2. **Kapcsolódás**: Kattintson a "Connect" gombra a Discord Gateway-hez való csatlakozáshoz
3. **Lekapcsolás**: Kattintson a "Disconnect" gombra a kapcsolat bontásához
4. **Frissítés**: A "Refresh" gombbal frissítheti az állapotot

### Üzenetkezelés

A bot automatikusan:

- Figyeli a beállított Discord csatornákat
- Továbbítja az összes üzenetet (beleértve a bot üzeneteket is) a ClickUp-nak
- Naplózza az eseményeket

## API Végpontok

### WebSocket vezérlés

- `GET /api/websocket/status` - WebSocket kapcsolat állapota
- `POST /api/websocket/connect` - Csatlakozás a Discord Gateway-hez
- `POST /api/websocket/disconnect` - Lekapcsolás a Discord Gateway-ről

### ClickUp hitelesítés

- `GET /api/auth/clickup` - ClickUp OAuth átirányítás
- `GET /api/auth/clickup/callback` - ClickUp OAuth callback
- `GET /api/auth/clickup/status` - ClickUp hitelesítés állapota

## Technikai részletek

### Fájlstruktúra

```
backend/laravel-server/
├── app/
│   ├── Console/Commands/
│   │   └── WebSocketConnect.php          # Háttér WebSocket kapcsolat
│   ├── Http/Controllers/
│   │   └── WebSocketController.php       # Web API vezérlő
│   └── Services/
│       └── DiscordBotService.php         # Discord üzenetfeldolgozás
├── resources/views/
│   └── bot-control.blade.php             # Webes felület
├── routes/
│   ├── web.php                           # Web útvonalak
│   └── api.php                           # API útvonalak
└── .env                                  # Konfigurációs fájl
```

### Cache használata

A bot állapota cache-ben tárolódik:

- `discord_websocket_status` - WebSocket kapcsolat állapota
- TTL: 3600 másodperc (1 óra)

### Háttér folyamatok

A WebSocket kapcsolat háttérben fut:

- Artisan parancs: `php artisan websocket:connect-background`
- Folyamat kezelés: `pkill -f 'websocket:connect-background'`

## Hibaelhárítás

### WebSocket nem csatlakozik

1. Ellenőrizze a Discord bot token érvényességét
2. Ellenőrizze a tűzfal beállításokat
3. Nézze meg a Laravel log fájlokat (`storage/logs/`)

### Üzenetek nem érkeznek meg

1. Ellenőrizze a Discord csatorna ID-kat
2. Ellenőrizze a bot jogosultságokat a Discord szerverén
3. Ellenőrizze a ClickUp API beállításokat

### Webes felület nem elérhető

1. Ellenőrizze a web szerver konfigurációt
2. Ellenőrizze a fájl jogosultságokat
3. Ellenőrizze a Laravel útvonal gyorsítótárat

## Hosting követelmények

- PHP 8.1+
- Composer (telepítéshez)
- Írható `storage/` és `bootstrap/cache/` mappák
- Kimenő HTTPS kapcsolat (Discord API)
- WebSocket támogatás (reaktív PHP könyvtárak)

## Biztonsági megjegyzések

- Az API végpontok védettek (kivéve a WebSocket vezérlés)
- Az API kulcsokat tartsa biztonságban
- A Discord bot token-t ne ossza meg
- Használjon HTTPS-t production környezetben

## Karbantartás

- Rendszeresen ellenőrizze a log fájlokat
- Monitorizálja a bot állapotát
- Frissítse a függőségeket szükség szerint
- Készítsen rendszeres biztonsági mentést

## Támogatás

Ha problémába ütközik:

1. Ellenőrizze a log fájlokat
2. Ellenőrizze a konfigurációt
3. Tesztelje a API végpontokat
4. Ellenőrizze a Discord bot jogosultságokat
