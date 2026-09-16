<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

$stats = ['questions' => 0, 'active' => 0, 'pending' => 0];
try { $stats['questions'] = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions")->fetchColumn(); } catch (Exception $e) {}
try { $stats['active']    = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions WHERE is_active=1")->fetchColumn(); } catch (Exception $e) {}
try { $stats['pending']   = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions WHERE is_active=0 AND submitted_by IS NOT NULL")->fetchColumn(); } catch (Exception $e) {}

// ── ⚔️ Sual Duellosu statistikası ──────────────────────────────────────
$sd = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $sd['games']   = (int)db()->query("SELECT COUNT(*) FROM es_duello_rooms WHERE status='finished'")->fetchColumn(); } catch (Exception $e) {}
try { $sd['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM es_duello_players")->fetchColumn(); } catch (Exception $e) {}
try { $sd['top']     = (int)db()->query("SELECT MAX(score) FROM es_duello_players")->fetchColumn(); } catch (Exception $e) {}
try { $sd['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='duello' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentGames = [];
try {
    $recentGames = db()->query("SELECT r.id, r.code, r.category, r.difficulty, r.total_rounds, r.created_at,
                                 (SELECT COUNT(*) FROM es_duello_players p WHERE p.room_id=r.id) AS player_count,
                                 (SELECT MAX(score) FROM es_duello_players p WHERE p.room_id=r.id AND p.left_at IS NULL) AS top_score
                                 FROM es_duello_rooms r
                                 WHERE r.status='finished'
                                 ORDER BY r.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── 📺 Tele Time statistikası ──────────────────────────────────────────
$tt = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $tt['games']   = (int)db()->query("SELECT COUNT(*) FROM tt_scores")->fetchColumn(); } catch (Exception $e) {}
try { $tt['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM tt_scores")->fetchColumn(); } catch (Exception $e) {}
try { $tt['top']     = (int)db()->query("SELECT MAX(score) FROM tt_scores")->fetchColumn(); } catch (Exception $e) {}
try { $tt['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='teletime' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentTtGames = [];
try {
    $recentTtGames = db()->query("SELECT s.id, s.score, s.boxes_total, s.boxes_correct, s.categories, s.created_at, u.name AS player_name
                                   FROM tt_scores s
                                   JOIN users u ON u.id = s.user_id
                                   ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── ⛓️ Zəncir statistikası ───────────────────────────────────────────────
$zc = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $zc['games']   = (int)db()->query("SELECT COUNT(*) FROM zc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $zc['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM zc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $zc['top']     = (int)db()->query("SELECT MAX(score) FROM zc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $zc['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='zencir' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentZcRuns = [];
try {
    $recentZcRuns = db()->query("SELECT s.id, s.score, s.max_streak, s.questions_answered, s.correct_count, s.created_at, u.name AS player_name
                                  FROM zc_scores s
                                  JOIN users u ON u.id = s.user_id
                                  ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── 💰 Milyonçu statistikası ─────────────────────────────────────────────
$mc = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $mc['games']   = (int)db()->query("SELECT COUNT(*) FROM mc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $mc['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM mc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $mc['top']     = (int)db()->query("SELECT MAX(prize) FROM mc_scores")->fetchColumn(); } catch (Exception $e) {}
try { $mc['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='milyoncu' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentMcRuns = [];
try {
    $recentMcRuns = db()->query("SELECT s.id, s.prize, s.level_reached, s.walked_away, s.created_at, u.name AS player_name
                                  FROM mc_scores s
                                  JOIN users u ON u.id = s.user_id
                                  ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── ⚡ Sürət Turu statistikası ────────────────────────────────────────────
$sr = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $sr['games']   = (int)db()->query("SELECT COUNT(*) FROM sr_scores")->fetchColumn(); } catch (Exception $e) {}
try { $sr['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM sr_scores")->fetchColumn(); } catch (Exception $e) {}
try { $sr['top']     = (int)db()->query("SELECT MAX(score) FROM sr_scores")->fetchColumn(); } catch (Exception $e) {}
try { $sr['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='suretturu' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentSrRuns = [];
try {
    $recentSrRuns = db()->query("SELECT s.id, s.score, s.max_streak, s.questions_answered, s.correct_count, s.created_at, u.name AS player_name
                                  FROM sr_scores s
                                  JOIN users u ON u.id = s.user_id
                                  ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── 🔤 Sözü Tap statistikası ─────────────────────────────────────────────
$sz = ['words' => 0, 'games' => 0, 'players' => 0, 'won' => 0, 'coins' => 0];
try { $sz['words']   = (int)db()->query("SELECT COUNT(*) FROM sz_words WHERE is_active=1")->fetchColumn(); } catch (Exception $e) {}
try { $sz['games']   = (int)db()->query("SELECT COUNT(*) FROM sz_results")->fetchColumn(); } catch (Exception $e) {}
try { $sz['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM sz_results")->fetchColumn(); } catch (Exception $e) {}
try { $sz['won']     = (int)db()->query("SELECT COALESCE(SUM(won),0) FROM sz_results")->fetchColumn(); } catch (Exception $e) {}
try { $sz['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='sozutap' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentSzResults = [];
try {
    $recentSzResults = db()->query("SELECT s.id, s.guesses, s.won, s.created_at, u.name AS player_name
                                     FROM sz_results s
                                     JOIN users u ON u.id = s.user_id
                                     ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// ── 🔗 Kəlmə Zənciri statistikası ────────────────────────────────────────
$kz = ['games' => 0, 'players' => 0, 'top' => 0, 'coins' => 0];
try { $kz['games']   = (int)db()->query("SELECT COUNT(*) FROM kz_scores")->fetchColumn(); } catch (Exception $e) {}
try { $kz['players'] = (int)db()->query("SELECT COUNT(DISTINCT user_id) FROM kz_scores")->fetchColumn(); } catch (Exception $e) {}
try { $kz['top']     = (int)db()->query("SELECT MAX(score) FROM kz_scores")->fetchColumn(); } catch (Exception $e) {}
try { $kz['coins']   = (int)db()->query("SELECT COALESCE(SUM(amount),0) FROM es_coin_tx WHERE type='kelmezencir' AND amount>0")->fetchColumn(); } catch (Exception $e) {}

$recentKzRuns = [];
try {
    $recentKzRuns = db()->query("SELECT s.id, s.score, s.max_chain, s.questions_answered, s.correct_count, s.created_at, u.name AS player_name
                                  FROM kz_scores s
                                  JOIN users u ON u.id = s.user_id
                                  ORDER BY s.id DESC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

$recentPending = [];
try {
    $recentPending = db()->query("SELECT q.id, q.question_az, q.category, q.created_at, u.name AS submitter
                                   FROM es_quiz_questions q
                                   LEFT JOIN users u ON u.id = q.submitted_by
                                   WHERE q.is_active=0 AND q.submitted_by IS NOT NULL
                                   ORDER BY q.id DESC LIMIT 6")->fetchAll();
} catch (Exception $e) {}

function h2($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function diffLabel2($d) { return match ((int)$d) { 1 => '🟢 Asan', 2 => '🟡 Orta', 3 => '🔴 Çətin', default => '—' }; }

$pageActive = 'index';
?>
<!DOCTYPE html><html lang="az" data-theme="dark"><head>
<meta charset="UTF-8"><?= admin_panel_favicon_tag(); ?><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Xülasə – Oyunların Sualları Admin</title>
<script>const t=localStorage.getItem('esevgili_theme')||'light';document.documentElement.setAttribute('data-theme',t);</script>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<style>
.alert-cards{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.alert-card{display:flex;align-items:center;gap:12px;padding:14px 18px;border-radius:14px;flex:1;min-width:200px;cursor:pointer;text-decoration:none;transition:all .2s;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.alert-card:hover{transform:translateY(-2px)}
.alert-card .ac-icon{font-size:1.8rem}
.alert-card .ac-num{font-size:1.6rem;font-weight:900}
.alert-card .ac-lbl{font-size:.72rem;opacity:.85;margin-top:1px}
.qs-card{flex:1;min-width:130px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;text-align:center}
.qs-num{font-size:1.8rem;font-weight:900;color:var(--rose)}
.qs-lbl{font-size:.72rem;color:var(--text2);margin-top:3px}
.quiz-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px}
.tbl-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:20px}
.tbl-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border)}
.tbl-top h3{font-size:.93rem;font-weight:800}
.tbl-top a{font-size:.8rem;color:var(--rose);font-weight:600;text-decoration:none}
.q-list{display:flex;flex-direction:column}
.q-card{border-bottom:1px solid var(--border);padding:12px 18px;display:flex;align-items:center;gap:12px}
.q-card:last-child{border-bottom:none}
.q-card .q-main{flex:1;min-width:0}
.q-card .q-question{font-size:.85rem;font-weight:600;color:var(--text)}
.q-card .q-meta{font-size:.72rem;color:var(--text2);margin-top:2px}
.q-card .q-score{font-size:1.1rem;font-weight:900;color:var(--rose)}
.empty-row{text-align:center;padding:26px;color:var(--text2);font-size:.85rem}
</style>
</head><body>
<?php include __DIR__ . '/oyunchu_sual_sidebar.php'; ?>
<div class="main">

  <div class="topbar">
    <div class="topbar-title">📊 Xülasə</div>
    <div class="topbar-right">
      <button class="theme-btn" onclick="toggleTheme()">🌙</button>
      <span class="admin-chip">⚙ <?=h2($admin['name'])?></span>
      <span class="topbar-date"><?=date('d.m.Y H:i')?></span>
    </div>
  </div>

  <div class="content">

    <?php if ($stats['pending'] > 0): ?>
    <div class="alert-cards">
      <a href="/telesmartadmin/oyunchu/oyunchu_sual_questions.php?filter=pending" class="alert-card" style="background:#fff8e0;border:1px solid #f0d060">
        <div class="ac-icon">📩</div>
        <div><div class="ac-num" style="color:#856404"><?=$stats['pending']?></div><div class="ac-lbl" style="color:#856404">Gözləyən sual təklifi</div></div>
      </a>
    </div>
    <?php endif; ?>

    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num"><?=$stats['questions']?></div><div class="qs-lbl">Ümumi Sual</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--green)"><?=$stats['active']?></div><div class="qs-lbl">Aktiv Sual</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--rose)"><?=$stats['pending']?></div><div class="qs-lbl">Gözləyən Təklif</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">⚔️ Sual Duellosu</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$sd['games']?></div><div class="qs-lbl">Tamamlanan Oyun</div></div>
      <div class="qs-card"><div class="qs-num"><?=$sd['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$sd['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$sd['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">📺 Tele Time</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$tt['games']?></div><div class="qs-lbl">Tamamlanan Oyun</div></div>
      <div class="qs-card"><div class="qs-num"><?=$tt['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$tt['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$tt['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">⛓️ Zəncir</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$zc['games']?></div><div class="qs-lbl">Tamamlanan Qaçış</div></div>
      <div class="qs-card"><div class="qs-num"><?=$zc['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$zc['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$zc['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">💰 Milyonçu</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$mc['games']?></div><div class="qs-lbl">Tamamlanan Oyun</div></div>
      <div class="qs-card"><div class="qs-num"><?=$mc['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$mc['top']?></div><div class="qs-lbl">Ən Yüksək Qazanc</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$mc['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">⚡ Sürət Turu</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$sr['games']?></div><div class="qs-lbl">Tamamlanan Tur</div></div>
      <div class="qs-card"><div class="qs-num"><?=$sr['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$sr['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$sr['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">🔤 Sözü Tap</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num"><?=$sz['words']?></div><div class="qs-lbl">Aktiv Söz</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$sz['games']?></div><div class="qs-lbl">Oynanan Tur</div></div>
      <div class="qs-card"><div class="qs-num" style="color:#27ae60"><?=$sz['won']?></div><div class="qs-lbl">Tapılan Söz</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$sz['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <h3 style="font-size:.85rem;font-weight:800;margin:4px 0 10px;opacity:.8">🔗 Kəlmə Zənciri</h3>
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$kz['games']?></div><div class="qs-lbl">Tamamlanan Qaçış</div></div>
      <div class="qs-card"><div class="qs-num"><?=$kz['players']?></div><div class="qs-lbl">Fərqli Oyunçu</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$kz['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)">🪙<?=$kz['coins']?></div><div class="qs-lbl">Verilmiş Coin</div></div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>⚔️ Sual Duellosu — Son Tamamlanan Oyunlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentGames): ?>
        <div class="empty-row">Hələ tamamlanan oyun yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentGames as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['category'] ?: 'Qarışıq kateqoriya')?> · <?=diffLabel2($g['difficulty'])?></div>
            <div class="q-meta"><?=(int)$g['player_count']?> nəfər · <?=(int)$g['total_rounds']?> raund · kod: <?=h2($g['code'])?> · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?=(int)($g['top_score'] ?? 0)?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>📺 Tele Time — Son Tamamlanan Oyunlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentTtGames): ?>
        <div class="empty-row">Hələ tamamlanan oyun yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentTtGames as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta"><?=h2($g['categories'])?> · <?=(int)$g['boxes_correct']?>/<?=(int)$g['boxes_total']?> düzgün · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?=(int)$g['score']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>⛓️ Zəncir — Son Tamamlanan Qaçışlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentZcRuns): ?>
        <div class="empty-row">Hələ tamamlanan qaçış yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentZcRuns as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta">🔥 ən yaxşı seriya: <?=(int)$g['max_streak']?> · <?=(int)$g['correct_count']?>/<?=(int)$g['questions_answered']?> düzgün · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?=(int)$g['score']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>💰 Milyonçu — Son Tamamlanan Oyunlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentMcRuns): ?>
        <div class="empty-row">Hələ tamamlanan oyun yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentMcRuns as $g):
            $isWin = (int)$g['level_reached'] >= 12 && !$g['walked_away'];
            $tag = $isWin ? '🏆 Tam qələbə' : ($g['walked_away'] ? '💰 Pulu götürdü' : '❌ Yanlış cavab');
        ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta"><?=$tag?> · <?=(int)$g['level_reached']?>-ci pillə · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score">🪙<?=(int)$g['prize']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>⚡ Sürət Turu — Son Tamamlanan Turlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentSrRuns): ?>
        <div class="empty-row">Hələ tamamlanan tur yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentSrRuns as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta">🔥 ən yaxşı kombo: <?=(int)$g['max_streak']?> · <?=(int)$g['correct_count']?>/<?=(int)$g['questions_answered']?> düzgün · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?=(int)$g['score']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>🔤 Sözü Tap — Son Nəticələr</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentSzResults): ?>
        <div class="empty-row">Hələ oynanılmayıb.</div>
        <?php endif; ?>
        <?php foreach ($recentSzResults as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta"><?= $g['won'] ? '✅ ' . (int)$g['guesses'] . ' cəhddə tapdı' : '❌ Tapa bilmədi' ?> · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?= $g['won'] ? '🏆' : '—' ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>🔗 Kəlmə Zənciri — Son Tamamlanan Qaçışlar</h3>
      </div>
      <div class="q-list">
        <?php if (!$recentKzRuns): ?>
        <div class="empty-row">Hələ tamamlanan qaçış yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentKzRuns as $g): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($g['player_name'] ?: 'İstifadəçi')?></div>
            <div class="q-meta">🔗 ən uzun zəncir: <?=(int)$g['max_chain']?> · <?=(int)$g['correct_count']?>/<?=(int)$g['questions_answered']?> düzgün · <?=h2(date('d.m.Y H:i', strtotime($g['created_at'])))?></div>
          </div>
          <div class="q-score"><?=(int)$g['score']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>📩 Son Sual Təklifləri</h3>
        <a href="/telesmartadmin/oyunchu/oyunchu_sual_questions.php?filter=pending">Hamısına bax →</a>
      </div>
      <div class="q-list">
        <?php if (!$recentPending): ?>
        <div class="empty-row">Gözləyən təklif yoxdur.</div>
        <?php endif; ?>
        <?php foreach ($recentPending as $p): ?>
        <div class="q-card">
          <div class="q-main">
            <div class="q-question"><?=h2($p['question_az'])?></div>
            <div class="q-meta"><?=h2($p['category'])?> · Göndərən: <?=h2($p['submitter'] ?? 'naməlum')?> · <?=h2(date('d.m.Y H:i', strtotime($p['created_at'])))?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
<script>
function toggleTheme(){
  const html=document.documentElement;
  const isDark=html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme',isDark?'light':'dark');
  localStorage.setItem('esevgili_theme',isDark?'light':'dark');
}
</script>
</body></html>
