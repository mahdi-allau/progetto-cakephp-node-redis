<h1>Messaggi da Redis (ws_messages)</h1>

<?php if (empty($redisMessages)): ?>
  <p>Nessun messaggio presente in Redis.</p>
<?php else: ?>
  <table border="1" cellpadding="5" cellspacing="0">
    <tr>
      <th>Username</th>
      <th>Content</th>
    </tr>
    <?php foreach ($redisMessages as $m): ?>
      <tr>
        <td><?= h($m['username'] ?? '') ?></td>
        <td><?= h($m['content'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
