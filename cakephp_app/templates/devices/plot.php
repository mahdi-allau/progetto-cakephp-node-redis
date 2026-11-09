<h2>Grafico RPM dispositivo <?= h($deviceId) ?></h2>

<p>
    <a href="/devices/telemetry/<?= h($deviceId) ?>">Vai alla tabella telemetria</a>
</p>

<canvas id="rpmChart" height="100"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const rpm = <?= json_encode($rpm) ?>;
const rpmRaw = <?= json_encode($rpmRaw) ?>;
const rpmFiltered = <?= json_encode($rpmFiltered) ?>;
const target = <?= json_encode($target) ?>;

new Chart(document.getElementById('rpmChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'RPM',
                data: rpm,
                borderWidth: 2,
                fill: false
            },
            {
                label: 'RPM Raw',
                data: rpmRaw,
                borderWidth: 2,
                fill: false
            },
            {
                label: 'RPM Filtered',
                data: rpmFiltered,
                borderWidth: 2,
                fill: false
            },
            {
                label: 'Target RPM',
                data: target,
                borderWidth: 2,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        animation: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

setTimeout(() => {
    location.reload();
}, 2000);
</script>