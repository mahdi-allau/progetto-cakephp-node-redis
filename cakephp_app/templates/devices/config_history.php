<h2>Storico configurazioni dispositivo <?= h($deviceId) ?></h2>

<p>
    <a href="<?= $this->Url->build(['action' => 'dashboard', $deviceId]) ?>">
        Torna alla dashboard
    </a>
</p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Target RPM</th>
            <th>Kp</th>
            <th>Ki</th>
            <th>Kd</th>
            <th>PID Sample</th>
            <th>PID attivo</th>
            <th>Soglia temperatura</th>
            <th>Soglia umidità</th>
            <th>Data</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($history as $row): ?>
            <tr>
                <td><?= h($row->id) ?></td>
                <td><?= h($row->target_rpm) ?></td>
                <td><?= h($row->kp) ?></td>
                <td><?= h($row->ki) ?></td>
                <td><?= h($row->kd) ?></td>
                <td><?= h($row->pid_samp) ?></td>
                <td><?= $row->fan_pid_enabled ? 'Si' : 'No' ?></td>
                <td><?= h($row->temp_threshold) ?> °C</td>
                <td><?= h($row->humidity_threshold) ?> %</td>
                <td><?= h($row->created) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($history->isEmpty()): ?>
    <p>Nessuna configurazione salvata nello storico.</p>
<?php endif; ?>