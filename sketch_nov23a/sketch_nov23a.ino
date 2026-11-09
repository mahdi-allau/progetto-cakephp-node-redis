#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebSocketsClient.h>
#include <FS.h>
#include <LittleFS.h>
#include <WebServer.h>

// ====== CONFIGURAZIONE DI DEFAULT (solo come suggerimento nella pagina AP) ======
const char* DEFAULT_WIFI_SSID     = "iPhone";
const char* DEFAULT_WIFI_PASSWORD = "INSERISCI_PASSWORD_WIFI";

// IP del PC dove girano CakePHP + Node
const char* DEFAULT_SERVER_IP     = "172.20.10.2";

// CakePHP (HTTP)
const int   DEFAULT_SERVER_HTTP_PORT = 8765;
const char* LOGIN_PATH               = "/auth/login";

// Node WebSocket
const int   DEFAULT_SERVER_WS_PORT   = 8081;
const char* WS_PATH                  = "/";

// Credenziali per il login JWT (come in AuthController di CakePHP)
const char* LOGIN_USER    = "mahdi";
const char* LOGIN_PASS    = "INSERISCI_PASSWORD";
// ======================================================================


// --------- CONFIG (LittleFS) ---------
struct Config {
  String wifiSsid;
  String wifiPass;
  String serverIp;
  uint16_t serverHttpPort;
  uint16_t serverWsPort;
  String deviceId;   // es. "ME01"
};

Config config;
bool   inConfigAP = false;       // true quando siamo in modalità Access Point di configurazione
WebServer configServer(80);      // web server per la pagina di configurazione


// Stato generale
bool   loginFatto = false;
String jwtToken   = "";

// Stato WebSocket
WebSocketsClient wsClient;
bool   wsAutenticato = false;

// Configurazione ricevuta dal server (esempio)
String cfgNomeDispositivo = "esp32-default";
int    cfgIntervalMs      = 10000;   // intervallo invio dati (ms)

// Timer per invio dati periodici
unsigned long lastDataSend = 0;

// PRIMO FOGLIO: contatore ID messaggi
unsigned long lastMsgId = 0;

// ------------------- PROTOTIPI -------------------
void faiLoginJwt();
void setupWebSocket();
void inviaDatiPeriodici();
void onWsEvent(WStype_t type, uint8_t * payload, size_t length);

// LittleFS / AP
bool loadConfig();
bool saveConfig();
bool connectWiFiFromConfig();
void startConfigAP();

