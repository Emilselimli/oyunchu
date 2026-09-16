<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
$admin = requireAdmin();

// DB cədvəlləri yarat (yoxdursa)
try {
    db()->exec("CREATE TABLE IF NOT EXISTS es_quiz_questions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_az TEXT NOT NULL,
        question_tr TEXT DEFAULT NULL,
        question_ru TEXT DEFAULT NULL,
        question_en TEXT DEFAULT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL COMMENT 'a,b,c,d',
        category VARCHAR(60) DEFAULT 'ümumi',
        difficulty TINYINT(1) DEFAULT 1 COMMENT '1=asan,2=orta,3=çətin',
        points SMALLINT DEFAULT 10,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db()->exec("CREATE TABLE IF NOT EXISTS es_quiz_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        score INT DEFAULT 0,
        total_q INT DEFAULT 0,
        correct_q INT DEFAULT 0,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME DEFAULT NULL,
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db()->exec("CREATE TABLE IF NOT EXISTS es_quiz_answers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        question_id INT UNSIGNED NOT NULL,
        chosen CHAR(1) NOT NULL,
        is_correct TINYINT(1) NOT NULL,
        answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// "Sual Duellosu" müstəqil oyunundan gələn istifadəçi təklifləri üçün
// (bax: games/sualduellosu/submit_question.php) — idempotent əlavələr.
try { db()->exec("ALTER TABLE es_quiz_questions ADD COLUMN IF NOT EXISTS submitted_by INT UNSIGNED DEFAULT NULL"); } catch(Exception $e) {}
try { db()->exec("ALTER TABLE es_quiz_questions ADD COLUMN IF NOT EXISTS explanation VARCHAR(500) DEFAULT NULL"); } catch(Exception $e) {}
try { db()->exec("ALTER TABLE es_quiz_questions ADD COLUMN IF NOT EXISTS game_slug VARCHAR(30) DEFAULT NULL"); } catch(Exception $e) {}

$msg = '';
$edit = null;

// --- ƏLAVƏ ET / YENİLƏ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'import_csv') {
        $importMsg = '';
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $importMsg = '❌ Fayl yüklənmədi. Yenidən cəhd edin.';
        } else {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $fh = fopen($tmpPath, 'r');
            if (!$fh) {
                $importMsg = '❌ Fayl açıla bilmədi.';
            } else {
                // UTF-8 BOM-u təmizlə (Excel-də ixrac edilən CSV-lərdə tez-tez olur).
                $bom = fread($fh, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($fh);

                $headerRaw = fgetcsv($fh, 0, ',');
                $header = $headerRaw ? array_map(fn($h) => strtolower(trim($h)), $headerRaw) : [];
                $required = ['question_az', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];
                $missing = array_diff($required, $header);

                if ($missing) {
                    $importMsg = '❌ CSV başlığında bu sütunlar yoxdur: ' . implode(', ', $missing);
                } else {
                    $colIdx = array_flip($header);
                    $ok = 0; $failed = 0; $failReasons = [];
                    $rowNum = 1;
                    $ins = db()->prepare("INSERT INTO es_quiz_questions
                        (question_az, option_a, option_b, option_c, option_d, correct_option, category, difficulty, points, is_active)
                        VALUES (?,?,?,?,?,?,?,?,?,1)");

                    while (($row = fgetcsv($fh, 0, ',')) !== false) {
                        $rowNum++;
                        if (count($row) === 1 && trim($row[0]) === '') continue; // boş sətir

                        $get = fn($key) => isset($colIdx[$key], $row[$colIdx[$key]]) ? trim($row[$colIdx[$key]]) : '';
                        $qaz = $get('question_az');
                        $a = $get('option_a'); $b = $get('option_b'); $c = $get('option_c'); $d = $get('option_d');
                        $correct = strtolower($get('correct_option'));
                        $cat = $get('category') ?: 'ümumi';
                        $diff = (int)($get('difficulty') ?: 1);
                        $diff = max(1, min(3, $diff));
                        $pts = (int)($get('points') ?: 10);
                        if ($pts <= 0) $pts = 10;

                        if ($qaz === '' || $a === '' || $b === '' || $c === '' || $d === '') {
                            $failed++; $failReasons[] = "Sətir $rowNum: boş sahə var"; continue;
                        }
                        if (!in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                            $failed++; $failReasons[] = "Sətir $rowNum: correct_option 'a/b/c/d' olmalıdır (gəldi: '$correct')"; continue;
                        }
                        try {
                            $ins->execute([$qaz, $a, $b, $c, $d, $correct, $cat, $diff, $pts]);
                            $ok++;
                        } catch (Exception $e) {
                            $failed++; $failReasons[] = "Sətir $rowNum: DB xətası";
                        }
                    }
                    fclose($fh);
                    sdAuditLog('quiz_csv_import', 'es_quiz_questions', null, "$ok uğurlu, $failed uğursuz");
                    $importMsg = "✅ $ok sual uğurla idxal olundu.";
                    if ($failed > 0) {
                        $importMsg .= " ⚠️ $failed sətir keçmədi: " . implode('; ', array_slice($failReasons, 0, 5));
                        if (count($failReasons) > 5) $importMsg .= ' və s.';
                    }
                }
            }
        }
        header('Location: /telesmartadmin/oyunchu/oyunchu_sual_questions.php?msg=' . urlencode($importMsg));
        exit;
    }

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $qaz    = trim($_POST['question_az'] ?? '');
        $qtr    = trim($_POST['question_tr'] ?? '');
        $qru    = trim($_POST['question_ru'] ?? '');
        $qen    = trim($_POST['question_en'] ?? '');
        $oa     = trim($_POST['option_a'] ?? '');
        $ob     = trim($_POST['option_b'] ?? '');
        $oc     = trim($_POST['option_c'] ?? '');
        $od     = trim($_POST['option_d'] ?? '');
        $cor    = strtolower(trim($_POST['correct_option'] ?? 'a'));
        $cat    = trim($_POST['category'] ?? 'ümumi');
        $diff   = max(1, min(3, (int)($_POST['difficulty'] ?? 1)));
        $pts    = max(5, min(100, (int)($_POST['points'] ?? 10)));
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (!$qaz || !$oa || !$ob || !$oc || !$od || !in_array($cor, ['a','b','c','d'])) {
            $msg = '❌ Zəruri xanaları doldurun.';
        } else {
            try {
                if ($id) {
                    db()->prepare("UPDATE es_quiz_questions SET
                        question_az=?,question_tr=?,question_ru=?,question_en=?,
                        option_a=?,option_b=?,option_c=?,option_d=?,
                        correct_option=?,category=?,difficulty=?,points=?,is_active=?
                        WHERE id=?")
                        ->execute([$qaz,$qtr,$qru,$qen,$oa,$ob,$oc,$od,$cor,$cat,$diff,$pts,$active,$id]);
                    $msg = '✅ Sual yeniləndi.';
                } else {
                    db()->prepare("INSERT INTO es_quiz_questions
                        (question_az,question_tr,question_ru,question_en,option_a,option_b,option_c,option_d,correct_option,category,difficulty,points,is_active)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$qaz,$qtr,$qru,$qen,$oa,$ob,$oc,$od,$cor,$cat,$diff,$pts,$active]);
                    $msg = '✅ Sual əlavə edildi.';
                }
            } catch(Exception $e) { $msg = '❌ Xəta: ' . $e->getMessage(); }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['del_id'] ?? 0);
        if ($id) {
            try { db()->prepare("DELETE FROM es_quiz_questions WHERE id=?")->execute([$id]); $msg = '🗑 Sual silindi.'; }
            catch(Exception $e) { $msg = '❌ ' . $e->getMessage(); }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['tog_id'] ?? 0);
        if ($id) {
            try { db()->prepare("UPDATE es_quiz_questions SET is_active = 1 - is_active WHERE id=?")->execute([$id]); }
            catch(Exception $e) {}
        }
    }

    if ($action === 'approve') {
        $id = (int)($_POST['app_id'] ?? 0);
        if ($id) {
            try {
                db()->prepare("UPDATE es_quiz_questions SET is_active=1 WHERE id=?")->execute([$id]);
                sdAuditLog('quiz_question_approve', 'es_quiz_questions', $id, 'İstifadəçi təklifi təsdiqləndi');
                $msg = '✅ Təklif təsdiqləndi və oyuna əlavə olundu.';
            } catch(Exception $e) { $msg = '❌ ' . $e->getMessage(); }
        }
    }

    if ($action === 'reject') {
        $id = (int)($_POST['rej_id'] ?? 0);
        if ($id) {
            try {
                // Təhlükəsizlik: yalnız istifadəçi təklifləri bu yolla silinə bilər.
                db()->prepare("DELETE FROM es_quiz_questions WHERE id=? AND submitted_by IS NOT NULL")->execute([$id]);
                sdAuditLog('quiz_question_reject', 'es_quiz_questions', $id, 'İstifadəçi təklifi rədd edildi');
                $msg = '🗑 Təklif rədd edildi.';
            } catch(Exception $e) { $msg = '❌ ' . $e->getMessage(); }
        }
    }

    if ($action === 'merge_duplicates') {
        $keepId = (int)($_POST['keep_id'] ?? 0);
        $allIds = array_map('intval', explode(',', $_POST['group_ids'] ?? ''));
        $deleteIds = array_values(array_diff($allIds, [$keepId]));
        if ($keepId && $deleteIds) {
            try {
                db()->prepare("UPDATE es_quiz_questions SET is_active=1 WHERE id=?")->execute([$keepId]);
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                db()->prepare("DELETE FROM es_quiz_questions WHERE id IN ($placeholders)")->execute($deleteIds);
                sdAuditLog('quiz_duplicates_merge', 'es_quiz_questions', $keepId, 'Silinən ID-lər: ' . implode(',', $deleteIds));
                $msg = '✅ Təkrarlar birləşdirildi — ' . count($deleteIds) . ' surət silindi, #' . $keepId . ' saxlanıldı.';
            } catch(Exception $e) { $msg = '❌ ' . $e->getMessage(); }
        } else {
            $msg = '❌ Saxlanılacaq sual seçilməyib.';
        }
    }

    if ($action === 'merge_all_duplicates') {
        try {
            $groups = sdq_findDuplicateGroups();
            $groupsMerged = 0; $totalDeleted = 0; $allDeletedIds = [];
            foreach ($groups as $group) {
                // Saxlanılacaq: əvvəlcə aktiv olanlar arasından ən kiçik ID, yoxdursa ümumi ən kiçik ID.
                $active = array_filter($group, fn($q) => (int)$q['is_active'] === 1);
                $pool = $active ?: $group;
                usort($pool, fn($a, $b) => $a['id'] <=> $b['id']);
                $keep = $pool[0]['id'];
                $ids = array_column($group, 'id');
                $deleteIds = array_values(array_diff($ids, [$keep]));
                if (!$deleteIds) continue;

                db()->prepare("UPDATE es_quiz_questions SET is_active=1 WHERE id=?")->execute([$keep]);
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                db()->prepare("DELETE FROM es_quiz_questions WHERE id IN ($placeholders)")->execute($deleteIds);

                $groupsMerged++;
                $totalDeleted += count($deleteIds);
                $allDeletedIds = array_merge($allDeletedIds, $deleteIds);
            }
            if ($groupsMerged > 0) {
                sdAuditLog('quiz_duplicates_merge_all', 'es_quiz_questions', null, "$groupsMerged qrup, silinən ID-lər: " . implode(',', $allDeletedIds));
                $msg = "✅ $groupsMerged qrup avtomatik birləşdirildi, ümumi $totalDeleted surət silindi.";
            } else {
                $msg = 'ℹ️ Birləşdiriləcək təkrar tapılmadı.';
            }
        } catch (Exception $e) { $msg = '❌ ' . $e->getMessage(); }
    }

    header('Location: /telesmartadmin/oyunchu/oyunchu_sual_questions.php' . ($msg ? '?msg=' . urlencode($msg) : '')); exit;
}

// URL mesajı
if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Redaktə modu
if (isset($_GET['edit'])) {
    try {
        $edit = db()->prepare("SELECT * FROM es_quiz_questions WHERE id=?")->execute([(int)$_GET['edit']]) ? null : null;
        $st = db()->prepare("SELECT * FROM es_quiz_questions WHERE id=?");
        $st->execute([(int)$_GET['edit']]);
        $edit = $st->fetch();
    } catch(Exception $e) {}
}

// Siyahı
$filter   = $_GET['filter'] ?? 'all';
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$where = '1';
$params = [];
if ($filter === 'active')   { $where = 'q.is_active=1'; }
if ($filter === 'inactive') { $where = 'q.is_active=0 AND q.submitted_by IS NULL'; }
if ($filter === 'pending')  { $where = 'q.is_active=0 AND q.submitted_by IS NOT NULL'; }
if ($search) { $where .= " AND (q.question_az LIKE ? OR q.category LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$duplicateGroups = [];
if ($filter === 'duplicates') {
    $duplicateGroups = sdq_findDuplicateGroups();
    $questions = [];
    $total = 0;
    $totalPages = 1;
} else {
    try {
        $st2 = db()->prepare("SELECT COUNT(*) FROM es_quiz_questions q WHERE $where");
        $st2->execute($params); $total = (int)$st2->fetchColumn();

        $st3 = db()->prepare("SELECT q.*, u.name AS submitter_name FROM es_quiz_questions q
                               LEFT JOIN users u ON u.id = q.submitted_by
                               WHERE $where ORDER BY q.id DESC LIMIT $perPage OFFSET $offset");
        $st3->execute($params); $questions = $st3->fetchAll();
    } catch(Exception $e) { $questions = []; $total = 0; }
    $totalPages = $total ? ceil($total / $perPage) : 1;
}

// Statistika
$qStats = ['total'=>0,'active'=>0,'sessions'=>0,'top'=>0,'pending'=>0,'duplicates'=>0];
try {
    $qStats['total']    = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions")->fetchColumn();
    $qStats['active']   = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions WHERE is_active=1")->fetchColumn();
    $qStats['sessions'] = (int)db()->query("SELECT COUNT(*) FROM es_quiz_sessions")->fetchColumn();
    $qStats['top']      = (int)db()->query("SELECT MAX(score) FROM es_quiz_sessions")->fetchColumn();
    $qStats['pending']  = (int)db()->query("SELECT COUNT(*) FROM es_quiz_questions WHERE is_active=0 AND submitted_by IS NOT NULL")->fetchColumn();
    // Dəqiq (normallaşdırılmış mətn) təkrarların sürətli, ucuz təxmini sayı — tam fuzzy skan yalnız "Təkrarlar" tabına keçəndə işə düşür.
    $qStats['duplicates'] = (int)db()->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(TRAILING '?' FROM TRIM(question_az))) k FROM es_quiz_questions GROUP BY k HAVING COUNT(*)>1) t")->fetchColumn();
} catch(Exception $e) {}

function h2($s){return htmlspecialchars($s??'',ENT_QUOTES,'UTF-8');}

function sdq_normalizeQ(string $t): string {
    $t = mb_strtolower(trim($t), 'UTF-8');
    $t = preg_replace('/[?!.,;:¿¡]+$/u', '', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
}

/** Təkrar/oxşar sualları qruplaşdırır. Dəqiq (normallaşdırılmış) uyğunluq + fuzzy (≥92% mətn OXŞARLIĞI
 *  VƏ variantların da oxşar olması — şablon suallar (məs. "2-nin kvadratı", "3-nin kvadratı") arasında
 *  fərqli ədədlərə görə YANLIŞ təkrar aşkarlanmasının qarşısını almaq üçün). */
function sdq_findDuplicateGroups(): array {
    $all = db()->query("SELECT id, question_az, option_a, option_b, option_c, option_d, correct_option, category, difficulty, is_active, submitted_by, created_at FROM es_quiz_questions ORDER BY id ASC")->fetchAll();
    $norm = [];
    $optSet = [];
    $nums = [];
    foreach ($all as $q) {
        $normQ = sdq_normalizeQ($q['question_az']);
        $norm[$q['id']] = $normQ;
        preg_match_all('/\d+/', $normQ, $m);
        $nums[$q['id']] = $m[0];
        $opts = [sdq_normalizeQ($q['option_a']), sdq_normalizeQ($q['option_b']), sdq_normalizeQ($q['option_c']), sdq_normalizeQ($q['option_d'])];
        sort($opts);
        $optSet[$q['id']] = implode('|', $opts);
    }

    $used = [];
    $groups = [];
    $n = count($all);
    for ($i = 0; $i < $n; $i++) {
        $qi = $all[$i];
        if (isset($used[$qi['id']])) continue;
        $group = [$qi];
        $ni = $norm[$qi['id']];
        $lenI = mb_strlen($ni);
        for ($j = $i + 1; $j < $n; $j++) {
            $qj = $all[$j];
            if (isset($used[$qj['id']])) continue;
            $nj = $norm[$qj['id']];
            $lenJ = mb_strlen($nj);
            if ($lenI === 0 || $lenJ === 0) continue;
            // Qısayol: uzunluqlar çox fərqlidirsə fuzzy yoxlamaya dəyməz.
            if (abs($lenI - $lenJ) / max($lenI, $lenJ) > 0.35) continue;
            // Mətndəki ədədlər (sıra ilə) fərqlidirsə, bu, şablon sualın FƏRQLİ nüsxəsidir
            // (məs. "2-nin kvadratı" / "3-nin kvadratı", ya da "10 ədədinin 50%-i" / "50 ədədinin 10%-i") —
            // real təkrar DEYİL, hətta mətn oxşarlığı yüksək olsa belə.
            if ($nums[$qi['id']] !== $nums[$qj['id']]) continue;
            $isDup = false;
            if ($ni === $nj) {
                $isDup = true;
            } else {
                similar_text($ni, $nj, $pct);
                // Mətn oxşardır (rəqəmsiz şablon halında da, məs. "Rusiyanın paytaxtı..." /
                // "Almaniyanın paytaxtı..."), amma bu fərqli FAKT ola bilər — variantlar da
                // (şəhər adları və s.) DƏQİQ eyni dəst olmalıdır ki, real təkrar sayılsın.
                if ($pct >= 92 && $optSet[$qi['id']] === $optSet[$qj['id']]) $isDup = true;
            }
            if ($isDup) { $group[] = $qj; $used[$qj['id']] = true; }
        }
        if (count($group) > 1) {
            $used[$qi['id']] = true;
            $groups[] = $group;
        }
    }
    return $groups;
}
function diffLabel($d){ return match((int)$d){ 1=>'🟢 Asan', 2=>'🟡 Orta', 3=>'🔴 Çətin', default=>'—' }; }

$pageActive = 'quiz_questions';
?>
<!DOCTYPE html><html lang="az" data-theme="dark"><head>
<meta charset="UTF-8"><?= admin_panel_favicon_tag(); ?><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Oyunların Sualları – Admin</title>
<script>const t=localStorage.getItem('esevgili_theme')||'light';document.documentElement.setAttribute('data-theme',t);</script>
<link rel="stylesheet" href="/telesmartadmin/oyunchu/oyunchu_admin.css">
<style>
.quiz-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px}
.qs-card{flex:1;min-width:130px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;text-align:center}
.qs-num{font-size:1.8rem;font-weight:900;color:var(--rose)}
.qs-lbl{font-size:.72rem;color:var(--text2);margin-top:3px}
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;margin-bottom:22px}
.form-card h3{font-size:.95rem;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.form-row{display:grid;gap:12px;margin-bottom:12px}
.form-row.cols2{grid-template-columns:1fr 1fr}
.form-row.cols4{grid-template-columns:1fr 1fr 1fr 1fr}
.form-row.cols3{grid-template-columns:1fr 1fr 1fr}
label{font-size:.78rem;font-weight:700;color:var(--text2);display:block;margin-bottom:4px}
input[type=text],input[type=number],select,textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;background:var(--input-bg);color:var(--text);font-size:.86rem;outline:none;transition:border .2s}
input:focus,select:focus,textarea:focus{border-color:var(--rose)}
textarea{resize:vertical;min-height:68px}
.opt-correct{border-color:var(--green)!important;background:rgba(39,174,96,.07)!important}
.btn-save{padding:10px 24px;background:var(--rose);color:#fff;border:none;border-radius:10px;font-size:.87rem;font-weight:700;cursor:pointer;transition:opacity .2s}
.btn-save:hover{opacity:.85}
.btn-cancel{padding:10px 18px;background:var(--border);color:var(--text);border:none;border-radius:10px;font-size:.87rem;font-weight:700;cursor:pointer}
.tbl-wrap{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.tbl-top{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px}
.tbl-top h3{font-size:.93rem;font-weight:800}
.search-box{display:flex;gap:8px;align-items:center}
.search-box input{padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--input-bg);color:var(--text);font-size:.82rem;width:200px}
.filter-tabs{display:flex;gap:6px}
.filter-tab{padding:5px 12px;border-radius:20px;font-size:.76rem;font-weight:700;text-decoration:none;color:var(--text2);background:var(--bg);border:1.5px solid var(--border);transition:all .18s}
.filter-tab.act{background:var(--rose);color:#fff;border-color:var(--rose)}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700}
.badge-active{background:#d4f5e2;color:#145a32}
.badge-inactive{background:#fde8e8;color:#7b1818}
.act-btns{display:flex;gap:5px;flex-wrap:wrap}
.btn-edit{padding:5px 10px;background:#e8f4fd;color:#1a5276;border:none;border-radius:7px;font-size:.74rem;font-weight:700;cursor:pointer;text-decoration:none}
.btn-del{padding:5px 10px;background:#fde8e8;color:#7b1818;border:none;border-radius:7px;font-size:.74rem;font-weight:700;cursor:pointer}
.btn-tog{padding:5px 9px;background:var(--badge-bg);color:var(--text);border:none;border-radius:7px;font-size:.74rem;cursor:pointer}
.pagination{display:flex;gap:5px;padding:14px 18px;justify-content:center;border-top:1px solid var(--border);flex-wrap:wrap}
.page-btn{padding:4px 10px;border-radius:7px;font-size:.76rem;font-weight:700;text-decoration:none;color:var(--text2);background:var(--bg);border:1.5px solid var(--border)}
.page-btn.act{background:var(--rose);color:#fff;border-color:var(--rose)}
/* Card layout */
.q-list{display:flex;flex-direction:column;gap:0}
.q-card{border-bottom:1px solid var(--border);padding:10px 16px;transition:background .15s}
.q-card:hover{background:var(--row-hover)}
.q-card:last-child{border-bottom:none}
.q-card-head{display:flex;align-items:flex-start;gap:10px}
.q-num{font-size:.72rem;color:var(--text2);min-width:40px;flex-shrink:0;padding-top:2px}
.q-main{flex:1;min-width:0}
.q-question{font-size:.85rem;font-weight:600;color:var(--text);line-height:1.4;margin-bottom:4px}
.q-answer{font-size:.76rem;color:var(--green);font-weight:700}
.q-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:4px}
.q-actions{display:flex;align-items:center;gap:5px;flex-shrink:0}
.q-variants{display:none;grid-template-columns:1fr 1fr;gap:5px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.q-variants.open{display:grid}
.var-item{padding:6px 10px;border-radius:7px;border:1.5px solid var(--border);font-size:.78rem;color:var(--text);line-height:1.3}
.var-item.ok{border-color:var(--green);background:rgba(39,174,96,.1);color:var(--green);font-weight:700}
.tog-var{background:none;border:1px solid var(--border);border-radius:5px;padding:2px 7px;cursor:pointer;color:var(--text2);font-size:.75rem;transition:all .15s}
.tog-var:hover{border-color:var(--rose);color:var(--rose)}
.msg-bar{padding:12px 18px;border-radius:12px;margin-bottom:18px;font-size:.85rem;font-weight:700;
  background: #d4f5e2; color:#145a32; border:1px solid #a9dfbf}
.msg-bar.err{background:#fde8e8;color:#7b1818;border-color:#f5b7b1}
.correct-mark{display:inline-block;width:20px;height:20px;border-radius:50%;background:var(--green);color:#fff;font-size:.65rem;font-weight:900;text-align:center;line-height:20px}
</style>
</head><body>
<?php include __DIR__ . '/oyunchu_sual_sidebar.php'; ?>
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-title">🎮 Oyunların Sualları</div>
    <div class="topbar-right">
      <button class="theme-btn" onclick="toggleTheme()">🌙</button>
      <span class="admin-chip">⚙ <?=h2($admin['name'])?></span>
    </div>
  </div>

  <div class="content">

    <?php if($msg): ?>
    <div class="msg-bar <?=str_contains($msg,'❌')?'err':''?>"><?=$msg?></div>
    <?php endif ?>

    <!-- Statistika -->
    <div class="quiz-stats">
      <div class="qs-card"><div class="qs-num"><?=$qStats['total']?></div><div class="qs-lbl">Ümumi Sual</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--green)"><?=$qStats['active']?></div><div class="qs-lbl">Aktiv Sual</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--blue)"><?=$qStats['sessions']?></div><div class="qs-lbl">Oyun Sessiyası</div></div>
      <div class="qs-card"><div class="qs-num" style="color:var(--gold)"><?=$qStats['top']?></div><div class="qs-lbl">Ən Yüksək Xal</div></div>
      <div class="qs-card" style="<?=$qStats['pending']>0?'border-color:var(--rose)':''?>"><div class="qs-num" style="color:var(--rose)"><?=$qStats['pending']?></div><div class="qs-lbl">Gözləyən Təklif</div></div>
    </div>

    <!-- CSV İdxal -->
    <div class="form-card">
      <h3>📥 Sualları CSV-dən İdxal Et</h3>
      <div class="field-hint" style="margin-bottom:14px">
        CSV faylının ilk sətri başlıq olmalıdır: <code>question_az,option_a,option_b,option_c,option_d,correct_option,category,difficulty,points</code>.
        Yalnız ilk 6 sütun məcburidir (<code>category</code> boşdursa "ümumi", <code>difficulty</code> boşdursa 1, <code>points</code> boşdursa 10 qəbul olunur).
        <code>correct_option</code> mütləq <code>a</code>, <code>b</code>, <code>c</code> və ya <code>d</code> olmalıdır. İdxal olunan suallar dərhal aktivdir.
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_csv">
        <div class="form-row">
          <input type="file" name="csv_file" accept=".csv" required>
        </div>
        <button type="submit" class="btn-save">📥 İdxal et</button>
        <a href="oyunchu_sual_csv_template.php" class="btn-save" style="background:var(--surface);color:var(--text);border:1.5px solid var(--border);text-decoration:none;display:inline-block;margin-left:8px">⬇️ Nümunə CSV yüklə</a>
      </form>
    </div>

    <!-- Form -->
    <div class="form-card">
      <h3><?=$edit ? '✏️ Sualı Redaktə et' : '➕ Yeni Sual Əlavə et'?></h3>
      <form method="POST">
        <input type="hidden" name="action" value="save">
        <?php if($edit): ?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif ?>

        <div class="form-row">
          <div>
            <label>Sual (Azərbaycanca) *</label>
            <textarea name="question_az" required><?=h2($edit['question_az']??'')?></textarea>
          </div>
        </div>
        <div class="form-row cols3">
          <div>
            <label>Sual (Türkcə)</label>
            <textarea name="question_tr" style="min-height:52px"><?=h2($edit['question_tr']??'')?></textarea>
          </div>
          <div>
            <label>Sual (Rusca)</label>
            <textarea name="question_ru" style="min-height:52px"><?=h2($edit['question_ru']??'')?></textarea>
          </div>
          <div>
            <label>Sual (İngiliscə)</label>
            <textarea name="question_en" style="min-height:52px"><?=h2($edit['question_en']??'')?></textarea>
          </div>
        </div>

        <div class="form-row cols2">
          <div>
            <label>A Variantı *</label>
            <input type="text" name="option_a" value="<?=h2($edit['option_a']??'')?>" required id="opt_a" oninput="highlightOpts()">
          </div>
          <div>
            <label>B Variantı *</label>
            <input type="text" name="option_b" value="<?=h2($edit['option_b']??'')?>" required id="opt_b" oninput="highlightOpts()">
          </div>
        </div>
        <div class="form-row cols2">
          <div>
            <label>C Variantı *</label>
            <input type="text" name="option_c" value="<?=h2($edit['option_c']??'')?>" required id="opt_c" oninput="highlightOpts()">
          </div>
          <div>
            <label>D Variantı *</label>
            <input type="text" name="option_d" value="<?=h2($edit['option_d']??'')?>" required id="opt_d" oninput="highlightOpts()">
          </div>
        </div>

        <div class="form-row cols4">
          <div>
            <label>Düzgün Cavab *</label>
            <select name="correct_option" id="sel_correct" onchange="highlightOpts()">
              <?php foreach(['a','b','c','d'] as $o): ?>
              <option value="<?=$o?>" <?=($edit['correct_option']??'a')===$o?'selected':''?>>
                <?=strtoupper($o)?> variantı
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <div>
            <label>Kateqoriya</label>
            <input type="text" name="category" value="<?=h2($edit['category']??'ümumi')?>" placeholder="məs: tarix, elm">
          </div>
          <div>
            <label>Çətinlik</label>
            <select name="difficulty">
              <option value="1" <?=($edit['difficulty']??1)==1?'selected':''?>>🟢 Asan</option>
              <option value="2" <?=($edit['difficulty']??1)==2?'selected':''?>>🟡 Orta</option>
              <option value="3" <?=($edit['difficulty']??1)==3?'selected':''?>>🔴 Çətin</option>
            </select>
          </div>
          <div>
            <label>Xal</label>
            <input type="number" name="points" value="<?=h2($edit['points']??10)?>" min="5" max="100">
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:18px;margin-top:4px">
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-weight:600;color:var(--text)">
            <input type="checkbox" name="is_active" value="1" <?=($edit===null||$edit['is_active'])?'checked':''?>>
            Aktiv (oyunda görünsün)
          </label>
          <button type="submit" class="btn-save">
            <?=$edit ? '💾 Yadda saxla' : '➕ Əlavə et'?>
          </button>
          <?php if($edit): ?>
          <a href="/telesmartadmin/oyunchu/oyunchu_sual_questions.php" class="btn-cancel">Ləğv et</a>
          <?php endif ?>
        </div>
      </form>
    </div>

    <!-- Siyahı -->
    <div class="tbl-wrap">
      <div class="tbl-top">
        <h3>📋 Suallar (<?=$total?> ədəd)</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <form method="GET" class="search-box">
            <input type="text" name="q" value="<?=h2($search)?>" placeholder="Sual axtar...">
            <input type="hidden" name="filter" value="<?=h2($filter)?>">
            <button type="submit" class="btn-save" style="padding:7px 14px">🔍</button>
          </form>
          <div class="filter-tabs">
            <a href="?filter=all<?=$search?'&q='.urlencode($search):''?>" class="filter-tab <?=$filter==='all'?'act':''?>">Hamısı</a>
            <a href="?filter=active<?=$search?'&q='.urlencode($search):''?>" class="filter-tab <?=$filter==='active'?'act':''?>">Aktiv</a>
            <a href="?filter=inactive<?=$search?'&q='.urlencode($search):''?>" class="filter-tab <?=$filter==='inactive'?'act':''?>">Deaktiv</a>
            <a href="?filter=pending<?=$search?'&q='.urlencode($search):''?>" class="filter-tab <?=$filter==='pending'?'act':''?>" style="<?=$qStats['pending']>0?'border-color:var(--rose);color:var(--rose)':''?>">📩 Təkliflər<?=$qStats['pending']>0?' ('.$qStats['pending'].')':''?></a>
            <a href="?filter=duplicates" class="filter-tab <?=$filter==='duplicates'?'act':''?>" style="<?=$qStats['duplicates']>0?'border-color:#e67e22;color:#e67e22':''?>">🔁 Təkrarlar<?=$qStats['duplicates']>0?' ('.$qStats['duplicates'].'+)':''?></a>
          </div>
        </div>
      </div>

      <?php if ($filter === 'duplicates'): ?>
      <?php if ($duplicateGroups): ?>
      <div class="form-card" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div style="font-size:.82rem;color:var(--text2)">
          <b><?=count($duplicateGroups)?> qrup</b> tapıldı. Avtomatik seçim qaydası: hər qrupda əvvəlcə
          <b>aktiv</b> suallar arasından ən köhnəsi (ən kiçik ID), aktiv yoxdursa ümumi ən köhnəsi saxlanılır.
        </div>
        <form method="POST" onsubmit="return handleConfirmForm(event, this, 'Bütün <?=count($duplicateGroups)?> qrup avtomatik birləşdiriləcək — hər qrupda yalnız 1 sual qalacaq, qalanları HƏMİŞƏLİK silinəcək. Davam edilsin?')">
          <input type="hidden" name="action" value="merge_all_duplicates">
          <button type="submit" class="btn-tog" style="background:rgba(230,126,34,.15);color:#e67e22;white-space:nowrap">🔗 Hamısını birləşdir (<?=count($duplicateGroups)?> qrup)</button>
        </form>
      </div>
      <?php endif; ?>
      <div class="q-list">
        <?php if (!$duplicateGroups): ?>
        <div style="text-align:center;padding:30px;color:var(--text2)">🎉 Heç bir təkrar/oxşar sual tapılmadı.</div>
        <?php endif; ?>
        <?php foreach ($duplicateGroups as $gi => $group): $groupIds = implode(',', array_column($group, 'id')); ?>
        <div class="form-card" style="margin-bottom:14px">
          <div style="font-size:.78rem;font-weight:800;color:#e67e22;margin-bottom:10px">🔁 Qrup <?=$gi+1?> — <?=count($group)?> oxşar sual</div>
          <form method="POST" onsubmit="return handleConfirmForm(event, this, 'Seçilməyən suallar HƏMİŞƏLİK silinəcək. Davam edilsin?')">
            <input type="hidden" name="action" value="merge_duplicates">
            <input type="hidden" name="group_ids" value="<?=h2($groupIds)?>">
            <?php foreach ($group as $qi => $dq): ?>
            <label style="display:block;padding:10px;border:1.5px solid var(--border);border-radius:10px;margin-bottom:8px;cursor:pointer;background:var(--bg)">
              <input type="radio" name="keep_id" value="<?=$dq['id']?>" <?=$qi===0?'checked':''?> style="margin-right:8px">
              <b>#<?=$dq['id']?></b> — <?=h2($dq['question_az'])?>
              <div style="font-size:.72rem;color:var(--text2);margin-top:4px;margin-left:22px">
                A) <?=h2($dq['option_a'])?> · B) <?=h2($dq['option_b'])?> · C) <?=h2($dq['option_c'])?> · D) <?=h2($dq['option_d'])?>
                — Düzgün: <?=strtoupper($dq['correct_option'])?> · <?=h2($dq['category'])?> · <?=$dq['is_active']?'Aktiv':'Deaktiv'?>
                <?php if (!empty($dq['submitted_by'])): ?> · 📩 istifadəçi təklifi<?php endif; ?>
              </div>
            </label>
            <?php endforeach; ?>
            <button type="submit" class="btn-tog" style="background:rgba(230,126,34,.15);color:#e67e22;margin-top:6px">🔗 Seçilən sualı saxla, digərlərini sil</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>

      <div class="q-list">
        <?php if(!$questions): ?>
        <div style="text-align:center;padding:30px;color:var(--text2)">Sual tapılmadı</div>
        <?php endif ?>
        <?php foreach($questions as $q):
          $co = $q['correct_option'];
        ?>
        <div class="q-card">
          <div class="q-card-head">
            <div class="q-num">#<?=$q['id']?></div>
            <div class="q-main">
              <div class="q-question"><?=h2($q['question_az'])?></div>
              <div class="q-answer">✅ <?=strtoupper($co)?>. <?=h2($q['option_'.$co]??'')?></div>
              <div class="q-meta">
                <span class="badge" style="background:var(--badge-bg);color:var(--text);font-size:.67rem"><?=h2($q['category'])?></span>
                <span style="font-size:.72rem;color:var(--text2)"><?=diffLabel($q['difficulty'])?></span>
                <span style="font-size:.72rem;color:var(--gold);font-weight:700"><?=$q['points']?> xal</span>
                <span class="badge <?=$q['is_active']?'badge-active':'badge-inactive'?>" style="font-size:.67rem"><?=$q['is_active']?'Aktiv':'Deaktiv'?></span>
                <?php if (!empty($q['submitted_by'])): ?>
                <span class="badge" style="background:rgba(200,80,106,.12);color:var(--rose);font-size:.67rem">📩 <?=h2($q['submitter_name'] ?? ('İstifadəçi #'.$q['submitted_by']))?> təklifi</span>
                <?php endif; ?>
                <button class="tog-var" onclick="toggleVar(<?=$q['id']?>)" id="vb_<?=$q['id']?>">▾ variantlar</button>
              </div>
              <div class="q-variants" id="vr_<?=$q['id']?>">
                <?php foreach(['a','b','c','d'] as $o): ?>
                <div class="var-item <?=$q['correct_option']===$o?'ok':''?>">
                  <?=$q['correct_option']===$o?'✅ ':''?><strong><?=strtoupper($o)?>.</strong> <?=h2($q['option_'.$o]??' ')?>
                </div>
                <?php endforeach ?>
              </div>
            </div>
            <div class="q-actions">
              <?php if (!empty($q['submitted_by']) && !$q['is_active']): ?>
              <form method="POST" style="display:inline" onsubmit="return handleConfirmForm(event, this, 'Bu təklif təsdiqlənib oyuna əlavə olunsun?')">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="app_id" value="<?=$q['id']?>">
                <button type="submit" class="btn-tog" style="background:rgba(39,174,96,.15);color:var(--green)">✅ Təsdiqlə</button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return handleConfirmForm(event, this, 'Bu təklif rədd edilib silinsin?')">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="rej_id" value="<?=$q['id']?>">
                <button type="submit" class="btn-del">❌ Rədd et</button>
              </form>
              <?php endif; ?>
              <a href="?edit=<?=$q['id']?>" class="btn-edit">✏️</a>
              <form method="POST" style="display:inline" onsubmit="return handleConfirmForm(event, this, 'Statusu dəyişdirilsin?')">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="tog_id" value="<?=$q['id']?>">
                <button type="submit" class="btn-tog"><?=$q['is_active']?'⏸':'▶️'?></button>
              </form>
              <form method="POST" style="display:inline" onsubmit="return handleConfirmForm(event, this, 'Bu sual silinsin?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_id" value="<?=$q['id']?>">
                <button type="submit" class="btn-del">🗑</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach ?>
      </div>

      <?php if($totalPages > 1): ?>
      <div class="pagination">
        <?php
          $url = '?filter='.urlencode($filter).'&q='.urlencode($search).'&p=';
          // Əvvəl düyməsi
          if($page > 1): ?>
          <a href="<?=$url?>1" class="page-btn">«</a>
          <a href="<?=$url.($page-1)?>" class="page-btn">‹</a>
        <?php endif;

          // Göstəriləcək səhifələr
          $show = [];
          $show[] = 1;
          $show[] = 2;
          if($page > 4) $show[] = '...';
          for($i=max(3,$page-2); $i<=min($totalPages-2,$page+2); $i++) $show[]=$i;
          if($page < $totalPages-3) $show[] = '...';
          $show[] = $totalPages-1;
          $show[] = $totalPages;
          $show = array_unique($show);

          foreach($show as $p):
            if($p === '...'):
        ?>
          <span class="page-btn" style="border:none;cursor:default;color:var(--text2)">…</span>
        <?php else: ?>
          <a href="<?=$url.$p?>" class="page-btn <?=$p===$page?'act':''?>"><?=$p?></a>
        <?php endif; endforeach;

          // Sonra düyməsi
          if($page < $totalPages): ?>
          <a href="<?=$url.($page+1)?>" class="page-btn">›</a>
          <a href="<?=$url.$totalPages?>" class="page-btn">»</a>
        <?php endif; ?>
      </div>
      <?php endif ?>
      <?php endif; // filter === 'duplicates' else ?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
function toggleVar(id){
  const vr=document.getElementById('vr_'+id);
  const btn=document.getElementById('vb_'+id);
  const open=vr.classList.toggle('open');
  if(btn)btn.textContent=open?'▴ bağla':'▾ variantlar';
}
// Düzgün variantı vizual vurğula
function highlightOpts(){
  const cor = document.getElementById('sel_correct')?.value;
  ['a','b','c','d'].forEach(o=>{
    const el = document.getElementById('opt_'+o);
    if(!el) return;
    el.classList.toggle('opt-correct', o===cor);
  });
}
highlightOpts();

// Tema
function toggleTheme(){
  const html=document.documentElement;
  const isDark=html.getAttribute('data-theme')==='dark';
  html.setAttribute('data-theme',isDark?'light':'dark');
  localStorage.setItem('esevgili_theme',isDark?'light':'dark');
}
</script>
    <?php include '../confirm_modal.php'; ?>
</body></html>
