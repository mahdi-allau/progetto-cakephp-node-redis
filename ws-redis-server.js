// ws-redis-server.js

const WebSocket = require('ws');
const jwt = require('jsonwebtoken');
const { createClient } = require('redis');

// ⚠️ Deve essere la stessa chiave usata da CakePHP per il JWT
const JWT_SECRET = 'CAMBIA_QUESTO_SEGRETO';

const redisPub = createClient();   // per pubblicare (Node → CakePHP)
const redisSub = createClient();   // per ascoltare (CakePHP → Node)

redisPub.connect();
redisSub.connect();

// WebSocket server (porta a scelta, es. 8080)
const wss = new WebSocket.Server({ port: 8080 });

// Mappa per sapere quale ws è associato a quale device
// es: deviceSockets.get(1) → socket dell’ESP32 con device_id = 1
const deviceSockets = new Map();

// Funzione per iscriversi ai comandi da Redis
async function subscribeCommands() {
  // In molte librerie Redis non esiste il wildcard diretto, quindi
  // puoi fare subscribe su canali singoli oppure su pattern.
//  Esempio semplice: uno per device. Per ora facciamo un canale generico:
  await redisSub.subscribe('device:cmd', (message) => {
    const data = JSON.parse(message);
    const ws = deviceSockets.get(data.device_id);
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(data));
    }
  });

  console.log('Node.js: in ascolto dei comandi da Redis su channel device:cmd');
}

subscribeCommands().catch(console.error);

wss.on('connection', (ws, req) => {
  console.log('Nuova connessione WebSocket');

  // Leggo il token JWT dalla query string (es: ws://host:8080/?token=AAA)
  const url = new URL(req.url, 'http://dummy');
  const token = url.searchParams.get('token');

  if (token) {
    // Connessione dal frontend web (non dall’ESP32)
    try {
      const payload = jwt.verify(token, JWT_SECRET);
      ws.isAuthenticated = true;
      ws.userId = payload.sub;
      console.log('Client web autenticato, userId:', payload.sub);
    } catch (e) {
      console.log('JWT non valido, chiudo la connessione');
      ws.close();
      return;
    }
  } else {
    // Se non c’è token presumiamo che sia un device (ESP32)
    ws.isDevice = true;
  }

  ws.on('message', async (msgBuffer) => {
    let msg;
    try {
      msg = JSON.parse(msgBuffer.toString());
    } catch (e) {
      console.log('Messaggio non in JSON valido:', msgBuffer.toString());
      return;
    }

    // 1) Registrazione device: l’ESP32 invia il suo device_id
    if (msg.type === 'hello_device' && ws.isDevice) {
      const deviceId = msg.device_id;
      deviceSockets.set(deviceId, ws);
      console.log('Device registrato, id:', deviceId);
      return;
    }

    // 2) Misure: l’ESP32 manda dati (temp, stato, ecc.)
    if (msg.type === 'measurement' && ws.isDevice) {
      console.log('Misura da device', msg.device_id, msg.data);

      // Mandiamo i dati a CakePHP tramite Redis
      await redisPub.publish(
        'device:data',
        JSON.stringify(msg) // { type: 'measurement', device_id, data: {...} }
      );

      return;
    }

    // 3) Messaggi dal client web
    if (ws.isAuthenticated) {
      // Esempio: il client web vuole inviare un comando a un device
      if (msg.type === 'command_to_device') {
        // msg = { type: 'command_to_device', device_id, cmd, params: {...} }

        await redisPub.publish(
          'device:cmd',
          JSON.stringify({
            device_id: msg.device_id,
            cmd: msg.cmd,
            params: msg.params || {}
          })
        );

        console.log('Comando pubblicato su Redis per device', msg.device_id);
      }
    }
  });

  ws.on('close', () => {
    // Pulizia: se era un device, lo togliamo dalla mappa
    if (ws.isDevice) {
      for (const [id, socket] of deviceSockets.entries()) {
        if (socket === ws) {
          deviceSockets.delete(id);
          console.log('Device disconnesso, id:', id);
          break;
        }
      }
    }
  });
});

console.log('WebSocket server con Redis e JWT avviato sulla porta 8080');
