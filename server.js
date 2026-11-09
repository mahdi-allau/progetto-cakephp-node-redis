// server.js
// WebSocket + JWT + Redis + inoltro a CakePHP
// con idDevice, idMsg, state, content, temperatura, umidità e soglie

const WebSocket = require('ws');
const jwt       = require('jsonwebtoken');
const redis     = require('redis');

const WS_PORT = 8081;
const JWT_KEY = process.env.JWT_KEY || 'CAMBIA_QUESTA_CHIAVE';

// Server WebSocket
const wss = new WebSocket.Server({ port: WS_PORT, host: '0.0.0.0' });

// Redis
const redisClient = redis.createClient({ url: 'redis://localhost:6379' });
const redisSub    = redis.createClient({ url: 'redis://localhost:6379' });

redisClient.connect()
  .then(() => console.log('Connesso a Redis (client)!'))
  .catch(err => console.error('Errore Redis client:', err));

redisSub.connect()
  .then(async () => {
    console.log('Connesso a Redis (subscriber)!');

    try {
      await redisSub.subscribe('canale_messaggi', (message) => {
        console.log('Messaggio da Redis (canale_messaggi):', message);
      });

      await redisSub.subscribe('canale_config', (message) => {
        console.log('Messaggio da Redis (canale_config):', message);

        let parsed;

        try {
          parsed = JSON.parse(message);
        } catch (err) {
          console.error('JSON config non valido:', err);
          return;
        }

        let sentCount = 0;

        wss.clients.forEach((client) => {
          if (client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify(parsed));
            sentCount++;
          }
        });

        console.log('Config inviata ai client WebSocket:', sentCount);
      });

      console.log('Iscritto ai canali Redis');
    } catch (err) {
      console.error('Errore subscribe Redis:', err);
    }
  })
  .catch(err => console.error('Errore Redis subscriber:', err));

console.log(`Server WebSocket in ascolto su ws://localhost:${WS_PORT}`);


// Helper per mandare JSON
function sendJson(ws, obj) {
  try {
    ws.send(JSON.stringify(obj));
  } catch (err) {
    console.error('Errore sendJson:', err);
  }
}


wss.on('connection', (ws) => {
  console.log('Nuovo client WebSocket connesso');

  ws.isAuthed = false;
  ws.user = null;

  sendJson(ws, {
    type: 'info',
    message: 'Connesso. Invia prima il token JWT con { "type": "auth", "token": "..." }'
  });

  ws.on('message', async (data) => {
    const raw = data.toString();

    console.log('Messaggio ricevuto dal client:', raw);

    let msg;

    try {
      msg = JSON.parse(raw);
    } catch (err) {
      sendJson(ws, {
        type: 'error',
        code: 'invalid_json',
        message: 'Messaggio non è JSON valido'
      });
      return;
    }

    // Se NON è autenticato, accettiamo solo auth
    if (!ws.isAuthed) {
      if (msg.type !== 'auth' || !msg.token) {
        sendJson(ws, {
          type: 'error',
          code: 'authentication_required',
          message: 'Manda prima { "type": "auth", "token": "..." }'
        });
        return;
      }

      try {
        const decoded = jwt.verify(msg.token, JWT_KEY);

        ws.isAuthed = true;
        ws.user = decoded;

        sendJson(ws, {
          type: 'auth_ok',
          user: decoded.sub,
          token_data: decoded
        });

        console.log('Connessione autenticata:', decoded);
      } catch (err) {
        console.error('JWT non valido:', err.message);

        sendJson(ws, {
          type: 'error',
          code: 'invalid_token',
          message: 'Token JWT non valido o scaduto'
        });

        ws.close();
      }

      return;
    }

    // Client autenticato
    if (msg.type === 'message') {
      const idDevice = msg.idDevice || msg.device || 'UNKNOWN';

      const idMsg = (typeof msg.idMsg === 'number')
        ? msg.idMsg
        : null;

      const stateIn = (typeof msg.state === 'number')
        ? msg.state
        : 0;

      const content = msg.content && typeof msg.content === 'object'
        ? msg.content
        : {};

      // Supporta sia vecchi nomi sia nuovi nomi
      const temp =
        content.temperature ??
        content.temp ??
        msg.temperature ??
        msg.temp ??
        null;

      const humid =
        content.humidity ??
        content.humid ??
        msg.humidity ??
        msg.humid ??
        null;

      const ts =
        content.ts ??
        msg.ts ??
        null;

      const device_name =
        content.device_name ??
        msg.device_name ??
        idDevice;

      const username =
        ws.user?.sub ||
        msg.username ||
        content.username ||
        'ws-client';

      const payload = {
        idDevice,
        idMsg,
        state: stateIn,
        content,
        username,

        // campi flat per CakePHP
        device_name,
        temp,
        humid,
        ts
      };

      // ACK verso ESP32
      sendJson(ws, {
        type: 'ack',
        idMsg: idMsg !== null ? idMsg : -1,
        state: 1,
        message: 'Ho ricevuto il messaggio',
        payload
      });

      // Inoltro a CakePHP
      try {
        const res = await fetch('http://localhost:8765/api/ws-message', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload),
        });

        const text = await res.text();

        console.log('---- Risposta CakePHP ----');
        console.log('Status:', res.status);
        console.log(text);
        console.log('--------------------------');
      } catch (err) {
        console.error('Errore chiamando CakePHP:', err);
      }

    } else {
      sendJson(ws, {
        type: 'error',
        code: 'unsupported_type',
        message: 'Tipo di messaggio non supportato'
      });
    }
  });
});