// ------------------- CALLBACK WEBSOCKET -------------------
void onWsEvent(WStype_t type, uint8_t * payload, size_t length) {
  switch (type) {
    case WStype_CONNECTED: {
      Serial.println("[WS] ✅ Connesso al server WebSocket!");
      wsAutenticato = false; // finché il server non conferma

      if (jwtToken.length() > 0) {
        // mando il messaggio di autenticazione
        DynamicJsonDocument doc(256);
        doc["type"]  = "auth";
        doc["token"] = jwtToken;

        String msg;
        serializeJson(doc, msg);

        Serial.print("[WS] Invia messaggio di auth: ");
        Serial.println(msg);
        wsClient.sendTXT(msg);
      } else {
        Serial.println("[WS] ⚠ Nessun JWT disponibile, non posso autenticarmi");
      }
    } break;

    case WStype_DISCONNECTED:
      Serial.println("[WS] ❌ Disconnesso dal server WebSocket");
      wsAutenticato = false;
      break;

    case WStype_TEXT: {
      // Converto il payload in String
      String txt((char*)payload, length);
      txt.trim();

      Serial.print("[WS] Messaggio dal server: ");
      Serial.println(txt);

      // Accettiamo SOLO JSON
      if (!txt.startsWith("{")) {
        Serial.println("[WS] Messaggio non JSON, ignorato.");
        return;
      }

      // Proviamo a parsare come JSON
      StaticJsonDocument<512> doc;
      DeserializationError err = deserializeJson(doc, txt);
      if (err) {
        Serial.print("[WS] Errore parse JSON: ");
        Serial.println(err.c_str());
        return;
      }

      const char* typeStr = doc["type"];
      if (!typeStr) {
        Serial.println("[WS] JSON senza campo 'type', lo ignoro.");
        return;
      }

      // --- Risposta di autenticazione ---
      if (strcmp(typeStr, "auth_ok") == 0) {
        wsAutenticato = true;
        Serial.println("[WS] ✅ Autenticazione OK sul WebSocket!");

      } else if (strcmp(typeStr, "auth_error") == 0) {
        wsAutenticato = false;
        Serial.println("[WS] ❌ Errore di autenticazione sul WebSocket!");

      // --- Messaggio di configurazione (dal server) ---
      } else if (strcmp(typeStr, "config") == 0) {
        const char* nomeDev = doc["device_name"];
        int interval        = doc["interval_ms"] | cfgIntervalMs;

        if (nomeDev) {
          cfgNomeDispositivo = String(nomeDev);
        }
        cfgIntervalMs = interval;

        Serial.println("[WS] 📡 Config ricevuta dal server:");
        Serial.print("     device_name = ");
        Serial.println(cfgNomeDispositivo);
        Serial.print("     interval_ms = ");
        Serial.println(cfgIntervalMs);

      // --- Messaggio generico ---
      } else if (strcmp(typeStr, "message") == 0) {
        const char* content = doc["content"];
        Serial.print("[WS] Messaggio generico: ");
        if (content) Serial.println(content);
        else Serial.println("(senza campo 'content')");

      // --- PRIMO FOGLIO: ACK con idMsg / state ---
      } else if (strcmp(typeStr, "ack") == 0) {
        long idMsg = doc["idMsg"] | -1;
        int  state = doc["state"] | -1;

        Serial.print("[WS] ✅ ACK dal server: idMsg = ");
        Serial.print(idMsg);
        Serial.print("  state = ");
        Serial.println(state);

      // --- Altri tipi (ad esempio risposta ai dati) ---
      } else if (strcmp(typeStr, "data_ok") == 0) {
        long id = doc["id"] | -1;
        Serial.print("[WS] ✅ Dati salvati, id = ");
        Serial.println(id);

      } else if (strcmp(typeStr, "error") == 0) {
        const char* code = doc["code"] | "unknown";
        const char* msg  = doc["message"] | "";
        Serial.print("[WS] ❌ Errore dal server: ");
        Serial.print(code);
        Serial.print(" - ");
        Serial.println(msg);

      } else {
        Serial.print("[WS] Tipo di messaggio non gestito: ");
        Serial.println(typeStr);
      }

    } break;   // fine case WStype_TEXT

    case WStype_ERROR:
      Serial.println("[WS] ⚠ Errore WebSocket");
      break;

    default:
      // altri tipi che al momento non ci interessano
      break;
  }
}

// ------------------- SETUP -------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== ESP32 login JWT + WebSocket verso CakePHP/Node (con LittleFS + AP) ===");

  // Mount LittleFS
  if (!LittleFS.begin(true)) {   // true = format se non riesce
    Serial.println("❌ Errore mount LittleFS (uso comunque i default)");
  }

  // Carico configurazione da file
  bool cfgOk = loadConfig();
  if (!cfgOk) {
    Serial.println("⚠ Nessuna configurazione valida trovata, avvio modalità AP di configurazione");
    startConfigAP();
    return;   // restiamo in AP, loop gestirà configServer
  }

  // Connessione WiFi usando i parametri della config
  if (!connectWiFiFromConfig()) {
    Serial.println("⚠ WiFi da config non raggiungibile, avvio modalità AP di configurazione");
    startConfigAP();
    return;
  }

  // Se siamo qui, WiFi connesso in modalità client
  Serial.println("\nConnesso al WiFi!");
  Serial.print("IP ESP32: ");
  Serial.println(WiFi.localIP());

  // 1) Login JWT verso CakePHP (usando IP/porta della config)
  faiLoginJwt();

  // 2) Se il login è andato bene, attiviamo il WebSocket
  if (loginFatto) {
    setupWebSocket();
  }
}

// ------------------- LOOP -------------------
void loop() {
  // Se siamo in AP di configurazione, gestiamo solo la pagina web
  if (inConfigAP) {
    configServer.handleClient();
    return;
  }

  // Modalità normale: Mantiene viva la connessione WebSocket
  wsClient.loop();

  // Invia dati periodici se siamo autenticati
  inviaDatiPeriodici();

  delay(10);
}

// ------------------- FUNZIONI CONFIG / AP -------------------

