<?php
declare(strict_types=1);

namespace App\Controller;

use Predis\Client as RedisClient;

class DevicesController extends AppController
{
    public function dashboard(string $deviceId = 'ME01')
    {
        $messagesTable = $this->fetchTable('Messages');
        $configsTable = $this->fetchTable('DeviceConfigs');

        $latestMessage = $messagesTable->find()
            ->orderBy(['id' => 'DESC'])
            ->first();

        $deviceConfig = $configsTable->find()
            ->where(['device_id' => $deviceId])
            ->first();

        if (!$deviceConfig) {
            $deviceConfig = $configsTable->newEmptyEntity();
            $deviceConfig->device_id = $deviceId;
            $deviceConfig->target_rpm = 3000;
            $deviceConfig->kp = 0.07;
            $deviceConfig->ki = 0.01;
            $deviceConfig->kd = 0.2;
            $deviceConfig->pid_samp = 200;
            $deviceConfig->fan_pid_enabled = true;
            $deviceConfig->temp_threshold = 26;
            $deviceConfig->humidity_threshold = 60;

            $configsTable->save($deviceConfig);
        }

        $payload = [];
        if ($latestMessage && !empty($latestMessage->content)) {
            $decoded = json_decode($latestMessage->content, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $this->set(compact('deviceId', 'latestMessage', 'deviceConfig', 'payload'));
    }

    public function sendConfig(string $deviceId = 'ME01')
    {
        $this->request->allowMethod(['post']);

        $configsTable = $this->fetchTable('DeviceConfigs');

        $config = $configsTable->find()
            ->where(['device_id' => $deviceId])
            ->first();

        if (!$config) {
            $config = $configsTable->newEmptyEntity();
            $config->device_id = $deviceId;
        }

        $data = $this->request->getData();

        $config = $configsTable->patchEntity($config, [
            'target_rpm' => (float)($data['target_rpm'] ?? 3000),
            'kp' => (float)($data['kp'] ?? 0.07),
            'ki' => (float)($data['ki'] ?? 0.01),
            'kd' => (float)($data['kd'] ?? 0.2),
            'pid_samp' => (int)($data['pid_samp'] ?? 200),
            'fan_pid_enabled' => !empty($data['fan_pid_enabled']),
            'temp_threshold' => (float)($data['temp_threshold'] ?? 26),
            'humidity_threshold' => (float)($data['humidity_threshold'] ?? 60),
        ]);

        if (!$configsTable->save($config)) {
            $this->Flash->error('Errore nel salvataggio configurazione.');
            return $this->redirect(['action' => 'dashboard', $deviceId]);
        }

        /*
         * STORICO CONFIGURAZIONI
         * Ogni volta che si invia una configurazione,
         * viene salvata una nuova riga nella tabella device_config_history.
         */
        $historyTable = $this->fetchTable('DeviceConfigHistory');

        $history = $historyTable->newEmptyEntity();

        $history = $historyTable->patchEntity($history, [
            'device_id' => $deviceId,
            'target_rpm' => (float)$config->target_rpm,
            'kp' => (float)$config->kp,
            'ki' => (float)$config->ki,
            'kd' => (float)$config->kd,
            'pid_samp' => (int)$config->pid_samp,
            'fan_pid_enabled' => (bool)$config->fan_pid_enabled,
            'temp_threshold' => (float)$config->temp_threshold,
            'humidity_threshold' => (float)$config->humidity_threshold,
        ]);

        $historyTable->save($history);

        $wsMessage = [
            'type' => 'config',
            'device_id' => $deviceId,
            'device_name' => 'esp32-default',

            'targetRPM' => (float)$config->target_rpm,
            'Kp' => (float)$config->kp,
            'Ki' => (float)$config->ki,
            'Kd' => (float)$config->kd,
            'pidSamp' => (int)$config->pid_samp,
            'fanPIDEnabled' => (bool)$config->fan_pid_enabled,

            'tempThreshold' => (float)$config->temp_threshold,
            'humidityThreshold' => (float)$config->humidity_threshold,

            'interval_ms' => 2000,
        ];

        try {
            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
            ]);

            $redis->publish('canale_config', json_encode($wsMessage));

            $this->Flash->success('Configurazione inviata al dispositivo e salvata nello storico.');
        } catch (\Throwable $e) {
            $this->Flash->error('Config salvata, ma invio Redis fallito: ' . $e->getMessage());
        }

        return $this->redirect(['action' => 'dashboard', $deviceId]);
    }

    public function configHistory(string $deviceId = 'ME01')
    {
        $historyTable = $this->fetchTable('DeviceConfigHistory');

        $history = $historyTable->find()
            ->where(['device_id' => $deviceId])
            ->orderBy(['id' => 'DESC'])
            ->all();

        $this->set(compact('deviceId', 'history'));
    }

    public function telemetry(string $deviceId = 'ME01')
    {
        $telemetryTable = $this->fetchTable('DeviceTelemetry');

        $points = $telemetryTable->find()
            ->where(['device_id' => $deviceId])
            ->orderBy(['id' => 'DESC'])
            ->limit(20)
            ->all();

        $this->set(compact('deviceId', 'points'));
    }

    public function plot(string $deviceId = 'ME01')
    {
        $telemetryTable = $this->fetchTable('DeviceTelemetry');

        $points = $telemetryTable->find()
            ->where(['device_id' => $deviceId])
            ->orderBy(['id' => 'DESC'])
            ->limit(50)
            ->all()
            ->toList();

        $points = array_reverse($points);

        $labels = [];
        $rpm = [];
        $rpmRaw = [];
        $rpmFiltered = [];
        $target = [];

        foreach ($points as $p) {
            $labels[] = $p->created ? $p->created->format('H:i:s') : '';
            $rpm[] = $p->rpm;
            $rpmRaw[] = $p->rpm_raw;
            $rpmFiltered[] = $p->rpm_filtered;
            $target[] = $p->target_rpm;
        }

        $this->set(compact('deviceId', 'labels', 'rpm', 'rpmRaw', 'rpmFiltered', 'target'));
    }
}