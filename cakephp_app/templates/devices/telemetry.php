<h2>Telemetria dispositivo <?= h($deviceId) ?></h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Device</th>
            <th>RPM</th>
            <th>RPM Raw</th>
            <th>RPM Filtered</th>
            <th>Target RPM</th>
            <th>Duty</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($points as $point): ?>
        <tr>
            <td><?= h($point->id) ?></td>
            <td><?= h($point->device_id) ?></td>
            <td><?= h($point->rpm) ?></td>
            <td><?= h($point->rpm_raw) ?></td>
            <td><?= h($point->rpm_filtered) ?></td>
            <td><?= h($point->target_rpm) ?></td>
            <td><?= h($point->duty) ?></td>
            <td><?= h($point->created) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>