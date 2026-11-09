<?php
$telemetry = $payload['payload'] ?? $payload['content'] ?? $payload;
?>

<h2>Dashboard dispositivo <?= h($deviceId) ?></h2>

<h3>Ultimi dati ricevuti</h3>

<table>
    <tr>
        <th>RPM</th>
        <td><?= h($telemetry['rpm'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>RPM Raw</th>
        <td><?= h($telemetry['rpm_raw'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>RPM Filtered</th>
        <td><?= h($telemetry['rpm_filtered'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>Duty</th>
        <td><?= h($telemetry['duty'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>Target RPM</th>
        <td><?= h($telemetry['targetRPM'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>Temperatura</th>
        <td><?= h($telemetry['temperature'] ?? '-') ?> °C</td>
    </tr>

    <tr>
        <th>Umidità</th>
        <td><?= h($telemetry['humidity'] ?? '-') ?> %</td>
    </tr>

    <tr>
        <th>Kp</th>
        <td><?= h($telemetry['Kp'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>Ki</th>
        <td><?= h($telemetry['Ki'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>Kd</th>
        <td><?= h($telemetry['Kd'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>PID Sample</th>
        <td><?= h($telemetry['pidSamp'] ?? '-') ?></td>
    </tr>

    <tr>
        <th>PID Enabled</th>
        <td><?= !empty($telemetry['fanPIDEnabled']) ? 'Si' : 'No' ?></td>
    </tr>

    <tr>
        <th>Spinning</th>
        <td><?= !empty($telemetry['spinning']) ? 'Si' : 'No' ?></td>
    </tr>

    <tr>
        <th>Timestamp</th>
        <td><?= h($telemetry['ts'] ?? '-') ?></td>
    </tr>
</table>

<hr>

<h3>Invia nuova configurazione</h3>

<?= $this->Form->create(null, [
    'url' => ['action' => 'sendConfig', $deviceId]
]) ?>

<fieldset>
    <legend>Parametri PID</legend>

    <?= $this->Form->control('target_rpm', [
        'label' => 'Target RPM',
        'value' => $deviceConfig->target_rpm
    ]) ?>

    <?= $this->Form->control('kp', [
        'label' => 'Kp',
        'value' => $deviceConfig->kp
    ]) ?>

    <?= $this->Form->control('ki', [
        'label' => 'Ki',
        'value' => $deviceConfig->ki
    ]) ?>

    <?= $this->Form->control('kd', [
        'label' => 'Kd',
        'value' => $deviceConfig->kd
    ]) ?>

    <?= $this->Form->control('pid_samp', [
        'label' => 'PID Sample (ms)',
        'value' => $deviceConfig->pid_samp
    ]) ?>

    <?= $this->Form->control('temp_threshold', [
        'label' => 'Soglia temperatura (°C)',
        'value' => $deviceConfig->temp_threshold ?? 26
    ]) ?>

    <?= $this->Form->control('humidity_threshold', [
        'label' => 'Soglia umidità (%)',
        'value' => $deviceConfig->humidity_threshold ?? 60
    ]) ?>

    <?= $this->Form->control('fan_pid_enabled', [
        'type' => 'checkbox',
        'label' => 'PID attivo',
        'checked' => (bool)$deviceConfig->fan_pid_enabled
    ]) ?>
</fieldset>

<?= $this->Form->button('Invia configurazione') ?>

<?= $this->Form->end() ?>