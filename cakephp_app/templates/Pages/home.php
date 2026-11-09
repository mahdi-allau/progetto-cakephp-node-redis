<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 */
$this->disableAutoLayout();

$deviceId = 'ME01';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Panel</title>
    <?= $this->Html->meta('icon') ?>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }

        .header {
            background: #ffffff;
            border-bottom: 1px solid #d1d5db;
        }

        .container {
            width: min(1100px, 94%);
            margin: 0 auto;
        }

        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 0;
            gap: 16px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 600;
            color: #111827;
        }

        .subtitle {
            margin: 4px 0 0;
            font-size: 0.95rem;
            color: #6b7280;
        }

        .main {
            padding: 28px 0;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 24px;
        }

        .section-title {
            margin: 0 0 18px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #111827;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            padding: 20px;
        }

        .card h3 {
            margin: 0 0 10px;
            font-size: 1rem;
            color: #111827;
        }

        .card p {
            margin: 0 0 14px;
            color: #4b5563;
            line-height: 1.6;
            font-size: 0.95rem;
            min-height: 66px;
        }

        .meta {
            margin-bottom: 14px;
            padding: 10px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-family: Consolas, monospace;
            font-size: 0.87rem;
            color: #374151;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            background: #1f2937;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.94rem;
            font-weight: 600;
        }

        .btn:hover {
            background: #111827;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 18px;
        }

        .info-box {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 20px;
        }

        .info-box h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1rem;
            color: #111827;
        }

        .info-box ul {
            margin: 0;
            padding-left: 18px;
            color: #4b5563;
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .footer {
            padding: 20px 0 30px;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

        @media (max-width: 900px) {
            .cards,
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="container header-inner">
            <div>
                <h1 class="title">ESP32-S3 Control Panel</h1>
                <p class="subtitle">Device: <?= h($deviceId) ?></p>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <div class="panel">
                <h2 class="section-title">Navigation</h2>

                <div class="cards">
                    <div class="card">
                        <h3>Configuration</h3>
                        <p>View current device data and send PID configuration parameters.</p>
                        <div class="meta">/devices/dashboard/<?= h($deviceId) ?></div>
                        <a class="btn" href="/devices/dashboard/<?= h($deviceId) ?>">Open</a>
                    </div>

                    <div class="card">
                        <h3>Telemetry</h3>
                        <p>View stored telemetry records from the database.</p>
                        <div class="meta">/devices/telemetry/<?= h($deviceId) ?></div>
                        <a class="btn" href="/devices/telemetry/<?= h($deviceId) ?>">Open</a>
                    </div>

                    <div class="card">
                        <h3>RPM Chart</h3>
                        <p>View RPM, raw RPM, filtered RPM and target RPM trend.</p>
                        <div class="meta">/devices/plot/<?= h($deviceId) ?></div>
                        <a class="btn" href="/devices/plot/<?= h($deviceId) ?>">Open</a>
                    </div>
                    <div class="card">
    <h3>Config History</h3>
    <p>View saved configuration parameters history.</p>
    <div class="meta">/devices/config-history/<?= h($deviceId) ?></div>
    <a class="btn" href="/devices/config-history/<?= h($deviceId) ?>">Open</a>
</div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <h3>System</h3>
                    <ul>
                        <li>ESP32-S3 device integration</li>
                        <li>PID control management</li>
                        <li>Telemetry acquisition and storage</li>
                        <li>Remote configuration via web interface</li>
                    </ul>
                </div>

                <div class="info-box">
                    <h3>Services</h3>
                    <ul>
                        <li>CakePHP application</li>
                        <li>Node.js WebSocket bridge</li>
                        <li>Redis messaging layer</li>
                        <li>Database persistence layer</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        Control and monitoring interface
    </footer>

</body>
</html>