// carica config da /config.json (LittleFS)
bool loadConfig() {
  if (!LittleFS.begin()) {
    if (!LittleFS.begin(true)) {
      Serial.println("LittleFS non disponibile");
      return false;
    }
  }

  if (!LittleFS.exists("/config.json")) {
    Serial.println("config.json non esiste");
    return false;
  }

  File f = LittleFS.open("/config.json", "r");
  if (!f) {
    Serial.println("Errore apertura config.json");
    return false;
  }

  StaticJsonDocument<512> doc;
  DeserializationError err = deserializeJson(doc, f);
  f.close();

  if (err) {
    Serial.print("Errore parse config.json: ");
    Serial.println(err.c_str());
    return false;
  }

  config.wifiSsid       = (const char*) (doc["wifi_ssid"]        | "");
  config.wifiPass       = (const char*) (doc["wifi_pass"]        | "");
  config.serverIp       = (const char*) (doc["server_ip"]        | DEFAULT_SERVER_IP);
  config.serverHttpPort =                 doc["server_http_port"] | DEFAULT_SERVER_HTTP_PORT;
  config.serverWsPort   =                 doc["server_ws_port"]   | DEFAULT_SERVER_WS_PORT;
  config.deviceId       = (const char*) (doc["device_id"]        | "ME01");

  if (config.wifiSsid.length() == 0) {
    Serial.println("Config senza wifi_ssid");
    return false;
  }

  Serial.println("✅ Config caricata da LittleFS:");
  Serial.print("SSID: ");      Serial.println(config.wifiSsid);
  Serial.print("server: ");    Serial.print(config.serverIp);
  Serial.print("  HTTP: ");    Serial.print(config.serverHttpPort);
  Serial.print("  WS: ");      Serial.println(config.serverWsPort);
  Serial.print("deviceId: ");  Serial.println(config.deviceId);

  return true;
}

// salva config su /config.json
bool saveConfig() {
  StaticJsonDocument<512> doc;
  doc["wifi_ssid"]        = config.wifiSsid;
  doc["wifi_pass"]        = config.wifiPass;
  doc["server_ip"]        = config.serverIp;
  doc["server_http_port"] = config.serverHttpPort;
  doc["server_ws_port"]   = config.serverWsPort;
  doc["device_id"]        = config.deviceId;

  File f = LittleFS.open("/config.json", "w");
  if (!f) {
    Serial.println("Errore apertura config.json in scrittura");
    return false;
  }

  if (serializeJson(doc, f) == 0) {
    Serial.println("Errore scrivendo config.json");
    f.close();
    return false;
  }

  f.close();
  Serial.println("✅ Config salvata su config.json");
  return true;
}

// connessione WiFi usando i dati di config
bool connectWiFiFromConfig() {
  Serial.print("Mi collego al WiFi (da config): ");
  Serial.println(config.wifiSsid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(config.wifiSsid.c_str(), config.wifiPass.c_str());

  int tentativi = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    tentativi++;
    if (tentativi > 60) { // ~30 secondi
      Serial.println("\nERRORE: non riesco a collegarmi al WiFi");
      return false;
    }
  }

  return true;
}

// modalità Access Point per configurazione
void startConfigAP() {
  inConfigAP = true;

  Serial.println("=== MODALITÀ AP DI CONFIGURAZIONE ===");
  WiFi.mode(WIFI_AP);
  WiFi.softAP("ESP32_CONFIG", "12345678");
  IPAddress ip = WiFi.softAPIP();
  Serial.print("AP IP: ");
  Serial.println(ip);

  // Pagina HTML semplice con form
  configServer.on("/", HTTP_GET, []() {
    String html = R"rawliteral(
      <html>
      <body>
        <h2>Configurazione ESP32</h2>
        <form method='POST' action='/save'>
          WiFi SSID: <input name='ssid'><br>
          WiFi Password: <input name='pass' type='password'><br>
          Server IP: <input name='sip' value=')rawliteral";
    html += DEFAULT_SERVER_IP;
    html += R"rawliteral('><br>
          Server HTTP Port: <input name='sport' value='8765'><br>
          Server WS Port: <input name='wport' value='8081'><br>
          Device ID: <input name='did' value='ME01'><br>
          <input type='submit' value='Salva'>
        </form>
      </body>
      </html>
    )rawliteral";
    configServer.send(200, "text/html", html);
  });

  configServer.on("/save", HTTP_POST, []() {
    config.wifiSsid       = configServer.arg("ssid");
    config.wifiPass       = configServer.arg("pass");
    config.serverIp       = configServer.arg("sip");
    config.serverHttpPort = configServer.arg("sport").toInt();
    config.serverWsPort   = configServer.arg("wport").toInt();
    config.deviceId       = configServer.arg("did");

    if (saveConfig()) {
      configServer.send(200, "text/html",
                        "Config salvata. Riavvio ESP32...<br>Puoi chiudere questa pagina.");
      delay(1000);
      ESP.restart();
    } else {
      configServer.send(500, "text/html", "Errore salvataggio config.json");
    }
  });

  configServer.begin();
  Serial.println("Web server di configurazione avviato");
}

