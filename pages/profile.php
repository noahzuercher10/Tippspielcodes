<?php
$active = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Mein Profil</h2>

  <div class="profile-head">
    <span class="avatar profile-avatar"><?= htmlspecialchars(initials($user)) ?></span>

    <div class="profile-bg">
      <strong>Hintergrundbild</strong>
      <?php if ($user['background_image']): ?>
        <img src="/Tippspiel/<?= htmlspecialchars($user['background_image']) ?>"
             alt="Aktuelles Hintergrundbild" class="bg-preview">
        <p style="color:var(--muted);font-size:12px">Aktuell aktiv.</p>
      <?php else: ?>
        <p style="color:var(--muted);font-size:13px">Standard-Hintergrundbild aktiv.</p>
      <?php endif; ?>

      <form id="bg-form" enctype="multipart/form-data">
        <label class="btn primary small" for="bg-file">Hintergrundbild hinzufuegen</label>
        <input type="file" id="bg-file" name="background" accept="image/*" hidden>
        <?php if ($user['background_image']): ?>
          <button type="button" id="bg-remove" class="btn small">Standard wiederherstellen</button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <table>
    <tr><th>Benutzername</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
    <tr><th>Vor- / Nachname</th><td><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></td></tr>
    <tr><th>E-Mail</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
    <tr><th>Punkte</th><td><?= (int)$user['points_total'] ?></td></tr>
    <tr><th>Imaginaeres Geld</th><td><?= number_format((float)$user['money_balance'],2,'.',"'") ?></td></tr>
    <tr><th>Erstellt am</th><td><?= htmlspecialchars($user['created_at']) ?></td></tr>
  </table>
</section>

<section class="card">
  <h2>Meine letzten Tipps</h2>
  <table>
    <thead><tr><th>Datum</th><th>Spiel</th><th>Tipp</th><th>Resultat</th><th>Modus</th><th>Ertrag</th></tr></thead>
    <tbody>
    <?php
      $stmt = db()->prepare(
        'SELECT b.*, m.match_datetime, m.home_score, m.away_score,
                h.name AS hn, a.name AS an
         FROM bets b
         JOIN matches m ON m.id = b.match_id
         JOIN teams h   ON h.id = m.home_team_id
         JOIN teams a   ON a.id = m.away_team_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC LIMIT 25'
      );
      $stmt->execute([$user['id']]);
      foreach ($stmt as $r):
          $tipText = $r['mode'] === 'points'
            ? (int)$r['tip_home'].' : '.(int)$r['tip_away']
            : ($r['tip_winner'] === 'home' ? 'Heimsieg'
              : ($r['tip_winner'] === 'away' ? 'Auswaertssieg' : 'Unentschieden'));
      ?>
        <tr>
          <td><?= htmlspecialchars($r['match_datetime']) ?></td>
          <td><?= htmlspecialchars($r['hn'].' vs '.$r['an']) ?></td>
          <td><?= htmlspecialchars($tipText) ?></td>
          <td><?= $r['home_score']!==null ? ((int)$r['home_score'].' : '.(int)$r['away_score']) : '-' ?></td>
          <td><span class="badge <?= $r['mode'] ?>"><?= $r['mode'] ?></span></td>
          <td><?= $r['mode']==='points'
                  ? (int)$r['points_earned'].' Pkt'
                  : number_format((float)$r['money_earned'],2,'.',"'") ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>
(() => {
  const fileInput = document.getElementById('bg-file');
  const removeBtn = document.getElementById('bg-remove');
  fileInput.addEventListener('change', async () => {
    if (!fileInput.files.length) return;
    const fd = new FormData();
    fd.append('background', fileInput.files[0]);
    try {
      const r = await fetch('/Tippspiel/api/upload-background.php',
        { method:'POST', credentials:'same-origin', body:fd });
      const data = await r.json();
      if (!r.ok) throw new Error(data.error || 'Upload fehlgeschlagen');
      Tippspiel.toast('Hintergrundbild gespeichert!', 'ok');
      setTimeout(()=>location.reload(), 600);
    } catch (e) { Tippspiel.toast(e.message, 'error'); }
  });

  if (removeBtn) {
    removeBtn.addEventListener('click', async () => {
      const fd = new FormData(); fd.append('action','remove');
      const r = await fetch('/Tippspiel/api/upload-background.php',
        { method:'POST', credentials:'same-origin', body:fd });
      if (r.ok) { Tippspiel.toast('Standard-Hintergrund aktiv','ok'); setTimeout(()=>location.reload(),500); }
    });
  }
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
