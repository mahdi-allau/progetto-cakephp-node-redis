<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\AppController;
use Predis\Client as RedisClient;
use Cake\Http\Response;

class ApiController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->autoRender = false;
    }

    /**
     * Riceve i messaggi da Node.js (che arrivano dall'ESP32 via WebSocket)
     * URL: POST /api/ws-message
     */
    public function wsMessage(): Response
    {
        $this->request->allowMethod(['post']);

        $data = $this->getRequest()->getData();

        if (empty($data)) {
            $raw  = (string)$this->getRequest()->getBody();
            $data = json_decode($raw, true);
        }

        if (!is_array($data)) {
            return $this->jsonResponse([
                'success' => false,
                'error'   => 'invalid_json',
                'message' => 'Corpo della richiesta non è JSON valido',
            ]);
        }

        // ====== Estraggo i campi principali ======
        $idDevice = $data['idDevice'] ?? null;
        $idMsg    = $data['idMsg'] ?? null;
        $state    = $data['state'] ?? null;

        $username = $data['username'] ?? 'ws-client';

        $contentPayload = $data['content'] ?? [];

        $deviceName = $data['device_name'] ?? null;
        $temp       = $data['temp'] ?? null;
        $humid      = $data['humid'] ?? null;
        $ts         = $data['ts'] ?? null;

        $wrappedContent = [
            'idDevice'    => $idDevice,
            'idMsg'       => $idMsg,
            'state'       => $state,
            'device_name' => $deviceName,
            'temp'        => $temp,
            'humid'       => $humid,
            'ts'          => $ts,
            'payload'     => $contentPayload,
        ];

        $recordData = [
            'username' => $username,
            'content'  => json_encode($wrappedContent, JSON_UNESCAPED_UNICODE),
        ];

        // ====== Salvo nella tabella Messages ======
        $messagesTable = $this->fetchTable('Messages');
        $message = $messagesTable->newEmptyEntity();
        $message = $messagesTable->patchEntity($message, $recordData);

        if ($message->getErrors()) {
            return $this->jsonResponse([
                'success' => false,
                'errors'  => $message->getErrors(),
            ]);
        }

        if (!$messagesTable->save($message)) {
            return $this->jsonResponse([
                'success' => false,
                'errors'  => $message->getErrors(),
            ]);
        }

        // ====== Salvo anche nella tabella device_telemetry ======
        try {
            $telemetryTable = $this->fetchTable('DeviceTelemetry');

            $telemetry = $telemetryTable->newEntity([
                'device_id'    => $idDevice ?? 'ME01',
                'rpm'          => $contentPayload['rpm'] ?? null,
                'rpm_raw'      => $contentPayload['rpm_raw'] ?? null,
                'rpm_filtered' => $contentPayload['rpm_filtered'] ?? null,
                'target_rpm'   => $contentPayload['targetRPM'] ?? null,
                'duty'         => $contentPayload['duty'] ?? null,
            ]);

            if (!$telemetry->getErrors()) {
                $telemetryTable->save($telemetry);
            }
        } catch (\Throwable $e) {
            $this->log('Errore salvataggio device_telemetry: ' . $e->getMessage(), 'error');
        }

        // ====== Pubblico anche su Redis ======
        try {
            $redis = new RedisClient();

            $payload = [
                'id'       => $message->id,
                'username' => $message->username,
                'content'  => $message->content,
                'created'  => $message->created?->format('Y-m-d H:i:s'),
            ];

            $redis->publish('canale_messaggi', json_encode($payload));
        } catch (\Throwable $e) {
            $this->log('Errore Redis (ApiController::wsMessage): ' . $e->getMessage(), 'error');
        }

        return $this->jsonResponse([
            'success' => true,
            'id'      => $message->id,
        ]);
    }

    /**
     * Helper per mandare una risposta JSON
     */
    private function jsonResponse(array $data): Response
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        return $this->response
            ->withType('application/json')
            ->withStringBody($json);
    }
}