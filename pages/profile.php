<?php
$active = 'profile';
require_once __DIR__ . '/../includes/header.php';

$isDark = ($user['theme'] ?? 'light') === 'dark';
$hasPic = !empty($user['profile_picture']);
$picUrl = $hasPic ? '/Tippspiel/' . htmlspecialchars($user['profile_picture']) : '';
?>
<section class="card">
  <h2>Profil</h2>

  <div class="profile-head">
    <?php if ($hasPic): ?>
      <img src="<?= $picUrl ?>" alt="Profilbild" class="profile-avatar" style="object-fit:cover">
    <?php else: ?>
      <span class="avatar profile-avatar"><?= htmlspecialchars(initials($user)) ?></span>
    <?php endif; ?>

    <div class="profile-info">
      <strong style="font-size:18px"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
      <span style="color:var(--muted)">@<?= htmlspecialchars($user['username']) ?></span>
      <span style="color:var(--muted);font-size:13px"><?= htmlspecialchars($user['email']) ?></span>

      <div style="margin-top:8px;display:flex;gap:10px;flex-wrap:wrap">
        <label class="btn primary small" for="pic-file" style="cursor:pointer">Profilbild ändern</label>
        <input type="file" id="pic-file" accept="image/*" hidden>
        <?php if ($hasPic): ?>
          <button type="button" id="pic-remove" class="btn small">Bild entfernen</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <table style="max-width:500px">
    <tr><th>Punkte</th><td><strong style="color:var(--primary)"><?= (int)$user['points_total'] ?></strong></td></tr>
    <tr><th>Guthaben</th><td><strong style="color:var(--accent)"><?= number_format((float)$user['money_balance'],2,'.',"'") ?></strong></td></tr>
    <tr><th>Dabei seit</th><td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td></tr>
  </table>
</section>

<section class="card">
  <h2>Darstellung</h2>
  <div class="theme-toggle">
    <span style="font-size:14px">Dark Mode</span>
    <label class="toggle-switch">
      <input type="checkbox" id="theme-toggle" <?= $isDark ? 'checked' : '' ?>>
      <span class="toggle-slider"></span>
    </label>
  </div>
</section>

<section class="card">
  <h2>Meine letzten Tipps</h2>
  <table>
    <thead><tr><th>Datum</th><th>Spiel</th><th>Tipp</th><th>Ergebnis</th><th>Modus</th><th>Ertrag</th></tr></thead>
    <tbody>
    <?php
      $stmt = db()->prepare(
        'SELECT b.*, m.match_datetime, m.home_score, m.away_score,
                m.home_name AS hn, m.away_name AS an
         FROM bets b
         JOIN matches m ON m.id = b.match_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC LIMIT 25'
      );
      $stmt->execute([$user['id']]);
      $rows = $stmt->fetchAll();
      if (!$rows):
    ?>
      <tr><td colspan="6" style="color:var(--muted);text-align:center;padding:20px">Noch keine Tipps abgegeben.</td></tr>
    <?php else: foreach ($rows as $r):
          $tipText = $r['mode'] === 'points'
            ? (int)$r['tip_home'].' : '.(int)$r['tip_away']
            : ($r['tip_winner'] === 'home' ? 'Heimsieg'
              : ($r['tip_winner'] === 'away' ? 'Auswärtssieg' : 'Unentschieden'));
          $ertrag = $r['mode'] === 'points'
            ? ((int)$r['points_earned'].' Pkt')
            : number_format((float)$r['money_earned'],2,'.',"'");
    ?>
      <tr>
        <td><?= date('d.m.Y', strtotime($r['match_datetime'])) ?></td>
        <td><?= htmlspecialchars($r['hn'].' – '.$r['an']) ?></td>
        <td><?= htmlspecialchars($tipText) ?></td>
        <td><?= $r['home_score'] !== null ? ((int)$r['home_score'].' : '.(int)$r['away_score']) : '–' ?></td>
        <td><span class="badge <?= $r['mode'] ?>"><?= $r['mode'] === 'points' ? 'Punkte' : 'Geld' ?></span></td>
        <td><?= htmlspecialchars($ertrag) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</section>

<script>
(() => {
  const fileInput = document.getElementById('pic-file');
  const removeBtn = document.getElementById('pic-remove');
  const themeToggle = document.getElementById('theme-toggle');

  fileInput.addEventListener('change', async () => {
    if (!fileInput.files.length) return;
    const fd = new FormData();
    fd.append('avatar', fileInput.files[0]);
    try {
      const r = await fetch('/Tippspiel/api/upload-avatar.php',
        { method: 'POST', credentials: 'same-origin', body: fd });
      const data = await r.json();
      if (!r.ok) throw new Error(data.error || 'Upload fehlgeschlagen');
      Tippspiel.toast('Profilbild gespeichert!', 'ok');
      setTimeout(() => location.reload(), 600);
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  });

  if (removeBtn) {
    removeBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('action', 'remove');
      const r = await fetch('/Tippspiel/api/upload-avatar.php',
        { method: 'POST', credentials: 'same-origin', body: fd });
      if (r.ok) {
        Tippspiel.toast('Profilbild entfernt', 'ok');
        setTimeout(() => location.reload(), 500);
      }
    });
  }

  themeToggle.addEventListener('change', async () => {
    const theme = themeToggle.checked ? 'dark' : 'light';
    document.body.classList.toggle('dark', themeToggle.checked);
    try {
      await Tippspiel.post('/Tippspiel/api/update-theme.php', { theme });
    } catch (e) { console.error(e); }
  });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
