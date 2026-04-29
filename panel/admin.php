<?php
session_start();

$db_file = __DIR__ . '/bot_cepat.db';
$pdo = new PDO("sqlite:" . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (kunci TEXT PRIMARY KEY, nilai TEXT)");
$pdo->exec("INSERT OR IGNORE INTO settings (kunci, nilai) VALUES ('ADMIN_PASSWORD', 'rahasia123'), ('NOMOR_ADMIN', '6282219303924'), ('TRIPAY_API_KEY', ''), ('TRIPAY_PRIVATE_KEY', ''), ('TRIPAY_MERCHANT_CODE', ''), ('DIGI_USERNAME', ''), ('DIGI_API_KEY', ''), ('DIGI_WEBHOOK_SECRET', ''), ('BOT_STATUS', 'online')");

try { $pdo->exec("ALTER TABLE produk ADD COLUMN harga_modal INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE transaksi ADD COLUMN profit INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN status TEXT DEFAULT 'Aktif'"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE kategori ADD COLUMN urutan INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE laci ADD COLUMN urutan INTEGER DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE produk ADD COLUMN urutan INTEGER DEFAULT 0"); } catch (Exception $e) {}

if (isset($_POST['action']) && $_POST['action'] == 'update_sort') {
    $table = $_POST['table'];
    $items = $_POST['items'] ?? [];
    $key = ($table == 'produk') ? 'sku' : 'kode';
    if (!empty($items) && in_array($table, ['kategori', 'laci', 'produk'])) {
        $pdo->beginTransaction();
        foreach ($items as $index => $id) {
            $stmt = $pdo->prepare("UPDATE $table SET urutan = ? WHERE $key = ?");
            $stmt->execute([$index, $id]);
        }
        $pdo->commit();
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

$pass_db = $pdo->query("SELECT nilai FROM settings WHERE kunci='ADMIN_PASSWORD'")->fetchColumn();
$PASSWORD_ADMIN = $pass_db ?: "rahasia123";

if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }
if (isset($_POST['login'])) {
    if ($_POST['password'] === $PASSWORD_ADMIN) { $_SESSION['admin_logged_in'] = true; header("Location: admin.php"); exit; }
    else { $error = "Password salah!"; }
}

if (!isset($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · PPOB Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Nunito',sans-serif;min-height:100vh;display:flex;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);}
        .login-side{width:320px;background:linear-gradient(180deg,#2d2d44 0%,#1a1a2e 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 30px;flex-shrink:0;}
        .login-side .logo{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#e91e8c,#ff6b35);display:flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:20px;box-shadow:0 8px 32px rgba(233,30,140,0.4);}
        .login-side h2{color:#fff;font-size:1.4rem;font-weight:800;margin-bottom:6px;text-align:center;}
        .login-side p{color:rgba(255,255,255,0.5);font-size:0.82rem;text-align:center;}
        .login-main{flex:1;display:flex;align-items:center;justify-content:center;padding:40px;}
        .login-card{background:#fff;border-radius:20px;padding:40px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        .login-card h3{font-size:1.5rem;font-weight:800;color:#2d2d44;margin-bottom:6px;}
        .login-card p{color:#999;font-size:0.85rem;margin-bottom:28px;}
        .error-box{background:#fff0f3;border:1px solid #ffcdd5;color:#e91e8c;border-radius:10px;padding:10px 14px;font-size:0.82rem;margin-bottom:20px;}
        label{display:block;font-size:0.78rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;}
        input[type=password],input[type=text]{width:100%;border:2px solid #eee;border-radius:10px;padding:12px 16px;font-family:'Nunito',sans-serif;font-size:0.95rem;color:#333;outline:none;transition:border-color 0.2s;}
        input:focus{border-color:#e91e8c;}
        .btn-login{width:100%;background:linear-gradient(135deg,#e91e8c,#ff6b35);color:#fff;border:none;border-radius:10px;padding:14px;font-family:'Nunito',sans-serif;font-size:1rem;font-weight:800;cursor:pointer;margin-top:20px;transition:opacity 0.2s,transform 0.2s;letter-spacing:0.3px;}
        .btn-login:hover{opacity:0.9;transform:translateY(-1px);}
        @media(max-width:600px){.login-side{display:none;}.login-main{padding:20px;}}
    </style>
</head>
<body>
<div class="login-side">
    <div class="logo">🤖</div>
    <h2>PPOB Admin Panel</h2>
    <p>Sistem Manajemen Bot WhatsApp & PPOB Terintegrasi</p>
</div>
<div class="login-main">
    <div class="login-card">
        <h3>Selamat Datang 👋</h3>
        <p>Masuk untuk mengelola bot dan transaksi</p>
        <?php if(isset($error)): ?><div class="error-box">⚠️ <?= $error ?></div><?php endif; ?>
        <form method="POST">
            <label>Password Admin</label>
            <input type="password" name="password" placeholder="Masukkan password..." required>
            <button type="submit" name="login" class="btn-login">Masuk ke Panel →</button>
        </form>
    </div>
</div>
</body>
</html>
<?php
    exit;
}

// ── Export CSV ──
if (isset($_GET['export_katalog'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Katalog_Produk_'.date('Y-m-d').'.csv');
    $out = fopen('php://output','w');
    fputcsv($out,['SKU','Kode Laci','Nama Produk','Harga Jual','Harga Modal','Deskripsi','Status']);
    foreach($pdo->query("SELECT sku,kategori,nama,harga,harga_modal,deskripsi,status FROM produk ORDER BY kategori")->fetchAll(PDO::FETCH_ASSOC) as $r) fputcsv($out,$r);
    fclose($out); exit;
}

$page = $_GET['page'] ?? 'dashboard';
$msg = ""; $err_msg = "";

// ── Actions ──
if (isset($_POST['update_saldo'])) { $pdo->prepare("UPDATE users SET saldo=? WHERE nomor_wa=?")->execute([$_POST['saldo_baru'],$_POST['nomor_wa']]); header("Location: ?page=pengguna&msg=ok"); exit; }
if (isset($_GET['toggle_ban'])) { $ns=$_GET['status']=='Aktif'?'Banned':'Aktif'; $pdo->prepare("UPDATE users SET status=? WHERE nomor_wa=?")->execute([$ns,$_GET['toggle_ban']]); header("Location: ?page=pengguna&msg=ok"); exit; }
if (isset($_POST['change_password'])) { $pdo->prepare("UPDATE settings SET nilai=? WHERE kunci='ADMIN_PASSWORD'")->execute([$_POST['new_password']]); header("Location: ?page=pengaturan&msg=pwd_ok"); exit; }
if (isset($_POST['edit_trx'])) { $pdo->prepare("UPDATE transaksi SET status=? WHERE invoice=?")->execute([$_POST['status_trx'],$_POST['invoice']]); header("Location: ?page=transaksi&msg=ok"); exit; }
if (isset($_GET['toggle_maint'])) { $pdo->prepare("UPDATE settings SET nilai=? WHERE kunci='BOT_STATUS'")->execute([$_GET['toggle_maint']]); header("Location: ?page=dashboard&msg=status_ok"); exit; }
if (isset($_GET['restart_pm2'])) { $ch=curl_init('http://127.0.0.1:3000/restart'); curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); curl_setopt($ch,CURLOPT_TIMEOUT,2); curl_exec($ch); curl_close($ch); header("Location: ?page=pengaturan&msg=restart_ok"); exit; }

if (isset($_POST['add_kategori'])) { $pdo->prepare("INSERT OR REPLACE INTO kategori(kode,nama) VALUES(?,?)")->execute([$_POST['kode'],$_POST['nama']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_POST['edit_kategori'])) { $pdo->prepare("UPDATE kategori SET nama=? WHERE kode=?")->execute([$_POST['nama'],$_POST['kode_lama']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_GET['del_kategori'])) { $pdo->prepare("DELETE FROM kategori WHERE kode=?")->execute([$_GET['del_kategori']]); header("Location: ?page=katalog&msg=ok"); exit; }

if (isset($_POST['add_laci'])) { $pdo->prepare("INSERT OR REPLACE INTO laci(kode,kategori_kode,nama,tipe_validasi) VALUES(?,?,?,?)")->execute([$_POST['kode'],$_POST['kategori_kode'],$_POST['nama'],$_POST['tipe_validasi']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_POST['edit_laci'])) { $pdo->prepare("UPDATE laci SET nama=?,tipe_validasi=? WHERE kode=?")->execute([$_POST['nama'],$_POST['tipe_validasi'],$_POST['kode_lama']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_GET['del_laci'])) { $pdo->prepare("DELETE FROM laci WHERE kode=?")->execute([$_GET['del_laci']]); header("Location: ?page=katalog&msg=ok"); exit; }

if (isset($_POST['add_product'])) { $pdo->prepare("INSERT OR REPLACE INTO produk(sku,kategori,nama,harga,harga_modal,deskripsi,status) VALUES(?,?,?,?,?,?,?)")->execute([$_POST['sku'],$_POST['kategori'],$_POST['nama'],$_POST['harga'],$_POST['harga_modal'],$_POST['deskripsi'],$_POST['status']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_POST['edit_product'])) { $pdo->prepare("UPDATE produk SET kategori=?,nama=?,harga=?,harga_modal=?,deskripsi=?,status=? WHERE sku=?")->execute([$_POST['kategori'],$_POST['nama'],$_POST['harga'],$_POST['harga_modal'],$_POST['deskripsi'],$_POST['status'],$_POST['sku']]); header("Location: ?page=katalog&msg=ok"); exit; }
if (isset($_GET['del_produk'])) { $pdo->prepare("DELETE FROM produk WHERE sku=?")->execute([$_GET['del_produk']]); header("Location: ?page=katalog&msg=ok"); exit; }

if (isset($_POST['mass_add_sql'])) { try { $pdo->exec($_POST['sql_query']); header("Location: ?page=katalog&msg=ok"); exit; } catch(Exception $e) { $err_msg=$e->getMessage(); } }
if (isset($_POST['mass_add_csv']) && isset($_FILES['file_csv'])) {
    if(($h=fopen($_FILES['file_csv']['tmp_name'],"r"))!==FALSE){
        $st=$pdo->prepare("INSERT OR REPLACE INTO produk(sku,kategori,nama,harga,harga_modal,deskripsi,status) VALUES(?,?,?,?,?,?,?)");
        while(($d=fgetcsv($h,1000,","))!==FALSE){if(count($d)>=7&&$d[0]!='sku')$st->execute($d);}
        fclose($h); header("Location: ?page=katalog&msg=ok"); exit;
    }
}
if (isset($_POST['save_settings'])) { foreach($_POST['settings'] as $k=>$v) $pdo->prepare("UPDATE settings SET nilai=? WHERE kunci=?")->execute([$v,$k]); header("Location: ?page=pengaturan&msg=ok"); exit; }

if(isset($_GET['msg'])){
    if($_GET['msg']=='ok') $msg="Perubahan berhasil disimpan!";
    if($_GET['msg']=='status_ok') $msg="Status bot berhasil diperbarui.";
    if($_GET['msg']=='restart_ok') $msg="Perintah restart terkirim.";
    if($_GET['msg']=='pwd_ok') $msg="Password admin berhasil diubah.";
}

$total_trx    = $pdo->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
$total_users  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_saldo  = $pdo->query("SELECT SUM(saldo) FROM users")->fetchColumn() ?: 0;
$total_produk = $pdo->query("SELECT COUNT(*) FROM produk")->fetchColumn();
$bot_status   = $pdo->query("SELECT nilai FROM settings WHERE kunci='BOT_STATUS'")->fetchColumn() ?: 'online';
$total_profit = $pdo->query("SELECT SUM(profit) FROM transaksi WHERE status='Sukses'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PPOB Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<style>
/* ═══════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --sidebar-bg: #2d2d44;
    --sidebar-w: 230px;
    --accent-pink: #e91e8c;
    --accent-orange: #ff6b35;
    --accent-teal: #00c9a7;
    --accent-blue: #4361ee;
    --accent-purple: #7b2ff7;
    --accent-yellow: #ffd166;
    --body-bg: #f5f6fa;
    --white: #ffffff;
    --text-dark: #2d2d44;
    --text-mid: #6b7280;
    --text-light: #9ca3af;
    --border: #e5e7eb;
    --shadow: 0 4px 20px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
    --radius: 14px;
    --radius-sm: 8px;
}

body {
    font-family: 'Nunito', sans-serif;
    background: var(--body-bg);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
    overflow-x: hidden;
}

/* ═══════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar-bg);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 200;
    transition: transform 0.3s ease;
    overflow-y: auto;
    overflow-x: hidden;
}
.sidebar::-webkit-scrollbar { width: 0; }

.sidebar-header {
    padding: 28px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-pink), var(--accent-orange));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(233,30,140,0.35);
}
.sidebar-brand { color: #fff; }
.sidebar-brand strong { display: block; font-size: 0.95rem; font-weight: 800; line-height: 1.2; }
.sidebar-brand span { font-size: 0.72rem; color: rgba(255,255,255,0.45); font-weight: 600; }

.nav-section-label {
    padding: 18px 20px 6px;
    font-size: 0.68rem;
    font-weight: 800;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 1.2px;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 20px;
    color: rgba(255,255,255,0.55);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 700;
    transition: all 0.2s;
    position: relative;
    margin: 1px 10px;
    border-radius: 10px;
}
.nav-link:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.85); }
.nav-link.active {
    background: linear-gradient(135deg, var(--accent-pink), #c0136c);
    color: #fff;
    box-shadow: 0 4px 14px rgba(233,30,140,0.4);
}
.nav-icon { font-size: 1rem; width: 22px; text-align: center; }
.sidebar-footer {
    margin-top: auto;
    padding: 16px 10px;
    border-top: 1px solid rgba(255,255,255,0.07);
}
.btn-logout {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 11px 20px;
    background: rgba(233,30,140,0.12);
    border: 1px solid rgba(233,30,140,0.25);
    color: #ff6b9d;
    border-radius: 10px;
    font-family: 'Nunito',sans-serif;
    font-size: 0.875rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    transition: background 0.2s;
}
.btn-logout:hover { background: rgba(233,30,140,0.22); }

/* ═══════════════════════════════════════
   TOPBAR (Mobile)
═══════════════════════════════════════ */
.topbar {
    display: none;
    position: fixed; top: 0; left: 0; right: 0;
    height: 58px;
    background: var(--white);
    border-bottom: 1px solid var(--border);
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    z-index: 300;
    box-shadow: var(--shadow);
}
.topbar-brand { font-weight: 900; font-size: 1.1rem; color: var(--accent-pink); }
.hamburger {
    background: var(--body-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 7px 12px;
    cursor: pointer;
    font-size: 1.1rem;
    color: var(--text-dark);
    transition: all 0.2s;
    min-width: 40px;
    text-align: center;
    line-height: 1;
}
.hamburger:hover { background: var(--border); }

/* ═══════════════════════════════════════
   MAIN LAYOUT
═══════════════════════════════════════ */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-width: 0; }
.content { padding: 28px 28px; flex: 1; }

/* ═══════════════════════════════════════
   PAGE HEADER
═══════════════════════════════════════ */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.page-title { font-size: 1.5rem; font-weight: 900; color: var(--text-dark); }
.page-title small { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-mid); margin-top: 2px; }
.breadcrumb { font-size: 0.78rem; color: var(--text-light); font-weight: 600; }
.breadcrumb span { color: var(--accent-pink); }

/* ═══════════════════════════════════════
   ALERTS
═══════════════════════════════════════ */
.alert {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 16px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}
.alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
.alert-danger  { background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
.alert-info    { background: #ede9fe; border-left: 4px solid var(--accent-purple); color: #4c1d95; }
.alert-close { margin-left: auto; background: none; border: none; cursor: pointer; color: inherit; opacity: 0.5; font-size: 1.1rem; }
.alert-close:hover { opacity: 1; }

/* ═══════════════════════════════════════
   CARDS
═══════════════════════════════════════ */
.card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    font-weight: 800;
    font-size: 0.875rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.card-body { padding: 20px; }

/* ═══════════════════════════════════════
   STAT CARDS (Dashboard)
═══════════════════════════════════════ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}
.stat-card {
    border-radius: var(--radius);
    padding: 22px 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.stat-card::after {
    content: '';
    position: absolute;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.12);
    bottom: -30px; right: -20px;
}
.stat-card::before {
    content: '';
    position: absolute;
    width: 60px; height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    top: -15px; right: 40px;
}
.stat-card.pink    { background: linear-gradient(135deg, #e91e8c, #c0136c); }
.stat-card.blue    { background: linear-gradient(135deg, #4361ee, #3a0ca3); }
.stat-card.teal    { background: linear-gradient(135deg, #00c9a7, #00a388); }
.stat-card.orange  { background: linear-gradient(135deg, #ff6b35, #e55a2b); }
.stat-card.purple  { background: linear-gradient(135deg, #7b2ff7, #5a189a); }
.stat-label { font-size: 0.75rem; font-weight: 800; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
.stat-val { font-size: 1.9rem; font-weight: 900; line-height: 1; margin-bottom: 8px; }
.stat-sub { font-size: 0.75rem; opacity: 0.75; font-weight: 600; }
.stat-icon { position: absolute; top: 18px; right: 18px; font-size: 1.8rem; opacity: 0.25; }

.profit-banner {
    background: linear-gradient(135deg, #2d2d44, #1a1a2e);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-lg);
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    overflow: hidden;
}
.profit-banner::before {
    content: '';
    position: absolute;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(233,30,140,0.15), rgba(255,107,53,0.1));
    right: -40px; top: -60px;
}
.profit-banner-left small { font-size: 0.75rem; color: rgba(255,255,255,0.5); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; }
.profit-banner-left .val { font-size: 2.4rem; font-weight: 900; color: #fff; margin: 4px 0; letter-spacing: -1px; }
.profit-badge { background: rgba(0,201,167,0.15); border: 1px solid rgba(0,201,167,0.3); color: #00c9a7; padding: 6px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 800; }

/* Bot Status Card */
.bot-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 18px 22px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow);
    flex-wrap: wrap;
    gap: 14px;
}
.bot-status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; flex-shrink: 0; }
.dot-online  { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.25); animation: pulse 2s infinite; }
.dot-offline { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.25); }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 3px rgba(16,185,129,0.25)} 50%{box-shadow:0 0 0 6px rgba(16,185,129,0.1)} }
.bot-info strong { font-size: 0.95rem; font-weight: 800; color: var(--text-dark); }
.bot-info small { display: block; font-size: 0.78rem; color: var(--text-mid); font-weight: 600; }

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    font-family: 'Nunito',sans-serif;
    font-size: 0.84rem; font-weight: 800;
    cursor: pointer; border: none; text-decoration: none;
    transition: all 0.2s; white-space: nowrap;
}
.btn-pink    { background: linear-gradient(135deg, #e91e8c, #c0136c); color: #fff; box-shadow: 0 4px 14px rgba(233,30,140,0.35); }
.btn-pink:hover { box-shadow: 0 6px 20px rgba(233,30,140,0.45); transform: translateY(-1px); }
.btn-blue    { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: #fff; box-shadow: 0 4px 14px rgba(67,97,238,0.35); }
.btn-blue:hover { transform: translateY(-1px); }
.btn-teal    { background: linear-gradient(135deg, #00c9a7, #00a388); color: #fff; box-shadow: 0 4px 14px rgba(0,201,167,0.3); }
.btn-orange  { background: linear-gradient(135deg, #ff6b35, #e55a2b); color: #fff; box-shadow: 0 4px 14px rgba(255,107,53,0.3); }
.btn-outline { background: var(--white); border: 2px solid var(--border); color: var(--text-dark); }
.btn-outline:hover { border-color: var(--accent-pink); color: var(--accent-pink); }
.btn-ghost   { background: var(--body-bg); color: var(--text-mid); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--border); color: var(--text-dark); }
.btn-sm  { padding: 6px 13px; font-size: 0.78rem; }
.btn-xs  { padding: 4px 10px; font-size: 0.74rem; border-radius: 6px; }

/* Danger */
.btn-danger-soft { background: #fee2e2; color: #dc2626; border: none; }
.btn-danger-soft:hover { background: #fecaca; }
.btn-success-soft { background: #d1fae5; color: #059669; border: none; }
.btn-success-soft:hover { background: #a7f3d0; }
.btn-warn-soft { background: #fef3c7; color: #d97706; border: none; }
.btn-warn-soft:hover { background: #fde68a; }

/* Toggle buttons */
.btn-toggle-on {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff; border: none;
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-family: 'Nunito',sans-serif; font-size: 0.875rem; font-weight: 800;
    cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    transition: all 0.2s;
}
.btn-toggle-on:hover { transform: translateY(-1px); }
.btn-toggle-off {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff; border: none;
    padding: 10px 22px; border-radius: var(--radius-sm);
    font-family: 'Nunito',sans-serif; font-size: 0.875rem; font-weight: 800;
    cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    transition: all 0.2s;
}
.btn-toggle-off:hover { transform: translateY(-1px); }

/* ═══════════════════════════════════════
   TABLE
═══════════════════════════════════════ */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
thead th {
    padding: 11px 16px;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text-mid);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    background: #f9fafb;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
tbody td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text-dark); }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #fafafa; }

/* ═══════════════════════════════════════
   BADGES
═══════════════════════════════════════ */
.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.3px; }
.badge-success { background: #d1fae5; color: #059669; }
.badge-danger  { background: #fee2e2; color: #dc2626; }
.badge-warning { background: #fef3c7; color: #d97706; }
.badge-blue    { background: #dbeafe; color: #2563eb; }
.badge-purple  { background: #ede9fe; color: #7c3aed; }

/* ═══════════════════════════════════════
   FORMS
═══════════════════════════════════════ */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.78rem; font-weight: 800; color: var(--text-mid); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 7px; }
.form-control {
    width: 100%;
    background: var(--body-bg);
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    color: var(--text-dark);
    font-family: 'Nunito',sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus { border-color: var(--accent-pink); box-shadow: 0 0 0 3px rgba(233,30,140,0.1); background: #fff; }
.form-control::placeholder { color: var(--text-light); font-weight: 600; }
select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath fill='%236b7280' d='M6 8L0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
textarea.form-control { resize: vertical; min-height: 80px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.section-title { font-size: 0.8rem; font-weight: 800; color: var(--text-mid); text-transform: uppercase; letter-spacing: 0.8px; margin: 24px 0 14px; padding-bottom: 8px; border-bottom: 2px solid var(--border); }
.section-title.pink { border-bottom-color: var(--accent-pink); color: var(--accent-pink); }
.section-title.blue { border-bottom-color: var(--accent-blue); color: var(--accent-blue); }
.section-title.orange { border-bottom-color: var(--accent-orange); color: var(--accent-orange); }

/* ═══════════════════════════════════════
   KATALOG
═══════════════════════════════════════ */
.drag-handle { cursor: grab; color: var(--text-light); padding: 4px 8px; transition: color 0.2s; font-size: 1rem; }
.drag-handle:hover { color: var(--accent-pink); }
.drag-handle:active { cursor: grabbing; }
.sortable-ghost   { opacity: 0; }
.sortable-chosen  { box-shadow: 0 6px 20px rgba(0,0,0,0.12); border-radius: var(--radius-sm); }
.sortable-drag    { opacity: 1; box-shadow: 0 12px 32px rgba(0,0,0,0.15); z-index: 9999; }
#sortable-kategori,.kategori-item,.laci-item,.sortable-produk,.sortable-produk-mobile { -webkit-user-select:none; user-select:none; }

.kat-row {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: box-shadow 0.2s;
}
.kat-row:hover { box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
.kat-label { font-weight: 800; font-size: 0.9rem; color: var(--text-dark); display: flex; align-items: center; gap: 8px; cursor: pointer; flex: 1; }
.kat-badge { background: var(--sidebar-bg); color: rgba(255,255,255,0.7); font-size: 0.65rem; font-weight: 800; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

.laci-wrap { padding-left: 24px; margin-bottom: 6px; }
.laci-row {
    background: #f9fafb;
    border: 1px solid var(--border);
    border-left: 3px solid var(--accent-pink);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    margin-bottom: 6px;
    display: flex; align-items: center; justify-content: space-between;
}
.laci-label { font-weight: 800; font-size: 0.85rem; color: var(--accent-pink); display: flex; align-items: center; gap: 8px; cursor: pointer; flex: 1; }

.produk-wrap { padding-left: 24px; margin-bottom: 8px; }
.produk-tip { background: #ede9fe; border-radius: var(--radius-sm); padding: 8px 12px; font-size: 0.78rem; font-weight: 700; color: #7c3aed; margin-bottom: 10px; }
.add-kat-row {
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    text-align: center;
    color: var(--text-mid);
    cursor: pointer;
    font-weight: 700; font-size: 0.875rem;
    transition: all 0.2s;
    margin-top: 8px;
}
.add-kat-row:hover { border-color: var(--accent-pink); color: var(--accent-pink); background: #fff0f7; }

.action-group { display: flex; gap: 5px; }
.collapse-content { display: none; }
.collapse-content.open { display: block; }

/* ═══════════════════════════════════════
   MEMBER/TRX MOBILE CARDS
═══════════════════════════════════════ */
.list-card {
    background: var(--white);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    margin-bottom: 10px;
    box-shadow: var(--shadow);
    border-left: 4px solid var(--border);
}
.list-card.green  { border-left-color: #10b981; }
.list-card.red    { border-left-color: #ef4444; }
.list-card.yellow { border-left-color: #f59e0b; }

/* ═══════════════════════════════════════
   TOAST
═══════════════════════════════════════ */
#toast {
    position: fixed; bottom: 24px; right: 24px;
    background: var(--text-dark);
    color: #fff;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 0.875rem; font-weight: 700;
    box-shadow: var(--shadow-lg);
    opacity: 0; transform: translateY(8px);
    transition: all 0.3s;
    z-index: 9999; pointer-events: none;
    display: flex; align-items: center; gap: 8px;
}
#toast.show { opacity: 1; transform: translateY(0); }
#toast span:first-child { background: #10b981; color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; flex-shrink: 0; }

/* ═══════════════════════════════════════
   MODAL
═══════════════════════════════════════ */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 500;
    align-items: center; justify-content: center;
    padding: 16px;
    backdrop-filter: blur(3px);
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--white);
    border-radius: 18px;
    width: 100%; max-width: 480px;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 30px 80px rgba(0,0,0,0.2);
    animation: mIn 0.22s ease;
}
.modal-box.lg { max-width: 620px; }
@keyframes mIn { from{opacity:0;transform:scale(0.94) translateY(12px)} to{opacity:1;transform:none} }
.modal-header {
    padding: 20px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.modal-title { font-weight: 900; font-size: 1rem; color: var(--text-dark); }
.modal-close { background: var(--body-bg); border: 1px solid var(--border); border-radius: 8px; color: var(--text-mid); cursor: pointer; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.15s; }
.modal-close:hover { background: #fee2e2; color: #dc2626; border-color: #fecaca; }
.modal-body { padding: 22px; }
.modal-footer { padding: 16px 22px; border-top: 1px solid var(--border); display: flex; gap: 10px; }
.modal-footer .btn { flex: 1; justify-content: center; }

/* ═══════════════════════════════════════
   TABS
═══════════════════════════════════════ */
.tab-nav { display: flex; gap: 4px; background: var(--body-bg); border-radius: var(--radius-sm); padding: 4px; margin-bottom: 18px; }
.tab-btn { flex: 1; padding: 9px; border: none; background: none; font-family:'Nunito',sans-serif; font-size: 0.84rem; font-weight: 800; color: var(--text-mid); border-radius: 7px; cursor: pointer; transition: all 0.15s; }
.tab-btn.active { background: var(--white); color: var(--accent-pink); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ═══════════════════════════════════════
   SETTINGS
═══════════════════════════════════════ */
.settings-grid { display: grid; grid-template-columns: 1fr 340px; gap: 22px; align-items: start; }
.danger-zone { background: #fff5f5; border: 2px solid #fecaca; border-radius: var(--radius); padding: 20px; }
.danger-zone h4 { color: #dc2626; font-size: 0.9rem; font-weight: 900; margin-bottom: 8px; }
.danger-zone p { font-size: 0.8rem; color: #9ca3af; font-weight: 600; margin-bottom: 16px; }

/* ═══════════════════════════════════════
   LOG
═══════════════════════════════════════ */
.log-box {
    background: #1a1a2e;
    border-radius: var(--radius);
    padding: 20px;
    height: 500px; overflow: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.8rem; color: #00c9a7; line-height: 1.7;
}

/* ═══════════════════════════════════════
   SIDEBAR OVERLAY
═══════════════════════════════════════ */
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 310; /* di atas topbar (300) */
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}
.sidebar-overlay.active { display: block; }

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media (max-width: 768px) {
    /* Sidebar di kanan, muncul dari kanan, z-index di atas overlay */
    .sidebar {
        left: auto;
        right: 0;
        top: 0;
        bottom: 0;
        width: 280px;
        transform: translateX(100%);   /* tersembunyi ke kanan */
        z-index: 320;                  /* paling atas */
        box-shadow: none;
        border-radius: 0;
    }
    .sidebar.open {
        transform: translateX(0);      /* muncul dari kanan */
        box-shadow: -8px 0 40px rgba(0,0,0,0.35);
    }
    .topbar {
        display: flex;
        z-index: 300; /* topbar di bawah overlay & sidebar */
    }
    .main { margin-left: 0; }
    .content { padding: 16px; padding-top: 74px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
    .stat-val { font-size: 1.5rem; }
    .form-row { grid-template-columns: 1fr; }
    .settings-grid { grid-template-columns: 1fr; }
    .d-mob-none { display: none !important; }
}
@media (min-width: 769px) {
    .d-desk-none { display: none !important; }
    /* Desktop: sidebar tetap di kiri */
    .sidebar {
        left: 0;
        right: auto;
        transform: none !important;
    }
}
</style>
</head>
<body>

<div id="toast"><span>✓</span><span id="toast-msg">Tersimpan</span></div>
<div class="sidebar-overlay" id="sOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-avatar">🤖</div>
        <div class="sidebar-brand">
            <strong>PPOB Panel</strong>
            <span>Admin Dashboard</span>
        </div>
    </div>
    <nav>
        <div class="nav-section-label">Menu Utama</div>
        <a href="?page=dashboard"  class="nav-link <?= $page=='dashboard'?'active':'' ?>" onclick="closeSidebar()"><span class="nav-icon">📊</span> Dashboard</a>
        <a href="?page=katalog"    class="nav-link <?= $page=='katalog'?'active':'' ?>"   onclick="closeSidebar()"><span class="nav-icon">📦</span> Katalog Produk</a>
        <a href="?page=transaksi"  class="nav-link <?= $page=='transaksi'?'active':'' ?>" onclick="closeSidebar()"><span class="nav-icon">🛒</span> Transaksi</a>
        <a href="?page=pengguna"   class="nav-link <?= $page=='pengguna'?'active':'' ?>"  onclick="closeSidebar()"><span class="nav-icon">👥</span> Daftar Member</a>
        <div class="nav-section-label">Sistem</div>
        <a href="?page=pengaturan" class="nav-link <?= $page=='pengaturan'?'active':'' ?>" onclick="closeSidebar()"><span class="nav-icon">⚙️</span> Pengaturan</a>
        <a href="?page=log"        class="nav-link <?= $page=='log'?'active':'' ?>"        onclick="closeSidebar()"><span class="nav-icon">📝</span> Log Sistem</a>
    </nav>
    <div class="sidebar-footer">
        <a href="?logout=true" class="btn-logout">
            <span>🚪</span> Keluar
        </a>
    </div>
</aside>

<div class="topbar">
    <div class="topbar-brand">🤖 PPOB</div>
    <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()">☰</button>
</div>

<main class="main">
<div class="content">

<?php if($msg): ?>
<div class="alert alert-success">✅ <?= $msg ?><button class="alert-close" onclick="this.parentElement.remove()">×</button></div>
<?php endif; ?>
<?php if($err_msg): ?>
<div class="alert alert-danger">⚠️ <?= $err_msg ?><button class="alert-close" onclick="this.parentElement.remove()">×</button></div>
<?php endif; ?>

<?php if($page=='dashboard'): ?>
<div class="page-header">
    <div class="page-title">Dashboard <br><small>Ringkasan sistem bot PPOB Anda</small></div>
    <div class="breadcrumb">Panel / <span>Dashboard</span></div>
</div>

<div class="bot-card">
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="bot-status-dot <?= $bot_status=='online'?'dot-online':'dot-offline' ?>"></span>
        <div class="bot-info">
            <strong>Status Bot: <?= strtoupper($bot_status) ?></strong>
            <small>Ubah mode operasional bot WhatsApp Anda</small>
        </div>
    </div>
    <?php if($bot_status=='online'): ?>
    <a href="#" onclick="event.preventDefault();showConfirm('?toggle_maint=maintenance','Bot akan diubah ke mode Sibuk. Lanjutkan?')" class="btn-toggle-on">⏸ Set ke Sibuk</a>
    <?php else: ?>
    <a href="#" onclick="event.preventDefault();showConfirm('?toggle_maint=online','Bot akan diaktifkan. Lanjutkan?')" class="btn-toggle-off">▶ Aktifkan Bot</a>
    <?php endif; ?>
</div>

<div class="profit-banner">
    <div class="profit-banner-left">
        <small>💰 Total Laba Bersih Keseluruhan</small>
        <div class="val">Rp <?= number_format($total_profit,0,',','.') ?></div>
        <span class="profit-badge">✓ Sukses</span>
    </div>
    <div style="font-size:3rem;opacity:0.15;position:relative;z-index:1;">💹</div>
</div>

<div class="stats-grid">
    <div class="stat-card pink">
        <div class="stat-icon">🛒</div>
        <div class="stat-label">Total Transaksi</div>
        <div class="stat-val"><?= number_format($total_trx) ?></div>
        <div class="stat-sub">Semua transaksi</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon">👥</div>
        <div class="stat-label">Total Member</div>
        <div class="stat-val"><?= number_format($total_users) ?></div>
        <div class="stat-sub">Pengguna terdaftar</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon">💳</div>
        <div class="stat-label">Saldo Mengendap</div>
        <div class="stat-val" style="font-size:1.3rem;">Rp<?= number_format($total_saldo,0,',','.') ?></div>
        <div class="stat-sub">Saldo semua member</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon">📦</div>
        <div class="stat-label">Produk Aktif</div>
        <div class="stat-val"><?= number_format($total_produk) ?></div>
        <div class="stat-sub">Produk di katalog</div>
    </div>
</div>

<?php elseif($page=='katalog'):
    $kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY urutan ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="page-header">
    <div class="page-title">Katalog Produk <br><small>Kelola kategori, laci, dan produk Anda</small></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="?export_katalog=true" class="btn btn-teal btn-sm" target="_blank">📥 Export CSV</a>
        <button class="btn btn-outline btn-sm" onclick="openM('mMassAdd')">⚡ Massal</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        Struktur Katalog
        <small style="font-weight:600;color:var(--text-light);">Tahan ☰ untuk drag & drop urutan</small>
    </div>
    <div class="card-body">
        <?php if(!count($kategori_list)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-light);font-weight:700;">Belum ada kategori. Buat yang pertama!</div>
        <?php endif; ?>

        <div class="produk-tip">💡 Klik nama untuk buka isi. Drag handle <b>☰</b> untuk atur urutan.</div>

        <div id="sortable-kategori">
        <?php foreach($kategori_list as $kat):
            $sk = preg_replace('/[^a-zA-Z0-9]/','_',$kat['kode']);
        ?>
        <div class="kategori-item" data-kode="<?= $kat['kode'] ?>">
            <div class="kat-row">
                <div class="kat-label" onclick="toggleC('kat-<?= $sk ?>')">
                    <span class="drag-handle" onclick="event.stopPropagation()">☰</span>
                    <span>📁</span>
                    <?= htmlspecialchars($kat['nama']) ?>
                    <span class="kat-badge" style="display:none;"><?= $kat['kode'] ?></span>
                </div>
                <div class="action-group">
                    <button class="btn btn-teal btn-xs" onclick="siapkanLaci('<?= $kat['kode'] ?>','<?= addslashes($kat['nama']) ?>');openM('mAddLaci')">+</button>
                    <button class="btn btn-warn-soft btn-xs" onclick="editKat('<?= $kat['kode'] ?>','<?= addslashes($kat['nama']) ?>');openM('mEditKat')">✏️</button>
                    <button class="btn btn-danger-soft btn-xs" onclick="showConfirm('?del_kategori=<?= $kat['kode'] ?>','Hapus kategori ini beserta semua isinya?')">✕</button>
                </div>
            </div>

            <div id="kat-<?= $sk ?>" class="collapse-content">
                <?php $laci_list = $pdo->query("SELECT * FROM laci WHERE kategori_kode='{$kat['kode']}' ORDER BY urutan ASC, nama ASC")->fetchAll(); ?>
                <div class="laci-wrap sortable-laci">
                <?php foreach($laci_list as $lac):
                    $sl = preg_replace('/[^a-zA-Z0-9]/','_',$lac['kode']);
                ?>
                <div class="laci-item" data-kode="<?= $lac['kode'] ?>">
                    <div class="laci-row">
                        <div class="laci-label" onclick="toggleC('lac-<?= $sl ?>')">
                            <span class="drag-handle" onclick="event.stopPropagation()">☰</span>
                            🗂
                            <?= htmlspecialchars($lac['nama']) ?>
                            <span class="badge badge-purple" style="display:none; font-size:.65rem;"><?= $lac['tipe_validasi'] ?></span>
                        </div>
                        <div class="action-group">
                            <button class="btn btn-pink btn-xs" onclick="siapkanProduk('<?= $lac['kode'] ?>','<?= addslashes($lac['nama']) ?>');openM('mAddProduk')">+</button>
                            <button class="btn btn-warn-soft btn-xs" onclick="editLaci('<?= $lac['kode'] ?>','<?= addslashes($lac['nama']) ?>','<?= $lac['tipe_validasi'] ?>');openM('mEditLaci')">✏️</button>
                            <button class="btn btn-danger-soft btn-xs" onclick="showConfirm('?del_laci=<?= $lac['kode'] ?>','Hapus laci ini?')">✕</button>
                        </div>
                    </div>

                    <div id="lac-<?= $sl ?>" class="collapse-content">
                        <?php $prods = $pdo->query("SELECT * FROM produk WHERE kategori='{$lac['kode']}' ORDER BY urutan ASC, harga ASC")->fetchAll(); ?>
                        <div class="produk-wrap">
                            <div class="table-wrap d-mob-none" style="background:#fff;border-radius:8px;border:1px solid var(--border);overflow:hidden;">
                                <table>
                                    <thead><tr>
                                        <th style="width:30px;"></th>
                                        <th>SKU</th><th>Nama Produk</th>
                                        <th>Modal</th><th>Jual</th><th>Laba</th>
                                        <th>Status</th><th style="text-align:right;">Aksi</th>
                                    </tr></thead>
                                    <tbody class="sortable-produk">
                                    <?php foreach($prods as $p): $unt=$p['harga']-$p['harga_modal']; ?>
                                    <tr data-sku="<?= $p['sku'] ?>">
                                        <td><span class="drag-handle">☰</span></td>
                                        <td><code style="background:#f3f4f6;padding:2px 7px;border-radius:4px;font-size:.75rem;color:#374151;"><?= $p['sku'] ?></code></td>
                                        <td style="font-weight:700;"><?= $p['nama'] ?></td>
                                        <td style="color:var(--text-mid);font-size:.82rem;">Rp<?= number_format($p['harga_modal'],0,',','.') ?></td>
                                        <td style="color:var(--accent-blue);font-weight:700;">Rp<?= number_format($p['harga'],0,',','.') ?></td>
                                        <td style="color:#059669;font-weight:800;">+Rp<?= number_format($unt,0,',','.') ?></td>
                                        <td><?= $p['status']=='Aktif'?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-danger">Gangguan</span>' ?></td>
                                        <td style="text-align:right;">
                                            <button class="btn btn-warn-soft btn-xs" onclick="editProduk('<?= $p['sku'] ?>','<?= $p['kategori'] ?>','<?= addslashes($p['nama']) ?>','<?= $p['harga'] ?>','<?= $p['harga_modal'] ?>','<?= addslashes($p['deskripsi']) ?>','<?= $p['status'] ?>');openM('mEditProduk')">Edit</button>
                                            <button class="btn btn-danger-soft btn-xs" onclick="showConfirm('?del_produk=<?= $p['sku'] ?>','Hapus produk ini?')">Hapus</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-desk-none sortable-produk-mobile">
                            <?php foreach($prods as $p): $unt=$p['harga']-$p['harga_modal']; ?>
                            <div class="list-card <?= $p['status']=='Aktif'?'green':'red' ?>" data-sku="<?= $p['sku'] ?>">
                                <div style="display:flex;align-items:flex-start;gap:8px;">
                                    <span class="drag-handle" style="margin-top:2px;">☰</span>
                                    <div style="flex:1;">
                                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                            <span style="font-weight:800;font-size:.88rem;"><?= $p['nama'] ?></span>
                                            <?= $p['status']=='Aktif'?'<span class="badge badge-success" style="font-size:.65rem;">Aktif</span>':'<span class="badge badge-danger" style="font-size:.65rem;">Gangguan</span>' ?>
                                        </div>
                                        <div style="display:flex;gap:10px;font-size:.78rem;margin-bottom:8px;">
                                            <span style="color:var(--text-mid);">Modal: Rp<?= number_format($p['harga_modal'],0,',','.') ?></span>
                                            <span style="color:var(--accent-blue);font-weight:700;">Jual: Rp<?= number_format($p['harga'],0,',','.') ?></span>
                                            <span style="color:#059669;font-weight:800;">+Rp<?= number_format($unt,0,',','.') ?></span>
                                        </div>
                                        <div style="display:flex;gap:6px;">
                                            <button class="btn btn-warn-soft btn-xs" onclick="editProduk('<?= $p['sku'] ?>','<?= $p['kategori'] ?>','<?= addslashes($p['nama']) ?>','<?= $p['harga'] ?>','<?= $p['harga_modal'] ?>','<?= addslashes($p['deskripsi']) ?>','<?= $p['status'] ?>');openM('mEditProduk')">Edit</button>
                                            <button class="btn btn-danger-soft btn-xs" onclick="showConfirm('?del_produk=<?= $p['sku'] ?>','Hapus produk ini?')">Hapus</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="add-kat-row" onclick="openM('mAddKat')">+ Tambah Kategori Baru</div>
    </div>
</div>

<?php elseif($page=='transaksi'):
    $trx = $pdo->query("SELECT * FROM transaksi ORDER BY id DESC LIMIT 100")->fetchAll();
?>
<div class="page-header">
    <div class="page-title">Riwayat Transaksi <br><small>100 transaksi terbaru</small></div>
    <div class="breadcrumb">Panel / <span>Transaksi</span></div>
</div>

<div class="card d-mob-none">
    <div class="table-wrap">
    <table>
        <thead><tr>
            <th>Tanggal</th><th>Invoice</th><th>No. WA</th><th>Produk</th>
            <th>Target</th><th>Harga</th><th>Laba</th><th>Status</th><th style="text-align:right;">Aksi</th>
        </tr></thead>
        <tbody>
        <?php foreach($trx as $t): ?>
        <tr>
            <td style="font-size:.78rem;color:var(--text-mid);"><?= $t['tanggal'] ?></td>
            <td><code style="font-size:.75rem;color:var(--text-mid);"><?= $t['invoice'] ?></code></td>
            <td style="font-weight:700;"><?= $t['nomor_wa'] ?></td>
            <td><?= $t['nama_produk'] ?></td>
            <td><?= $t['target'] ?></td>
            <td style="color:var(--accent-blue);font-weight:700;">Rp<?= number_format($t['harga'],0,',','.') ?></td>
            <td style="color:#059669;font-weight:700;">+Rp<?= number_format($t['profit']??0,0,',','.') ?></td>
            <td><?php
                if($t['status']=='Sukses') echo '<span class="badge badge-success">Sukses</span>';
                elseif($t['status']=='Gagal') echo '<span class="badge badge-danger">Gagal</span>';
                else echo '<span class="badge badge-warning">'.$t['status'].'</span>';
            ?></td>
            <td style="text-align:right;"><button class="btn btn-ghost btn-xs" onclick="editTrx('<?= $t['invoice'] ?>','<?= $t['status'] ?>');openM('mEditTrx')">Edit</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="d-desk-none">
<?php foreach($trx as $t): $sc=$t['status']=='Sukses'?'green':($t['status']=='Gagal'?'red':'yellow'); ?>
<div class="list-card <?= $sc ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <div>
            <div style="font-weight:800;font-size:.9rem;margin-bottom:2px;"><?= $t['nama_produk'] ?></div>
            <code style="font-size:.72rem;color:var(--text-light);"><?= $t['invoice'] ?></code>
        </div>
        <?php if($t['status']=='Sukses') echo '<span class="badge badge-success">Sukses</span>';
        elseif($t['status']=='Gagal') echo '<span class="badge badge-danger">Gagal</span>';
        else echo '<span class="badge badge-warning">'.$t['status'].'</span>'; ?>
    </div>
    <div style="font-size:.78rem;color:var(--text-mid);margin-bottom:8px;">📱 <?= $t['nomor_wa'] ?> · 🎯 <?= $t['target'] ?></div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid var(--border);">
        <div>
            <span style="color:var(--accent-blue);font-weight:700;">Rp<?= number_format($t['harga'],0,',','.') ?></span>
            <span style="color:#059669;font-weight:700;font-size:.8rem;margin-left:8px;">+Rp<?= number_format($t['profit']??0,0,',','.') ?></span>
        </div>
        <button class="btn btn-ghost btn-xs" onclick="editTrx('<?= $t['invoice'] ?>','<?= $t['status'] ?>');openM('mEditTrx')">Edit</button>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php elseif($page=='pengguna'):
    $users = $pdo->query("SELECT * FROM users ORDER BY saldo DESC")->fetchAll();
?>
<div class="page-header">
    <div class="page-title">Daftar Member <br><small><?= count($users) ?> pengguna terdaftar</small></div>
    <div class="breadcrumb">Panel / <span>Member</span></div>
</div>

<div class="card d-mob-none">
    <div class="table-wrap">
    <table>
        <thead><tr><th>No. WhatsApp</th><th>Nama</th><th>Saldo</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
        <tbody>
        <?php foreach($users as $u): ?>
        <tr>
            <td style="font-weight:800;"><?= $u['nomor_wa'] ?></td>
            <td style="color:var(--text-mid);"><?= $u['nama'] ?></td>
            <td style="color:#059669;font-weight:800;">Rp <?= number_format($u['saldo'],0,',','.') ?></td>
            <td><?= $u['status']=='Aktif'?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-danger">Banned</span>' ?></td>
            <td style="text-align:right;">
                <button class="btn btn-ghost btn-xs" onclick="editSaldo('<?= $u['nomor_wa'] ?>','<?= addslashes($u['nama']) ?>',<?= $u['saldo'] ?>);openM('mEditSaldo')">Saldo</button>
                <button class="btn <?= $u['status']=='Aktif'?'btn-danger-soft':'btn-success-soft' ?> btn-xs" onclick="showConfirm('?page=pengguna&toggle_ban=<?= $u['nomor_wa'] ?>&status=<?= $u['status'] ?>','Ubah status akun ini?')"><?= $u['status']=='Aktif'?'Ban':'Unban' ?></button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="d-desk-none">
<?php foreach($users as $u): ?>
<div class="list-card <?= $u['status']=='Aktif'?'green':'red' ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
        <div>
            <div style="font-weight:900;font-size:.95rem;"><?= $u['nomor_wa'] ?></div>
            <div style="font-size:.8rem;color:var(--text-mid);font-weight:600;"><?= $u['nama'] ?></div>
        </div>
        <?= $u['status']=='Aktif'?'<span class="badge badge-success">Aktif</span>':'<span class="badge badge-danger">Banned</span>' ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px solid var(--border);">
        <span style="color:#059669;font-weight:900;font-size:1rem;">Rp <?= number_format($u['saldo'],0,',','.') ?></span>
        <div style="display:flex;gap:6px;">
            <button class="btn btn-ghost btn-xs" onclick="editSaldo('<?= $u['nomor_wa'] ?>','<?= addslashes($u['nama']) ?>',<?= $u['saldo'] ?>);openM('mEditSaldo')">Saldo</button>
            <button class="btn <?= $u['status']=='Aktif'?'btn-danger-soft':'btn-success-soft' ?> btn-xs" onclick="showConfirm('?page=pengguna&toggle_ban=<?= $u['nomor_wa'] ?>&status=<?= $u['status'] ?>','Ubah status akun ini?')"><?= $u['status']=='Aktif'?'Ban':'Unban' ?></button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php elseif($page=='pengaturan'):
    $set=[];
    foreach($pdo->query("SELECT * FROM settings")->fetchAll() as $s) $set[$s['kunci']]=$s['nilai'];
?>
<div class="page-header">
    <div class="page-title">Pengaturan Server <br><small>Konfigurasi bot, API, dan keamanan</small></div>
    <div class="breadcrumb">Panel / <span>Pengaturan</span></div>
</div>

<div class="settings-grid">
    <div>
        <div class="card">
            <div class="card-header">⚙️ Konfigurasi API & Koneksi</div>
            <div class="card-body">
            <form method="POST">
                <div class="section-title pink">Bot & Admin</div>
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp Admin</label>
                    <input type="text" name="settings[NOMOR_ADMIN]" value="<?= $set['NOMOR_ADMIN']??'' ?>" class="form-control" placeholder="628xxxx...">
                </div>

                <div class="section-title blue">Tripay — Payment Gateway</div>
                <div class="form-group"><label class="form-label">API Key</label><input type="text" name="settings[TRIPAY_API_KEY]" value="<?= $set['TRIPAY_API_KEY']??'' ?>" class="form-control" placeholder="DEV-xxx / PROD-xxx"></div>
                <div class="form-group"><label class="form-label">Private Key</label><input type="password" name="settings[TRIPAY_PRIVATE_KEY]" value="<?= $set['TRIPAY_PRIVATE_KEY']??'' ?>" class="form-control"></div>
                <div class="form-group"><label class="form-label">Merchant Code</label><input type="text" name="settings[TRIPAY_MERCHANT_CODE]" value="<?= $set['TRIPAY_MERCHANT_CODE']??'' ?>" class="form-control"></div>

                <div class="section-title orange">Digiflazz — Pusat PPOB</div>
                <div class="form-group"><label class="form-label">Username</label><input type="text" name="settings[DIGI_USERNAME]" value="<?= $set['DIGI_USERNAME']??'' ?>" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">API Key / Prod Key</label><input type="password" name="settings[DIGI_API_KEY]" value="<?= $set['DIGI_API_KEY']??'' ?>" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Webhook Secret</label><input type="text" name="settings[DIGI_WEBHOOK_SECRET]" value="<?= $set['DIGI_WEBHOOK_SECRET']??'' ?>" class="form-control"></div>
                </div>
                <button type="submit" name="save_settings" class="btn btn-pink" style="width:100%;justify-content:center;padding:13px;">💾 Simpan Semua Pengaturan</button>
            </form>
            </div>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="danger-zone">
            <h4>⚠️ Zona Berbahaya</h4>
            <p>Tindakan ini bersifat permanen atau akan mematikan layanan sesaat.</p>
            <a href="#" onclick="event.preventDefault();showConfirm('?restart_pm2=true','Bot akan restart & offline sesaat. Lanjutkan?')" class="btn btn-orange" style="width:100%;justify-content:center;">🔄 Restart Bot (PM2)</a>
        </div>
        <div class="card">
            <div class="card-header">🔐 Keamanan Panel</div>
            <div class="card-body">
            <form method="POST">
                <div class="form-group"><label class="form-label">Password Baru Admin</label><input type="text" name="new_password" class="form-control" placeholder="Isi password baru..." required></div>
                <button type="submit" name="change_password" class="btn btn-danger-soft" style="width:100%;justify-content:center;padding:11px;border-radius:var(--radius-sm);">Ubah Password</button>
            </form>
            </div>
        </div>
    </div>
</div>

<?php elseif($page=='log'): ?>
<div class="page-header">
    <div class="page-title">Log Sistem <br><small>Output real-time bot WhatsApp</small></div>
    <button class="btn btn-ghost btn-sm" onclick="location.reload()">↻ Refresh</button>
</div>
<div class="card">
    <div class="card-header">📝 100 Baris Terakhir — bot.log</div>
    <div class="log-box">
        <pre><?php
            $lf = __DIR__.'/bot.log';
            echo file_exists($lf) ? htmlspecialchars(shell_exec("tail -n 100 ".escapeshellarg($lf))) : "File log belum tersedia. Tunggu bot berjalan.";
        ?></pre>
    </div>
</div>
<?php endif; ?>

</div></main>

<div class="modal-overlay" id="mConfirm">
<div class="modal-box" style="max-width:380px;">
    <div class="modal-header"><div class="modal-title">Konfirmasi Tindakan</div><button class="modal-close" onclick="closeM('mConfirm')">×</button></div>
    <div class="modal-body" style="text-align:center;padding:32px;">
        <div style="font-size:3rem;margin-bottom:14px;">⚠️</div>
        <p id="confirmMsg" style="font-weight:700;color:var(--text-dark);font-size:0.95rem;"></p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeM('mConfirm')">Batal</button>
        <a href="#" id="confirmBtn" class="btn btn-orange" style="justify-content:center;">Ya, Lanjutkan</a>
    </div>
</div>
</div>

<div class="modal-overlay" id="mEditSaldo">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">💳 Edit Saldo Member</div><button class="modal-close" type="button" onclick="closeM('mEditSaldo')">×</button></div>
    <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:14px;">Member: <strong id="saldo_nama"></strong> (<span id="saldo_wa_txt"></span>)</div>
        <input type="hidden" name="nomor_wa" id="saldo_wa">
        <div class="form-group"><label class="form-label">Nominal Saldo Baru (Rp)</label><input type="number" name="saldo_baru" id="saldo_val" class="form-control" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mEditSaldo')">Batal</button><button type="submit" name="update_saldo" class="btn btn-pink">Simpan</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mEditTrx">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">🛒 Edit Status Transaksi</div><button class="modal-close" type="button" onclick="closeM('mEditTrx')">×</button></div>
    <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:14px;">Invoice: <strong id="trx_inv_txt"></strong></div>
        <input type="hidden" name="invoice" id="trx_inv">
        <div class="form-group"><label class="form-label">Status Baru</label>
            <select name="status_trx" id="trx_status" class="form-control">
                <option>Sukses</option><option>Gagal</option><option>Diproses</option><option>Menunggu Pembayaran</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mEditTrx')">Batal</button><button type="submit" name="edit_trx" class="btn btn-pink">Simpan</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mAddKat">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">📁 Tambah Kategori</div><button class="modal-close" type="button" onclick="closeM('mAddKat')">×</button></div>
    <div class="modal-body">
        <div class="form-group"><label class="form-label">Kode (Tanpa Spasi)</label><input type="text" name="kode" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Nama Tampilan</label><input type="text" name="nama" class="form-control" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mAddKat')">Batal</button><button type="submit" name="add_kategori" class="btn btn-pink">Simpan</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mEditKat">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">✏️ Edit Kategori</div><button class="modal-close" type="button" onclick="closeM('mEditKat')">×</button></div>
    <div class="modal-body">
        <div class="form-group"><label class="form-label">Kode (Permanen)</label><input type="text" name="kode_lama" id="ekat_kode" class="form-control" readonly style="opacity:.6;"></div>
        <div class="form-group"><label class="form-label">Nama Tampilan</label><input type="text" name="nama" id="ekat_nama" class="form-control" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mEditKat')">Batal</button><button type="submit" name="edit_kategori" class="btn btn-orange">Update</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mAddLaci">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">🗂 Tambah Laci</div><button class="modal-close" type="button" onclick="closeM('mAddLaci')">×</button></div>
    <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:14px;">Ke Kategori: <strong id="alaci_katNama"></strong></div>
        <input type="hidden" name="kategori_kode" id="alaci_katKode">
        <div class="form-group"><label class="form-label">Kode Laci</label><input type="text" name="kode" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Nama Tampilan</label><input type="text" name="nama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Tipe Validasi Target</label>
            <select name="tipe_validasi" class="form-control">
                <option value="bebas">Bebas (Default)</option>
                <option value="nomor_hp">Nomor HP</option>
                <option value="id_ml">ID Mobile Legends</option>
                <option value="id_ff">ID Free Fire</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mAddLaci')">Batal</button><button type="submit" name="add_laci" class="btn btn-pink">Simpan</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mEditLaci">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">✏️ Edit Laci</div><button class="modal-close" type="button" onclick="closeM('mEditLaci')">×</button></div>
    <div class="modal-body">
        <div class="form-group"><label class="form-label">Kode (Permanen)</label><input type="text" name="kode_lama" id="elaci_kode" class="form-control" readonly style="opacity:.6;"></div>
        <div class="form-group"><label class="form-label">Nama Tampilan</label><input type="text" name="nama" id="elaci_nama" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Tipe Validasi</label>
            <select name="tipe_validasi" id="elaci_val" class="form-control">
                <option value="bebas">Bebas</option><option value="nomor_hp">Nomor HP</option><option value="id_ml">ID Mobile Legends</option><option value="id_ff">ID Free Fire</option>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mEditLaci')">Batal</button><button type="submit" name="edit_laci" class="btn btn-orange">Update</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mAddProduk">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">📦 Tambah Produk</div><button class="modal-close" type="button" onclick="closeM('mAddProduk')">×</button></div>
    <div class="modal-body">
        <div class="alert alert-info" style="margin-bottom:14px;">Ke Laci: <strong id="aprod_laciNama"></strong></div>
        <input type="hidden" name="kategori" id="aprod_laciKode">
        <div class="form-group"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Nama Produk</label><input type="text" name="nama" class="form-control" required></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" style="color:#dc2626;">Harga Modal</label><input type="number" name="harga_modal" class="form-control" required></div>
            <div class="form-group"><label class="form-label" style="color:#059669;">Harga Jual</label><input type="number" name="harga" class="form-control" required></div>
        </div>
        <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option value="Aktif">Aktif</option><option value="Gangguan">Gangguan</option></select></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="deskripsi" class="form-control">-</textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mAddProduk')">Batal</button><button type="submit" name="add_product" class="btn btn-pink">Simpan</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mEditProduk">
<div class="modal-box"><form method="POST">
    <div class="modal-header"><div class="modal-title">✏️ Edit Produk</div><button class="modal-close" type="button" onclick="closeM('mEditProduk')">×</button></div>
    <div class="modal-body">
        <div class="form-group"><label class="form-label">SKU (Permanen)</label><input type="text" name="sku" id="ep_sku" class="form-control" readonly style="opacity:.6;"></div>
        <div class="form-group"><label class="form-label">Kode Laci</label><input type="text" name="kategori" id="ep_kat" class="form-control"></div>
        <div class="form-group"><label class="form-label">Nama Produk</label><input type="text" name="nama" id="ep_nama" class="form-control"></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" style="color:#dc2626;">Harga Modal</label><input type="number" name="harga_modal" id="ep_modal" class="form-control" required></div>
            <div class="form-group"><label class="form-label" style="color:#059669;">Harga Jual</label><input type="number" name="harga" id="ep_harga" class="form-control" required></div>
        </div>
        <div class="form-group"><label class="form-label">Status</label><select name="status" id="ep_status" class="form-control"><option value="Aktif">Aktif</option><option value="Gangguan">Gangguan</option></select></div>
        <div class="form-group"><label class="form-label">Deskripsi</label><textarea name="deskripsi" id="ep_desk" class="form-control"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeM('mEditProduk')">Batal</button><button type="submit" name="edit_product" class="btn btn-pink">Update</button></div>
</form></div>
</div>

<div class="modal-overlay" id="mMassAdd">
<div class="modal-box lg">
    <div class="modal-header"><div class="modal-title">⚡ Tambah Massal Produk</div><button class="modal-close" type="button" onclick="closeM('mMassAdd')">×</button></div>
    <div class="modal-body">
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('tSql',this)">Via SQL Query</button>
            <button class="tab-btn" onclick="switchTab('tCsv',this)">Via Upload CSV</button>
        </div>
        <div id="tSql" class="tab-pane active">
            <form method="POST">
                <div class="form-group"><label class="form-label">SQL Insert</label><textarea name="sql_query" class="form-control" style="font-family:monospace;min-height:120px;" placeholder="INSERT OR REPLACE INTO produk (sku, kategori, nama, harga, harga_modal, deskripsi, status) VALUES ..."></textarea></div>
                <button type="submit" name="mass_add_sql" class="btn btn-blue" style="width:100%;justify-content:center;">▶ Jalankan SQL</button>
            </form>
        </div>
        <div id="tCsv" class="tab-pane">
            <form method="POST" enctype="multipart/form-data">
                <div class="alert alert-info" style="margin-bottom:14px;font-size:.8rem;">Urutan kolom: <strong>SKU, Kode Laci, Nama, Harga Jual, Harga Modal, Deskripsi, Status</strong> (tanpa header)</div>
                <div class="form-group"><label class="form-label">File CSV</label><input type="file" name="file_csv" class="form-control" accept=".csv" required></div>
                <button type="submit" name="mass_add_csv" class="btn btn-teal" style="width:100%;justify-content:center;">📤 Upload & Import</button>
            </form>
        </div>
    </div>
</div>
</div>

<script>
// Sidebar mobile
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sOverlay').classList.add('active');
    const btn = document.getElementById('hamburgerBtn');
    if(btn) btn.textContent = '✕';
    document.body.style.overflow = 'hidden'; // cegah scroll body
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sOverlay').classList.remove('active');
    const btn = document.getElementById('hamburgerBtn');
    if(btn) btn.textContent = '☰';
    document.body.style.overflow = '';
}
function toggleSidebar() {
    const isOpen = document.getElementById('sidebar').classList.contains('open');
    isOpen ? closeSidebar() : openSidebar();
}

// Modal
function openM(id)  { document.getElementById(id).classList.add('active'); }
function closeM(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(el => el.addEventListener('click', e => { if(e.target===el) el.classList.remove('active'); }));

// Collapse
let openCols = JSON.parse(localStorage.getItem('ppob_cols') || '[]');
function toggleC(id) {
    const el = document.getElementById(id); if(!el) return;
    if(el.classList.toggle('open')) { if(!openCols.includes(id)) openCols.push(id); }
    else openCols = openCols.filter(x=>x!==id);
    localStorage.setItem('ppob_cols', JSON.stringify(openCols));
}
document.addEventListener('DOMContentLoaded', () => {
    openCols.forEach(id => { const el=document.getElementById(id); if(el) el.classList.add('open'); });
});

// Confirm
function showConfirm(url, msg) {
    document.getElementById('confirmMsg').innerText = msg;
    document.getElementById('confirmBtn').href = url;
    openM('mConfirm');
}

// Tabs
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

// Toast
function toast(msg) {
    document.getElementById('toast-msg').textContent = msg;
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 2500);
}

// Sort API
function simpanUrutan(table, items) {
    const fd = new FormData();
    fd.append('action','update_sort'); fd.append('table',table);
    items.forEach(id=>fd.append('items[]',id));
    fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{ if(res.status==='ok') toast('Urutan tersimpan!'); });
}

// Sortable init
document.addEventListener('DOMContentLoaded', () => {
    const o = { handle:'.drag-handle', animation:150, delay:400, delayOnTouchOnly:false, forceFallback:true, fallbackClass:'sortable-drag', chosenClass:'sortable-chosen', ghostClass:'sortable-ghost' };
    const katEl = document.getElementById('sortable-kategori');
    if(katEl) new Sortable(katEl, {...o, onEnd: e=>simpanUrutan('kategori', Array.from(e.to.children).map(el=>el.dataset.kode)) });
    document.querySelectorAll('.sortable-laci').forEach(el => new Sortable(el, {...o, onEnd: e=>simpanUrutan('laci', Array.from(e.to.children).map(el=>el.dataset.kode)) }));
    document.querySelectorAll('.sortable-produk').forEach(el => new Sortable(el, {...o, onEnd: e=>simpanUrutan('produk', Array.from(e.to.children).map(el=>el.dataset.sku)) }));
    document.querySelectorAll('.sortable-produk-mobile').forEach(el => new Sortable(el, {...o, onEnd: e=>simpanUrutan('produk', Array.from(e.to.children).map(el=>el.dataset.sku)) }));
});

// Helpers
function siapkanLaci(k,n)  { document.getElementById('alaci_katKode').value=k; document.getElementById('alaci_katNama').innerText=n; }
function siapkanProduk(k,n){ document.getElementById('aprod_laciKode').value=k; document.getElementById('aprod_laciNama').innerText=n; }
function editKat(kode,nama)  { document.getElementById('ekat_kode').value=kode; document.getElementById('ekat_nama').value=nama; }
function editLaci(kode,nama,val){ document.getElementById('elaci_kode').value=kode; document.getElementById('elaci_nama').value=nama; document.getElementById('elaci_val').value=val; }
function editProduk(sku,kat,nama,harga,modal,desk,status){
    document.getElementById('ep_sku').value=sku; document.getElementById('ep_kat').value=kat;
    document.getElementById('ep_nama').value=nama; document.getElementById('ep_harga').value=harga;
    document.getElementById('ep_modal').value=modal; document.getElementById('ep_desk').value=desk;
    document.getElementById('ep_status').value=status;
}
function editSaldo(wa,nama,saldo){ document.getElementById('saldo_wa').value=wa; document.getElementById('saldo_wa_txt').innerText=wa; document.getElementById('saldo_nama').innerText=nama; document.getElementById('saldo_val').value=saldo; }
function editTrx(inv,status){ document.getElementById('trx_inv').value=inv; document.getElementById('trx_inv_txt').innerText=inv; document.getElementById('trx_status').value=status; }
</script>
</body>
</html>
