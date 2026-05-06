<?php
$active = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="card">
  <h2>Mein Profil</h2>
  <p>
    <span class="avatar" style="width:64px;height:64px;font-size:24px"><?= htmlspecialchars(initials($user)) ?></span>
  </p>
  <table>
    <tr><th>Benutzername</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
    <tr><th>Vor- / Nachname</th><td><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></td></tr>
    <tr><th>E-Mail</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
    <tr><th>Rolle</th><td><?= htmlspecialchars($user['role']) ?></td></tr>
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
      foreach ($stmt as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['match_datetime']) ?></td>
          <td><?= htmlspecialchars($r['hn'].' vs '.$r['an']) ?></td>
          <td><?= (int)$r['tip_home'] ?> : <?= (int)$r['tip_away'] ?></td>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