// ------------------- FUNZIONI LOGIN / WS / INVIO DATI -------------------

void faiLoginJwt() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi non connesso, impossibile fare login");
    return;
  }

  HTTPClient http;

  String url = String("http://") + config.serverIp + ":" + config.serverHttpPort + LOGIN_PATH;
  Serial.print("Chiamo URL: ");
  Serial.println(url);

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000); // timeout 5 secondi

  // Corpo JSON della richiesta
  String body = String("{\"username\":\"") + LOGIN_USER +
                "\",\"password\":\"" + LOGIN_PASS + "\"}";

  Serial.print("Payload: ");
  Serial.println(body);

  int code = http.POST(body);

  Serial.print("HTTP status code: ");
  Serial.println(code);

  if (code > 0) {
    String res = http.getString();
    Serial.println("Risposta dal server:");
    Serial.println(res);

    StaticJsonDocument<512> doc;
    DeserializationError err = deserializeJson(doc, res);
    if (err) {
      Serial.print("Errore parse JSON: ");
      Serial.println(err.c_str());
      loginFatto = false;
    } else {
      bool success = doc["success"] | false;
      const char* token = doc["token"];

      Serial.print("success = ");
      Serial.println(success ? "true" : "false");

      if (success && token != nullptr) {
        jwtToken = String(token);
        Serial.print("JWT token estratto: ");
        Serial.println(jwtToken);
        loginFatto = true;
      } else {
        Serial.println("Login fallito o token mancante.");
        loginFatto = false;
      }
    }

  } else {
    Serial.print("Errore HTTP: ");
    Serial.println(http.errorToString(code));
    loginFatto = false;
  }

  http.end();
}

void setupWebSocket() {
  Serial.println("[WS] Mi collego al server WebSocket...");

  wsClient.begin(config.serverIp.c_str(), config.serverWsPort, WS_PATH);
  wsClient.onEvent(onWsEvent);
  wsClient.setReconnectInterval(5000);          // tenta di riconnettersi ogni 5s
  wsClient.enableHeartbeat(15000, 3000, 2);     // ping/pong per tenere viva la connessione
}

// Invia un messaggio "message" ogni cfgIntervalMs millisecondi
// PRIMO FOGLIO: ID dispositivo, IDMSG, STATE, CONTENT{...}
void inviaDatiPeriodici() {
  if (!wsAutenticato) return;  // invio solo se il WebSocket è autenticato

  unsigned long now = millis();
  if (now - lastDataSend < (unsigned long)cfgIntervalMs) return;
  lastDataSend = now;

  // Esempio: dati finti di sensore
  float temp = 20.0 + (random(0, 100) / 10.0);   // 20.0 - 29.9
  float umid = 40.0 + (random(0, 600) / 10.0);   // 40.0 - 99.9

  DynamicJsonDocument doc(512);

  // campi principali (come nel foglio 1)
  doc["type"]     = "message";           // tipo messaggio device -> server
  doc["idDevice"] = config.deviceId;     // ID del dispositivo (ME01, salvato in config)
  doc["idMsg"]    = ++lastMsgId;         // ID del messaggio (contatore che cresce)
  doc["state"]    = 0;                   // 0 = in invio (foglio 1)

  // oggetto CONTENT con i dati reali
  JsonObject content = doc.createNestedObject("content");
  content["temp"]        = temp;
  content["humid"]       = umid;
  content["ts"]          = now;
  content["device_name"] = cfgNomeDispositivo;
  content["username"]    = LOGIN_USER;

  String msg;
  serializeJson(doc, msg);

  Serial.print("[WS] Invia dati (ID/IDMSG/CONTENT): ");
  Serial.println(msg);

  wsClient.sendTXT(msg);
}
