<?php
session_start();

$configFile = __DIR__ . '/config.php';
$isInstalled = file_exists($configFile);

// ===========================================================
//  MODE INSTALLATION (PREMIER LANCEMENT)
// ===========================================================
if (!$isInstalled) {
    if (isset($_POST['install'])) {
        $admin_user = trim($_POST['admin_username'] ?? '');
        $admin_pass = $_POST['admin_password'] ?? '';
        $site_name  = trim($_POST['site_name'] ?? 'Purple Music');

        // Paramètres MySQL
        $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
        $db_port = trim($_POST['db_port'] ?? '3306');
        $db_name = trim($_POST['db_name'] ?? 'purple_music');
        $db_user = trim($_POST['db_user'] ?? 'purple_music_user');
        $db_pass = $_POST['db_pass'] ?? '';

        $color_bg      = $_POST['inst_color_bg']      ?? '#0f0c1d';
        $color_panel   = $_POST['inst_color_panel']   ?? '#1b1429';
        $color_primary = $_POST['inst_color_primary'] ?? '#8e44ad';
        $color_accent  = $_POST['inst_color_accent']  ?? '#bb86fc';
        $color_text    = $_POST['inst_color_text']    ?? '#e0e0e0';

        if (empty($admin_user) || empty($admin_pass)) {
            $install_error = "Identifiant et mot de passe admin requis.";
        } else {
            try {
                $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // ── Création des tables ──────────────────────────────────
                $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                    `setting_key` VARCHAR(100) NOT NULL,
                    `value` TEXT NOT NULL,
                    PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `username` VARCHAR(60) NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `genres` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_genre_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `tracks` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `filename` VARCHAR(255) NOT NULL,
                    `title` VARCHAR(255) NOT NULL,
                    `artist` VARCHAR(255) NOT NULL DEFAULT 'Artiste inconnu',
                    `cover` VARCHAR(255) NOT NULL DEFAULT 'default.png',
                    `genre` VARCHAR(100) NOT NULL DEFAULT 'Autre',
                    `uploader_id` INT UNSIGNED NOT NULL,
                    `upload_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `play_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `duration` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_play_count` (`play_count`),
                    KEY `idx_uploader` (`uploader_id`),
                    CONSTRAINT `fk_tracks_user`
                        FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $pdo->exec("CREATE TABLE IF NOT EXISTS `playlists` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `creator_id` INT UNSIGNED NOT NULL,
                    `song_ids` TEXT,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_creator` (`creator_id`),
                    CONSTRAINT `fk_playlists_user`
                        FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                // ── Dossiers ────────────────────────────────────────────
                if (!is_dir(__DIR__ . '/music'))  mkdir(__DIR__ . '/music',  0755, true);
                if (!is_dir(__DIR__ . '/covers')) mkdir(__DIR__ . '/covers', 0755, true);

                // ── Assets uploadés ─────────────────────────────────────
                if (!empty($_FILES['inst_favicon']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['inst_favicon']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'ico'])) {
                        move_uploaded_file($_FILES['inst_favicon']['tmp_name'], __DIR__ . '/favicon.png');
                    }
                }
                if (!empty($_FILES['inst_default_cover']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['inst_default_cover']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                        move_uploaded_file($_FILES['inst_default_cover']['tmp_name'], __DIR__ . '/covers/default.png');
                    }
                }

                // ── Settings ────────────────────────────────────────────
                $defaultSettings = [
                    'site_name'           => $site_name,
                    'color_bg'            => $color_bg,
                    'color_panel'         => $color_panel,
                    'color_primary'       => $color_primary,
                    'color_accent'        => $color_accent,
                    'color_text'          => $color_text,
                    'color_text_muted'    => '#a196b4',
                    'color_border'        => '#3d2b56',
                    'color_search_bg'     => '#241b36',
                    'color_header_bg'     => 'rgba(27, 20, 41, 0.85)',
                    'color_player_bg'     => 'rgba(30, 24, 45, 0.85)',
                    'color_mob_nav_bg'    => 'rgba(21, 16, 32, 0.95)',
                    'color_fp_gradient_1' => '#302b63',
                    'color_fp_gradient_2' => '#0f0c29',
                    'default_cover'       => 'default.png',
                    'favicon'             => 'favicon.png',
                ];
                $stmtSet = $pdo->prepare(
                    "INSERT INTO `settings` (`setting_key`, `value`) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
                );
                foreach ($defaultSettings as $k => $v) $stmtSet->execute([$k, $v]);

                // ── Genres ──────────────────────────────────────────────
                $defaultGenres = ['Phonk/Funk','Rap','Pop','Rock','Electro','Hyperpop','Nightcore','Qualité inférieure','Autre'];
                $stmtGen = $pdo->prepare("INSERT IGNORE INTO `genres` (`name`) VALUES (?)");
                foreach ($defaultGenres as $g) $stmtGen->execute([$g]);

                // ── Admin ───────────────────────────────────────────────
                $hash   = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmtU  = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `is_admin`) VALUES (?, ?, 1)");
                $stmtU->execute([$admin_user, $hash]);
                $adminId = $pdo->lastInsertId();

                // ── Protection dossiers ─────────────────────────────────
                file_put_contents(__DIR__ . '/music/.htaccess',
                    "RemoveHandler .php .phtml .phps\nOptions -ExecCGI\n<Files *>\nSetHandler default-handler\n</Files>");
                file_put_contents(__DIR__ . '/covers/.htaccess',
                    "RemoveHandler .php .phtml .phps\nOptions -ExecCGI\n<Files *>\nSetHandler default-handler\n</Files>");

                // ── config.php ──────────────────────────────────────────
                $configContent = "<?php\n"
                    . "define('DB_HOST', '" . addslashes($db_host) . "');\n"
                    . "define('DB_PORT', '" . addslashes($db_port) . "');\n"
                    . "define('DB_NAME', '" . addslashes($db_name) . "');\n"
                    . "define('DB_USER', '" . addslashes($db_user) . "');\n"
                    . "define('DB_PASS', '" . addslashes($db_pass) . "');\n"
                    . "define('MUSIC_DIR',  __DIR__ . '/music');\n"
                    . "define('COVER_DIR',  __DIR__ . '/covers');\n";
                file_put_contents($configFile, $configContent);

                $_SESSION['user_id']  = (int)$adminId;
                $_SESSION['username'] = $admin_user;
                $_SESSION['is_admin'] = 1;
                // Nécessaire pour les appels authentifiés à api.php juste après
                // l'installation (voir la connexion normale plus bas dans le fichier).
                $_SESSION['api_pw']   = $admin_pass;
                header("Location: " . $_SERVER['PHP_SELF']); exit;

            } catch (Exception $e) {
                @unlink($configFile);
                $install_error = "Erreur : " . $e->getMessage();
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation — Purple Music</title>
        <style>
            body { background:#0f0c1d; color:#e0e0e0; font-family:system-ui,sans-serif; display:flex; justify-content:center; align-items:flex-start; min-height:100vh; margin:0; padding:40px 20px; }
            .box { background:#1b1429; padding:40px; border-radius:24px; box-shadow:0 10px 40px rgba(0,0,0,.6); width:100%; max-width:520px; box-sizing:border-box; }
            h2,h3 { color:#bb86fc; text-align:center; margin-top:0; }
            h3 { border-bottom:1px solid #3d2b56; padding-bottom:8px; margin-top:25px; font-size:1.05em; text-align:left; }
            label { font-size:.9em; color:#a196b4; display:block; margin-top:10px; }
            input[type=text],input[type=password],input[type=file],input[type=number] { width:100%; padding:12px; margin:6px 0 14px; background:#140f1f; border:1px solid #3d2b56; color:#fff; border-radius:10px; box-sizing:border-box; outline:none; }
            .db-grid { display:grid; grid-template-columns:1fr 80px; gap:12px; }
            .color-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:10px; }
            .color-item { background:#140f1f; padding:10px; border-radius:10px; border:1px solid #3d2b56; display:flex; align-items:center; justify-content:space-between; }
            input[type=color] { border:none; width:40px; height:30px; background:transparent; cursor:pointer; }
            button { width:100%; padding:14px; background:#8e44ad; border:none; color:#fff; font-weight:700; font-size:1em; border-radius:50px; cursor:pointer; margin-top:20px; }
            button:hover { background:#9b59b6; }
            .error { color:#ff4757; text-align:center; font-size:.9em; margin-bottom:15px; }
            .hint { font-size:.78em; color:#6b5e85; margin-top:-10px; margin-bottom:10px; }
        </style>
    </head>
    <body>
    <div class="box">
        <h2>⚙ Configuration Initiale</h2>
        <?php if (isset($install_error)) echo '<div class="error">' . htmlspecialchars($install_error) . '</div>'; ?>
        <form method="post" enctype="multipart/form-data">
            <h3>Général</h3>
            <label>Nom du site</label>
            <input type="text" name="site_name" value="Purple Music" required>

            <h3>Connexion MySQL</h3>
            <p class="hint">La base de données et l'utilisateur doivent déjà exister (voir <code>setup_purple_music.sql</code>).</p>
            <div class="db-grid">
                <div>
                    <label>Hôte MySQL</label>
                    <input type="text" name="db_host" value="127.0.0.1" required>
                </div>
                <div>
                    <label>Port</label>
                    <input type="number" name="db_port" value="3306" required>
                </div>
            </div>
            <label>Nom de la base</label>
            <input type="text" name="db_name" value="purple_music" required>
            <label>Utilisateur MySQL</label>
            <input type="text" name="db_user" value="purple_music_user" required>
            <label>Mot de passe MySQL</label>
            <input type="password" name="db_pass" placeholder="Mot de passe de l'utilisateur MySQL">

            <h3>Compte Administrateur</h3>
            <label>Identifiant admin</label>
            <input type="text" name="admin_username" placeholder="ex: Axolat" required>
            <label>Mot de passe admin</label>
            <input type="password" name="admin_password" required>

            <h3>Thème</h3>
            <div class="color-grid">
                <div class="color-item"><span>Arrière-plan</span><input type="color" name="inst_color_bg" value="#0f0c1d"></div>
                <div class="color-item"><span>Panneaux</span><input type="color" name="inst_color_panel" value="#1b1429"></div>
                <div class="color-item"><span>Primaire</span><input type="color" name="inst_color_primary" value="#8e44ad"></div>
                <div class="color-item"><span>Accent</span><input type="color" name="inst_color_accent" value="#bb86fc"></div>
            </div>
            <label>Couleur texte</label>
            <input type="color" name="inst_color_text" value="#e0e0e0" style="width:100%;height:40px;background:#140f1f;padding:5px;border:1px solid #3d2b56;border-radius:10px;">

            <h3>Assets</h3>
            <label>Favicon (.png/.ico)</label>
            <input type="file" name="inst_favicon" accept="image/png,image/x-icon">
            <label>Cover par défaut</label>
            <input type="file" name="inst_default_cover" accept="image/*">

            <button type="submit" name="install">Installer et démarrer</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ===========================================================
//  CONNEXION MySQL & BOOTSTRAP
// ===========================================================
require_once $configFile;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function checkRateLimit(string $action, int $limitSeconds): bool {
    $key = 'last_' . $action . '_time';
    if (isset($_SESSION[$key]) && (time() - $_SESSION[$key]) < $limitSeconds) return false;
    $_SESSION[$key] = time();
    return true;
}

// Sert les variantes "r,g,b" des couleurs de thème pour permettre des
// rgba(var(--x-rgb), alpha) dans le CSS — nécessaire pour que les halos/
// surbrillances tintés continuent de suivre la couleur choisie (site ou
// thème client) au lieu de rester figés sur le violet d'origine.
function hexToRgbTriplet(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '142,68,173';
    $int = hexdec($hex);
    return sprintf('%d,%d,%d', ($int >> 16) & 255, ($int >> 8) & 255, $int & 255);
}

// ===========================================================
//  CLIENT api.php — index.php ne parle plus jamais directement
//  aux tables `users`/`tracks`/`playlists` : toute la logique
//  métier (auth, upload, lecture, playlists…) passe par api.php,
//  exactement comme le fait le client Android Amethyst Music.
//  Seuls les réglages du site (thème serveur, genres) restent
//  gérés ici : api.php n'a pas de notion de ces tables.
// ===========================================================
function api_request(string $action, array $params = []): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/api.php?action=' . urlencode($action);
    $body = http_build_query($params);
    $fallback = ['status' => 'error', 'message' => 'api.php injoignable'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false) return $fallback;
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : $fallback;
}

// ── PDO MySQL (réglages du site : thème & genres uniquement) ──
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $settingsRaw = $db->query("SELECT `setting_key`, `value` FROM `settings`")
                      ->fetchAll(PDO::FETCH_KEY_PAIR);

    $site_name           = $settingsRaw['site_name']           ?? 'Purple Music';
    $color_bg            = $settingsRaw['color_bg']            ?? '#0f0c1d';
    $color_panel         = $settingsRaw['color_panel']         ?? '#1b1429';
    $color_primary       = $settingsRaw['color_primary']       ?? '#8e44ad';
    $color_accent        = $settingsRaw['color_accent']        ?? '#bb86fc';
    $color_text          = $settingsRaw['color_text']          ?? '#e0e0e0';
    $color_text_muted    = $settingsRaw['color_text_muted']    ?? '#a196b4';
    $color_border        = $settingsRaw['color_border']        ?? '#3d2b56';
    $color_search_bg     = $settingsRaw['color_search_bg']     ?? '#241b36';
    $color_header_bg     = $settingsRaw['color_header_bg']     ?? 'rgba(27, 20, 41, 0.85)';
    $color_player_bg     = $settingsRaw['color_player_bg']     ?? 'rgba(30, 24, 45, 0.85)';
    $color_mob_nav_bg    = $settingsRaw['color_mob_nav_bg']    ?? 'rgba(21, 16, 32, 0.95)';
    $color_fp_gradient_1 = $settingsRaw['color_fp_gradient_1'] ?? '#302b63';
    $color_fp_gradient_2 = $settingsRaw['color_fp_gradient_2'] ?? '#0f0c29';
    $default_cover       = $settingsRaw['default_cover']       ?? 'default.png';
    $favicon_file        = $settingsRaw['favicon']             ?? 'favicon.png';

    $genresList = $db->query("SELECT `name` FROM `genres` ORDER BY `name` ASC")
                     ->fetchAll(PDO::FETCH_COLUMN);
    if (empty($genresList)) {
        $genresList = ['Phonk/Funk','Rap','Pop','Rock','Electro','Hyperpop','Nightcore','Qualité inférieure','Autre'];
    }

} catch (Exception $e) {
    die("Erreur BDD : " . $e->getMessage());
}

// ===========================================================
//  AUTHENTIFICATION — déléguée à api.php (action=login/register)
// ===========================================================
if (isset($_POST['register'])) {
    if (!checkRateLimit('register', 30)) {
        $error = "Veuillez patienter avant de vous réinscrire.";
    } else {
        $res = api_request('register', [
            'username' => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ]);
        if (($res['status'] ?? '') === 'success') {
            $info = "Compte créé avec succès, tu peux te connecter.";
        } else {
            $error = $res['message'] ?? "Inscription impossible.";
        }
    }
}

if (isset($_POST['login'])) {
    $res = api_request('login', [
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
    ]);
    if (($res['status'] ?? '') === 'success') {
        $_SESSION['user_id']  = (int)$res['user_id'];
        $_SESSION['username'] = $res['username'];
        $_SESSION['is_admin'] = !empty($res['is_admin']) ? 1 : 0;
        // api.php n'a pas de session : chaque appel authentifié doit renvoyer
        // le mot de passe. On le garde côté serveur (jamais en localStorage)
        // pour l'injecter dans la page et permettre au JS d'appeler api.php.
        $_SESSION['api_pw'] = $_POST['password'] ?? '';
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    } else {
        $error = $res['message'] ?? "Identifiants incorrects.";
    }
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: " . $_SERVER['PHP_SELF']); exit; }

$user_id  = $_SESSION['user_id']  ?? null;
$username = $_SESSION['username'] ?? null;
$is_admin = !empty($_SESSION['is_admin']);
$api_pw   = $_SESSION['api_pw'] ?? '';

// ===========================================================
//  CONTRÔLES (utilisateur connecté)
// ===========================================================
if ($user_id) {
    // Vérification CSRF pour toutes les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            die("Jeton CSRF invalide.");
        }
    }

    // ── Sauvegarde paramètres admin ──────────────────────────
    if ($is_admin && isset($_POST['save_admin_settings'])) {
        $upsert = $db->prepare(
            "INSERT INTO `settings` (`setting_key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $fields = [
            'site_name'           => trim($_POST['adm_site_name']),
            'color_bg'            => $_POST['adm_color_bg'],
            'color_panel'         => $_POST['adm_color_panel'],
            'color_primary'       => $_POST['adm_color_primary'],
            'color_accent'        => $_POST['adm_color_accent'],
            'color_text'          => $_POST['adm_color_text'],
            'color_text_muted'    => $_POST['adm_color_text_muted'],
            'color_border'        => $_POST['adm_color_border'],
            'color_search_bg'     => $_POST['adm_color_search_bg'],
            'color_header_bg'     => $_POST['adm_color_header_bg'],
            'color_player_bg'     => $_POST['adm_color_player_bg'],
            'color_mob_nav_bg'    => $_POST['adm_color_mob_nav_bg'],
            'color_fp_gradient_1' => $_POST['adm_color_fp_gradient_1'],
            'color_fp_gradient_2' => $_POST['adm_color_fp_gradient_2'],
        ];
        foreach ($fields as $k => $v) $upsert->execute([$k, $v]);

        if (!empty($_FILES['adm_favicon']['name'])) {
            $ext = strtolower(pathinfo($_FILES['adm_favicon']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','ico'])) move_uploaded_file($_FILES['adm_favicon']['tmp_name'], __DIR__ . '/favicon.png');
        }
        if (!empty($_FILES['adm_default_cover']['name'])) {
            $ext = strtolower(pathinfo($_FILES['adm_default_cover']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg'])) move_uploaded_file($_FILES['adm_default_cover']['tmp_name'], __DIR__ . '/covers/default.png');
        }
        if (!empty($_POST['adm_new_genre'])) {
            $db->prepare("INSERT IGNORE INTO `genres` (`name`) VALUES (?)")->execute([trim($_POST['adm_new_genre'])]);
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    if ($is_admin && isset($_GET['delete_genre'])) {
        $db->prepare("DELETE FROM `genres` WHERE `name` = ?")->execute([$_GET['delete_genre']]);
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    // Upload, édition/suppression de pistes, et gestion des playlists sont
    // désormais entièrement gérés côté client via des appels fetch() à
    // api.php (voir le <script> plus bas) — index.php ne duplique plus
    // cette logique (extraction ID3, calcul de durée, compression d'image…
    // tout ça vit déjà dans api.php).
}

// ===========================================================
//  DONNÉES POUR LE RENDU — lues depuis api.php
// ===========================================================
$all_tracks    = $user_id ? api_request('list')      : [];
$all_playlists = $user_id ? api_request('playlists') : [];
if (!is_array($all_tracks) || (isset($all_tracks['status'])))       $all_tracks = [];
if (!is_array($all_playlists) || (isset($all_playlists['status']))) $all_playlists = [];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($site_name); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon_file); ?>?v=<?php echo time(); ?>">
    <style>
        :root {
            --bg-dark:    <?php echo $color_bg; ?>;
            --bg-panel:   <?php echo $color_panel; ?>;
            --primary:    <?php echo $color_primary; ?>;
            --accent:     <?php echo $color_accent; ?>;
            --primary-rgb: <?php echo hexToRgbTriplet($color_primary); ?>;
            --accent-rgb:  <?php echo hexToRgbTriplet($color_accent); ?>;
            --text:       <?php echo $color_text; ?>;
            --text-muted: <?php echo $color_text_muted; ?>;
            --border-color:  <?php echo $color_border; ?>;
            --search-bg:     <?php echo $color_search_bg; ?>;
            --header-bg:     <?php echo $color_header_bg; ?>;
            --mob-nav-bg:    <?php echo $color_mob_nav_bg; ?>;
            --player-bg:     <?php echo $color_player_bg; ?>;
            --fp-gradient-1: <?php echo $color_fp_gradient_1; ?>;
            --fp-gradient-2: <?php echo $color_fp_gradient_2; ?>;
            /* Surfaces/texte non exposés au panneau admin, mais suivant
               quand même le thème choisi par l'utilisateur (voir applyTheme). */
            --modal-bg:    #1e162e;
            --input-bg:    #140f1f;
            --elevated-bg: #2d2444;
            --player-text: #ffffff;
            --danger: #ff4757;
            --radius-sm: 8px; --radius-md: 16px; --radius-lg: 24px; --radius-full: 9999px;
        }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        body { margin:0; font-family:'Segoe UI',system-ui,-apple-system,sans-serif; background:var(--bg-dark); color:var(--text); padding-bottom:160px; overflow-x:hidden; }
        ::-webkit-scrollbar { width:8px; } ::-webkit-scrollbar-track { background:var(--bg-dark); } ::-webkit-scrollbar-thumb { background:var(--border-color); border-radius:var(--radius-full); } ::-webkit-scrollbar-thumb:hover { background:var(--primary); }

        header { display:flex; justify-content:space-between; padding:15px 30px; background:var(--header-bg); backdrop-filter:blur(15px); border-bottom:1px solid rgba(61,43,86,.5); align-items:center; position:sticky; top:0; z-index:100; height:70px; }
        .logo { font-weight:800; font-size:1.6em; color:var(--accent); white-space:nowrap; letter-spacing:-1px; }
        nav { display:flex; gap:25px; margin-left:40px; flex-grow:1; }
        nav span { cursor:pointer; font-weight:600; color:var(--text-muted); transition:.3s; white-space:nowrap; padding:5px 10px; border-radius:var(--radius-sm); }
        nav span:hover { color:var(--text); background:rgba(255,255,255,.05); }
        nav span.active { color:var(--accent); }
        nav span.admin-nav-btn { color:#e67e22; font-weight:700; }
        .header-actions { display:flex; gap:12px; align-items:center; }

        .btn { padding:10px 20px; border-radius:var(--radius-full); border:none; cursor:pointer; font-weight:700; transition:all .2s ease; text-decoration:none; display:inline-flex; align-items:center; font-size:.9em; white-space:nowrap; justify-content:center; }
        .btn:active { transform:scale(.96); }
        .btn-primary { background:var(--primary); color:white; box-shadow:0 4px 15px rgba(var(--primary-rgb),.3); }
        .btn-primary:hover { background:#9b59b6; }
        .btn-outline { background:transparent; border:1px solid var(--primary); color:var(--accent); }
        .btn-outline:hover { background:rgba(var(--primary-rgb),.1); }
        .btn-danger { background:rgba(255,71,87,.1); color:var(--danger); font-size:.75em; border:1px solid rgba(255,71,87,.3); padding:6px 12px; }

        main { padding:30px; max-width:1100px; margin:auto; }
        .controls-container { margin-bottom:25px; }
        .section-title { border-left:5px solid var(--primary); padding-left:15px; margin-bottom:20px; font-size:1.5em; border-radius:2px; }
        .search-row { display:flex; align-items:center; gap:15px; width:100%; }
        .search-container { flex-grow:1; position:relative; }
        .search-input { width:100%; height:50px; padding:0 25px; border-radius:50px; border:1px solid rgba(61,43,86,.5); background:var(--search-bg); color:var(--text); font-size:1em; outline:none; transition:all .3s; box-shadow:0 4px 10px rgba(0,0,0,.2); }
        .search-input:focus { border-color:var(--accent); background:var(--elevated-bg); box-shadow:0 0 0 3px rgba(var(--accent-rgb),.2); }
        .search-input::placeholder { color:var(--text-muted); }
        .filter-wrapper { position:relative; width:50px; height:50px; flex-shrink:0; }
        .filter-icon-visual { width:100%; height:100%; background:var(--search-bg); border:1px solid rgba(61,43,86,.5); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--accent); box-shadow:0 4px 10px rgba(0,0,0,.2); transition:.3s; }
        .filter-select-overlay { position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; appearance:none; -webkit-appearance:none; z-index:10; }
        .filter-wrapper:hover .filter-icon-visual { border-color:var(--accent); background:var(--elevated-bg); transform:translateY(-2px); }

        .track-list { background:var(--bg-panel); border-radius:24px; overflow:hidden; border:1px solid #2d2444; min-height:200px; box-shadow:0 10px 30px rgba(0,0,0,.2); }
        .track-item { display:grid; grid-template-columns:40px 50px 1fr auto; align-items:center; padding:15px 25px; border-bottom:1px solid rgba(255,255,255,.03); gap:20px; transition:background .2s; }
        .track-item:last-child { border-bottom:none; }
        .track-item:hover { background:rgba(255,255,255,.07); }
        .mini-cover { width:50px; height:50px; border-radius:12px; object-fit:cover; box-shadow:0 4px 8px rgba(0,0,0,.3); }
        .track-index { color:var(--primary); font-weight:700; opacity:.7; }
        #load-more-trigger { height:40px; text-align:center; color:var(--text-muted); padding-top:15px; font-size:.9em; }

        .playlist-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:25px; }
        .playlist-card { background:var(--bg-panel); border-radius:24px; padding:25px; border:1px solid rgba(61,43,86,.5); transition:transform .3s,box-shadow .3s; }
        .playlist-card:hover { transform:translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,.4); border-color:var(--primary); }

        #queue-panel { position:fixed; right:-360px; top:70px; width:340px; height:calc(100vh - 70px); background:var(--mob-nav-bg); backdrop-filter:blur(20px); border-left:1px solid rgba(255,255,255,.1); z-index:999; transition:.4s cubic-bezier(.4,0,.2,1); padding:25px; padding-bottom:120px; box-shadow:-10px 0 40px rgba(0,0,0,.5); overflow-y:auto; }
        #queue-panel.open { right:0; }
        .queue-item { display:flex; align-items:center; gap:12px; padding:10px; border-radius:12px; margin-bottom:8px; cursor:pointer; border:1px solid transparent; transition:.2s; }
        .queue-item.active { background:rgba(var(--primary-rgb),.15); border-color:var(--primary); }
        .queue-item:hover { background:rgba(255,255,255,.05); }
        .close-queue-mobile { display:none; width:100%; margin-bottom:20px; background:var(--elevated-bg); border:none; color:var(--player-text); padding:12px; border-radius:var(--radius-sm); font-weight:bold; }

        #player-bar { position:fixed; bottom:25px; left:50%; transform:translateX(-50%); width:94%; max-width:1000px; background:var(--player-bg); backdrop-filter:blur(20px) saturate(180%); padding:15px 30px; border-radius:20px; display:flex; align-items:center; z-index:1000; border:1px solid rgba(255,255,255,.1); box-shadow:0 15px 50px rgba(0,0,0,.6); }
        .player-info { display:flex; align-items:center; gap:15px; width:25%; min-width:180px; }
        #player-cover { width:56px; height:56px; border-radius:12px; object-fit:cover; box-shadow:0 5px 15px rgba(0,0,0,.3); }
        .progress-container { flex-grow:1; margin:0 20px; }
        .progress-bg { background:rgba(255,255,255,.1); height:6px; border-radius:10px; cursor:pointer; position:relative; overflow:hidden; }
        .progress-fill { background:linear-gradient(90deg,var(--primary),var(--accent)); height:100%; width:0%; border-radius:10px; }
        .controls { display:flex; align-items:center; gap:12px; }
        .control-btn { background:none; border:none; color:var(--player-text); cursor:pointer; opacity:.8; transition:.2s; padding:8px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .control-btn svg { width:24px; height:24px; fill:var(--player-text); display:block; }
        .control-btn:hover { background:rgba(255,255,255,.1); opacity:1; }
        .control-btn.active { color:var(--accent); opacity:1; position:relative; }
        .control-btn.active svg { fill:var(--accent); }
        .control-btn.active::after { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:4px; height:4px; background:var(--accent); border-radius:50%; }
        .loop-badge { display:none; position:absolute; top:-2px; right:-2px; width:14px; height:14px; border-radius:50%; background:var(--accent); color:var(--bg-dark); font-size:9px; font-weight:800; line-height:1; align-items:center; justify-content:center; font-family:system-ui,sans-serif; box-shadow:0 0 0 2px var(--bg-panel); }
        .loop-badge.show { display:flex; }
        #masterPlay { background:white; border:none; width:50px; height:50px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform .2s,box-shadow .2s; box-shadow:0 0 20px rgba(255,255,255,.3); flex-shrink:0; }
        #masterPlay:hover { transform:scale(1.1); box-shadow:0 0 30px rgba(255,255,255,.5); }
        #masterPlay svg { fill:#0f0c1d; width:22px; height:22px; }
        .volume-container { display:flex; align-items:center; gap:8px; width:110px; margin-left:10px; }
        .fp-volume-container { width:100%; margin:15px 0 25px 0; justify-content:center; }
        input[type=range].vol-slider { -webkit-appearance:none; width:100%; height:4px; background:linear-gradient(90deg,var(--accent) 100%,rgba(255,255,255,.2) 100%); border-radius:5px; outline:none; cursor:pointer; }
        input[type=range].vol-slider::-webkit-slider-thumb { -webkit-appearance:none; width:12px; height:12px; background:#fff; border-radius:50%; cursor:pointer; transition:.2s; }

        .modal { display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.6); backdrop-filter:blur(8px); }
        .modal-content { background:var(--modal-bg); margin:5% auto; padding:30px; width:90%; max-width:550px; border-radius:28px; border:1px solid rgba(255,255,255,.1); box-shadow:0 25px 80px rgba(0,0,0,.5); max-height:85vh; overflow-y:auto; animation:modalPop .3s cubic-bezier(.175,.885,.32,1.275); }
        @keyframes modalPop { from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }

        input[type=text],input[type=password],input[type=file],select { width:100%; padding:14px; margin:10px 0 20px 0; background:var(--input-bg); border:1px solid var(--border-color); color:var(--text); border-radius:12px; outline:none; transition:.3s; }
        input[type=text]:focus,input[type=password]:focus,select:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(var(--primary-rgb),.2); }

        .adm-accordion-item { border:1px solid var(--border-color); border-radius:14px; margin-bottom:12px; background:rgba(0,0,0,.15); overflow:hidden; }
        .adm-accordion-header { background:rgba(255,255,255,.02); padding:16px 20px; font-weight:bold; font-size:1.05em; color:var(--accent); cursor:pointer; display:flex; justify-content:space-between; align-items:center; user-select:none; transition:background .2s; }
        .adm-accordion-header:hover { background:rgba(255,255,255,.05); }
        .adm-accordion-header::after { content:'▼'; font-size:.8em; opacity:.7; transition:transform .3s ease; }
        .adm-accordion-item.open .adm-accordion-header::after { transform:rotate(-180deg); color:var(--text); }
        .adm-accordion-content { padding:20px; display:none; border-top:1px solid rgba(255,255,255,.05); }

        .extended-color-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:10px; }
        .extended-color-item { background:var(--input-bg); padding:12px 15px; border-radius:12px; border:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; gap:10px; }
        .extended-color-item span { font-size:.85em; color:var(--text-muted); font-weight:500; }
        .extended-color-item input[type=color] { border:none; width:45px !important; height:35px !important; background:transparent; cursor:pointer; padding:0; border-radius:6px; margin:0; flex-shrink:0; }

        .song-select-container { max-height:300px; overflow-y:auto; margin-top:15px; border:1px solid var(--border-color); border-radius:16px; background:var(--input-bg); }
        .song-select-item { display:flex; align-items:center; padding:12px; border-bottom:1px solid rgba(255,255,255,.05); cursor:pointer; transition:.2s; }
        .song-select-item:hover { background:rgba(255,255,255,.05); }
        .song-select-item.selected { background:rgba(var(--primary-rgb),.2); }

        .theme-swatch-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(72px,1fr)); gap:14px; margin:10px 0 20px; }
        .theme-swatch { display:flex; flex-direction:column; align-items:center; gap:6px; cursor:pointer; }
        .theme-swatch .swatch-circle { width:38px; height:38px; border-radius:50%; border:2px solid transparent; box-shadow:0 2px 10px rgba(0,0,0,.4); transition:.2s; }
        .theme-swatch:hover .swatch-circle { transform:scale(1.08); }
        .theme-swatch.active .swatch-circle { border-color:var(--accent); box-shadow:0 0 0 3px rgba(var(--accent-rgb),.3); }
        .theme-swatch span { font-size:.65em; color:var(--text-muted); text-align:center; line-height:1.2; }

        .eq-row { display:grid; grid-template-columns:70px 1fr 55px; align-items:center; gap:12px; margin-bottom:14px; }
        .eq-label { font-size:.8em; color:var(--text-muted); font-weight:600; }
        .eq-val { font-size:.75em; color:var(--accent); font-family:monospace; text-align:right; }
        .eq-preset-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }

        .lyric-line { color:rgba(255,255,255,.5); font-size:1.05em; font-weight:600; padding:9px 0; cursor:pointer; transition:.2s; }
        .lyric-line:hover { color:rgba(255,255,255,.8); }
        .lyric-line.active { color:var(--player-text); font-size:1.25em; }
        .lyrics-status { color:rgba(255,255,255,.6); margin-top:20px; padding:0 10px; text-align:center; }
        .fp-btn.active { background:rgba(var(--primary-rgb),.3); }

        #full-player { position:fixed; top:100%; left:0; width:100%; height:100%; background:radial-gradient(circle at top right,var(--fp-gradient-1),var(--fp-gradient-2)); z-index:5000; transition:top .4s cubic-bezier(.2,.8,.2,1); display:flex; flex-direction:row; box-sizing:border-box; color:var(--player-text); overflow:hidden; }
        #full-player.active { top:0; }
        .fp-main { flex:1; min-width:0; display:flex; flex-direction:column; justify-content:space-between; padding:30px; box-sizing:border-box; }
        .fp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .fp-btn { background:none; border:none; cursor:pointer; padding:10px; border-radius:50%; flex-shrink:0; }
        .fp-art-container { flex-grow:1; display:flex; align-items:center; justify-content:center; margin:20px 0; max-height:45vh; }
        #fp-cover { width:100%; height:auto; aspect-ratio:1/1; object-fit:cover; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.5); max-width:350px; }
        .fp-info-area { text-align:left; margin-bottom:30px; padding:0 10px; overflow:hidden; }
        #fp-title { font-size:1.8em; font-weight:800; margin-bottom:5px; white-space:nowrap; overflow:hidden; width:100%; position:relative; mask-image:linear-gradient(to right,transparent 0%,black 5%,black 95%,transparent 100%); -webkit-mask-image:linear-gradient(to right,transparent 0%,black 5%,black 95%,transparent 100%); }
        #fp-title span { display:inline-block; }
        .scrolling-active { padding-left:100%; animation:marquee 12s linear infinite; }
        @keyframes marquee { 0%{transform:translate(0,0)} 100%{transform:translate(-100%,0)} }
        .fp-controls { display:flex; justify-content:space-between; align-items:center; padding:0 10px 20px 10px; width:100%; box-sizing:border-box; }

        /* Panneau latéral "File d'attente / Paroles" (façon YouTube Music) */
        #fp-sidebar { width:0; flex-shrink:0; background:rgba(0,0,0,.25); backdrop-filter:blur(20px); border-left:1px solid rgba(255,255,255,.08); box-sizing:border-box; overflow:hidden; display:flex; flex-direction:column; transition:width .3s cubic-bezier(.2,.8,.2,1); }
        #fp-sidebar.open { width:380px; }
        .fp-sidebar-tabs { display:flex; flex-shrink:0; border-bottom:1px solid rgba(255,255,255,.08); }
        .fp-tab-btn { flex:1; background:none; border:none; padding:20px 10px; color:var(--player-text); opacity:.55; cursor:pointer; font-weight:700; font-size:.8em; letter-spacing:.5px; white-space:nowrap; border-bottom:2px solid transparent; transition:.2s; }
        .fp-tab-btn:hover { opacity:.85; }
        .fp-tab-btn.active { opacity:1; border-bottom-color:var(--accent); color:var(--accent); }
        .fp-sidebar-content { flex:1; overflow-y:auto; padding:15px 20px; min-width:380px; }
        .fp-tab-pane { display:none; }
        .fp-tab-pane.active { display:block; }

        #mobile-bottom-nav { display:none; position:fixed; bottom:0; left:0; width:100%; background:var(--mob-nav-bg); backdrop-filter:blur(15px); border-top:1px solid rgba(255,255,255,.05); z-index:3000; justify-content:space-around; padding:10px 0 15px 0; height:70px; box-sizing:border-box; }
        .mob-nav-item { display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted); font-size:.7em; background:none; border:none; gap:5px; font-weight:600; width:20%; }
        .mob-nav-item svg { width:24px; height:24px; fill:currentColor; transition:transform .2s; }
        .mob-nav-item.active { color:var(--accent); }
        .mobile-settings-btn { display:none; }
        .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:25px; }
        .settings-grid label { display:flex; align-items:center; gap:10px; cursor:pointer; font-size:.95em; }
        .adm-genre-item { display:flex; justify-content:space-between; align-items:center; padding:8px; background:rgba(0,0,0,.2); margin-bottom:5px; border-radius:8px; border:1px solid var(--border-color); }

        @media(max-width:768px){
            body { padding-bottom:240px; }
            header { display:flex; justify-content:center; align-items:center; height:60px; padding:10px 20px; position:relative; }
            nav,.header-actions { display:none; }
            .mobile-settings-btn { display:block; position:absolute; right:20px; background:none; border:none; padding:5px; cursor:pointer; }
            .mobile-settings-btn svg { width:24px; height:24px; fill:var(--text-muted); }
            main { padding:20px; width:100%; box-sizing:border-box; }
            .track-item { grid-template-columns:50px 1fr auto; padding:12px 10px; gap:12px; }
            .track-index { display:none; }
            #player-bar { width:calc(100% - 20px); max-width:100%; bottom:80px; left:50%; transform:translateX(-50%); border-radius:16px; flex-direction:column; padding:12px 15px; box-sizing:border-box; }
            .player-info { width:100%; justify-content:flex-start; margin-bottom:5px; }
            .progress-container { width:100%; margin:8px 0 12px 0; }
            .controls { width:100%; justify-content:space-between; gap:0; padding:0 10px; box-sizing:border-box; }
            #masterPlay { width:45px; height:45px; }
            #player-bar .volume-container { display:none; }
            #queue-panel { width:100%; right:-100%; top:0; height:100%; z-index:2000; border-left:none; }
            .close-queue-mobile { display:block; }
            .modal-content { width:90%; margin:8% auto; padding:25px; }
            .settings-grid { grid-template-columns:1fr; }
            #mobile-bottom-nav { display:flex; }
            .extended-color-grid { grid-template-columns:1fr; }
            #full-player { flex-direction:column; }
            #fp-sidebar { position:absolute; inset:0; width:0; height:100%; z-index:10; border-left:none; }
            #fp-sidebar.open { width:100%; }
            .fp-sidebar-content { min-width:0; }
        }
    </style>
</head>
<body>

<?php if (!$user_id): ?>
<div style="max-width:350px;width:90%;margin:100px auto;text-align:center;">
    <div class="logo" style="font-size:3em;margin-bottom:30px;"><?php echo htmlspecialchars($site_name); ?></div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <?php if (isset($error)) echo "<p style='color:var(--danger);'>" . htmlspecialchars($error) . "</p>"; ?>
        <?php if (isset($info))  echo "<p style='color:#2ecc71;'>" . htmlspecialchars($info) . "</p>"; ?>
        <input type="text" name="username" placeholder="Utilisateur" required style="padding:15px;border-radius:12px;">
        <input type="password" name="password" placeholder="Mot de passe" required style="padding:15px;border-radius:12px;">
        <button type="submit" name="login" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:10px;padding:15px;">Connexion</button>
        <button type="submit" name="register" class="btn btn-outline" style="width:100%;justify-content:center;margin-top:15px;padding:15px;">Créer un compte</button>
    </form>
</div>
<?php else: ?>

<header>
    <div class="logo"><?php echo htmlspecialchars($site_name); ?> <?php if ($is_admin) echo "<small style='color:gold;font-size:10px;vertical-align:middle;'>ADMIN</small>"; ?></div>
    <nav>
        <span id="nav-accueil" class="active" onclick="showSection('accueil')">Bibliothèque</span>
        <span id="nav-playlists" onclick="showSection('playlists')">Playlists</span>
        <?php if ($is_admin): ?>
            <span class="admin-nav-btn" onclick="openModal('adminPanelModal')">⚙️ Panel Admin</span>
        <?php endif; ?>
    </nav>
    <div class="header-actions">
        <button class="btn btn-outline" onclick="toggleQueue()">File</button>
        <button class="btn btn-primary" onclick="openCreateModal()">+ Mix</button>
        <button class="btn btn-outline" onclick="openModal('uploadModal')">Upload</button>
        <button class="btn btn-outline" onclick="openModal('equalizerModal');renderEqSliders();" title="Égaliseur" style="padding:10px;border-radius:50%;">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M4 10h3v10H4V10zm6.5-6h3v16h-3V4zM17 14h3v6h-3v-6z"/></svg>
        </button>
        <button class="btn btn-outline" onclick="openModal('settingsModal')" style="padding:10px;border-radius:50%;">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19.4 13c0-.3.1-.6.1-1s0-.7-.1-1l2.1-1.7c.2-.2.2-.4.1-.6l-2-3.5c-.1-.2-.3-.3-.6-.2l-2.5 1c-.5-.4-1.1-.7-1.7-1l-.4-2.7c0-.2-.2-.4-.5-.4h-4c-.3 0-.5.2-.5.4l-.4 2.7c-.6.2-1.2.6-1.7 1l-2.5-1c-.2-.1-.5 0-.6.2l-2 3.5c-.1.2-.1.5.1.6L4.6 11c-.1.3-.1.6-.1 1s0 .7.1 1l-2.1 1.7c-.2.2-.2.4-.1.6l2 3.5c.1.2.3.3.6.2l2.5-1c.5.4 1.1.7 1.7 1l.4 2.7c0 .2.2.4.5.4h4c.3 0 .5-.2.5-.4l.4-2.7c.6-.2 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5c.1-.2.1-.5-.1-.6L19.4 13zM12 15.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
        </button>
        <a href="?logout=1" class="btn" style="color:var(--text-muted);">Sortir</a>
    </div>
    <button class="mobile-settings-btn" onclick="openModal('settingsModal')">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19.4 13c0-.3.1-.6.1-1s0-.7-.1-1l2.1-1.7c.2-.2.2-.4.1-.6l-2-3.5c-.1-.2-.3-.3-.6-.2l-2.5 1c-.5-.4-1.1-.7-1.7-1l-.4-2.7c0-.2-.2-.4-.5-.4h-4c-.3 0-.5.2-.5.4l-.4 2.7c-.6.2-1.2.6-1.7 1l-2.5-1c-.2-.1-.5 0-.6.2l-2 3.5c-.1.2-.1.5.1.6L4.6 11c-.1.3-.1.6-.1 1s0 .7.1 1l-2.1 1.7c-.2.2-.2.4-.1.6l2 3.5c.1.2.3.3.6.2l2.5-1c.5.4 1.1.7 1.7 1l.4 2.7c0 .2.2.4.5.4h4c.3 0 .5-.2.5-.4l.4-2.7c.6-.2 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5c.1-.2.1-.5-.1-.6L19.4 13zM12 15.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
    </button>
</header>

<?php if ($is_admin): ?>
<div id="adminPanelModal" class="modal"><div class="modal-content">
    <h2 style="margin-top:0;color:#e67e22;">Configuration Système</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="adm-accordion-item open">
            <div class="adm-accordion-header" onclick="toggleAccordion(this)">Général</div>
            <div class="adm-accordion-content" style="display:block;">
                <label>Nom de l'application</label>
                <input type="text" name="adm_site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
            </div>
        </div>

        <div class="adm-accordion-item">
            <div class="adm-accordion-header" onclick="toggleAccordion(this)">Thème Visuel</div>
            <div class="adm-accordion-content">
                <div class="extended-color-grid">
                    <div class="extended-color-item"><span>Arrière-plan</span><input type="color" name="adm_color_bg" value="<?php echo $color_bg; ?>"></div>
                    <div class="extended-color-item"><span>Panneaux</span><input type="color" name="adm_color_panel" value="<?php echo $color_panel; ?>"></div>
                    <div class="extended-color-item"><span>Primaire</span><input type="color" name="adm_color_primary" value="<?php echo $color_primary; ?>"></div>
                    <div class="extended-color-item"><span>Accent</span><input type="color" name="adm_color_accent" value="<?php echo $color_accent; ?>"></div>
                    <div class="extended-color-item"><span>Texte Principal</span><input type="color" name="adm_color_text" value="<?php echo $color_text; ?>"></div>
                    <div class="extended-color-item"><span>Texte Muted</span><input type="color" name="adm_color_text_muted" value="<?php echo $color_text_muted; ?>"></div>
                    <div class="extended-color-item"><span>Bordures</span><input type="color" name="adm_color_border" value="<?php echo $color_border; ?>"></div>
                    <div class="extended-color-item"><span>Fond Recherche</span><input type="color" name="adm_color_search_bg" value="<?php echo $color_search_bg; ?>"></div>
                    <div class="extended-color-item"><span>Gradient FP 1</span><input type="color" name="adm_color_fp_gradient_1" value="<?php echo $color_fp_gradient_1; ?>"></div>
                    <div class="extended-color-item"><span>Gradient FP 2</span><input type="color" name="adm_color_fp_gradient_2" value="<?php echo $color_fp_gradient_2; ?>"></div>
                </div>
                <label style="margin-top:12px;display:block;">Header (supporte rgba)</label>
                <input type="text" name="adm_color_header_bg" value="<?php echo htmlspecialchars($color_header_bg); ?>">
                <label>Mini-Lecteur (supporte rgba)</label>
                <input type="text" name="adm_color_player_bg" value="<?php echo htmlspecialchars($color_player_bg); ?>">
                <label>Nav Mobile (supporte rgba)</label>
                <input type="text" name="adm_color_mob_nav_bg" value="<?php echo htmlspecialchars($color_mob_nav_bg); ?>">
            </div>
        </div>

        <div class="adm-accordion-item">
            <div class="adm-accordion-header" onclick="toggleAccordion(this)">Assets Médias</div>
            <div class="adm-accordion-content">
                <label>Nouveau Favicon (.png/.ico)</label>
                <input type="file" name="adm_favicon" accept="image/png,image/x-icon">
                <label>Nouvelle Cover par défaut</label>
                <input type="file" name="adm_default_cover" accept="image/png">
            </div>
        </div>

        <div class="adm-accordion-item">
            <div class="adm-accordion-header" onclick="toggleAccordion(this)">Gestionnaire des Genres</div>
            <div class="adm-accordion-content">
                <label>Créer un genre</label>
                <input type="text" name="adm_new_genre" placeholder="ex: Ambient, Jazz...">
                <label style="font-weight:bold;display:block;margin-bottom:5px;">Genres actifs :</label>
                <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border-color);padding:10px;border-radius:10px;">
                    <?php foreach ($genresList as $g): ?>
                        <div class="adm-genre-item">
                            <span><?php echo htmlspecialchars($g); ?></span>
                            <a href="?delete_genre=<?php echo urlencode($g); ?>" style="color:var(--danger);text-decoration:none;font-weight:bold;" onclick="return confirm('Supprimer ce genre ?')">✕</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:15px;margin-top:25px;">
            <button type="button" class="btn" style="flex:1;border:1px solid rgba(255,255,255,.1);" onclick="closeModal('adminPanelModal')">Annuler</button>
            <button type="submit" name="save_admin_settings" class="btn btn-primary" style="flex:1;">Enregistrer</button>
        </div>
    </form>
</div></div>
<?php endif; ?>

<div id="queue-panel">
    <button class="close-queue-mobile" onclick="toggleQueue()">▼ Fermer la file</button>
    <h3 style="margin-top:0;color:var(--accent);font-size:1.2em;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:15px;">File d'attente</h3>
    <div id="queue-list" style="margin-top:15px;"><p style="color:var(--text-muted);font-size:.9em;">Aucune musique...</p></div>
</div>

<main id="accueil">
    <div class="controls-container">
        <h2 class="section-title">Toutes les pistes</h2>
        <div class="search-row">
            <div class="search-container">
                <input type="text" id="searchInput" class="search-input" placeholder="Rechercher titre, artiste..." onkeyup="onSearchInput()">
            </div>
            <div class="filter-wrapper" title="Trier">
                <div class="filter-icon-visual">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 4c0-.55.45-1 1-1h10c.55 0 1 .45 1 1v1.5c0 .28-.11.53-.3.71L10 10.9v5.2c0 .28-.11.53-.29.71l-2 2c-.18.18-.43.29-.71.29s-.53-.11-.71-.29A.996.996 0 0 1 6 18.1v-7.2L3.3 6.21A.996.996 0 0 1 3 5.5V4z"/><rect x="16" y="5" width="6" height="2" rx="1"/><rect x="16" y="11" width="6" height="2" rx="1"/><rect x="16" y="17" width="6" height="2" rx="1"/></svg>
                </div>
                <select id="sortSelect" class="filter-select-overlay" onchange="filterAndSortTracks()">
                    <option value="popular">Les plus écoutés</option>
                    <option value="date_desc">Ajouts récents</option>
                    <option value="date_asc">Ajouts anciens</option>
                    <option value="alpha_asc">Nom (A-Z)</option>
                    <option value="alpha_desc">Nom (Z-A)</option>
                    <option value="artist">Par Artiste</option>
                </select>
            </div>
        </div>
    </div>
    <div class="track-list" id="global-list"></div>
    <div id="load-more-trigger"></div>
</main>

<main id="playlists" style="display:none;">
    <h2 class="section-title" style="margin-bottom:25px;">Tes Mixs</h2>
    <div class="playlist-grid">
        <?php foreach ($all_playlists as $p): ?>
            <div class="playlist-card">
                <h3 style="margin-top:0;font-size:1.3em;"><?php echo htmlspecialchars($p['name']); ?></h3>
                <p style="font-size:.85em;color:var(--text-muted);margin-bottom:20px;">Créé par <strong><?php echo htmlspecialchars($p['creator']); ?></strong></p>
                <button class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:15px;" onclick="playPlaylist('<?php echo htmlspecialchars($p['song_ids']); ?>','<?php echo $p['id']; ?>')">▶ Écouter</button>
                <?php if ($p['creator_id'] == $user_id || $is_admin): ?>
                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-outline" style="flex:1;justify-content:center;font-size:.8em;" onclick='openEditModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Éditer</button>
                        <button class="btn btn-danger" style="flex:1;justify-content:center;border-radius:99px;" onclick="deletePlaylist(<?php echo (int)$p['id']; ?>)">Suppr</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div id="mobile-bottom-nav">
    <button class="mob-nav-item active" id="mob-nav-accueil" onclick="showSection('accueil')">
        <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>Biblio
    </button>
    <button class="mob-nav-item" id="mob-nav-playlists" onclick="showSection('playlists')">
        <svg viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>Mixs
    </button>
    <?php if ($is_admin): ?>
        <button class="mob-nav-item" onclick="openModal('adminPanelModal')" style="color:#e67e22;">
            <svg viewBox="0 0 24 24"><path d="M19.4 13c0-.3.1-.6.1-1s0-.7-.1-1l2.1-1.7c.2-.2.2-.4.1-.6l-2-3.5c-.1-.2-.3-.3-.6-.2l-2.5 1c-.5-.4-1.1-.7-1.7-1l-.4-2.7c0-.2-.2-.4-.5-.4h-4c-.3 0-.5.2-.5.4l-.4 2.7c-.6.2-1.2.6-1.7 1l-2.5-1c-.2-.1-.5 0-.6.2l-2 3.5c-.1.2-.1.5.1.6L4.6 11c-.1.3-.1.6-.1 1s0 .7.1 1l-2.1 1.7c-.2.2-.2.4-.1.6l2 3.5c.1.2.3.3.6.2l2.5-1c.5.4 1.1.7 1.7 1l.4 2.7c0 .2.2.4.5.4h4c.3 0 .5-.2.5-.4l.4-2.7c.6-.2 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5c.1-.2.1-.5-.1-.6L19.4 13zM12 15.5c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>Admin
        </button>
    <?php endif; ?>
    <button class="mob-nav-item" onclick="toggleQueue()">
        <svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>File
    </button>
    <button class="mob-nav-item" onclick="openModal('uploadModal')">
        <svg viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>Upload
    </button>
</div>

<!-- Modals -->
<div id="settingsModal" class="modal"><div class="modal-content">
    <h2 style="margin-top:0;">Filtres & Paramètres</h2>

    <p style="color:var(--text-muted);font-size:.9em;margin-bottom:10px;">Thème :</p>
    <div class="theme-swatch-grid" id="theme-swatch-grid"></div>

    <p style="color:var(--text-muted);font-size:.9em;margin-bottom:20px;">Genres à <strong style="color:var(--danger);">masquer</strong> :</p>
    <div class="settings-grid">
        <?php foreach ($genresList as $g): ?>
            <label><input type="checkbox" class="genre-filter-cb" data-genre="<?php echo htmlspecialchars($g); ?>" onchange="toggleGenreSetting('<?php echo htmlspecialchars($g); ?>',this.checked)"> <?php echo htmlspecialchars($g); ?></label>
        <?php endforeach; ?>
    </div>

    <button class="btn btn-outline" style="width:100%;justify-content:center;margin-bottom:12px;" onclick="closeModal('settingsModal');openModal('equalizerModal');renderEqSliders();">🎚 Ouvrir l'égaliseur</button>
    <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="closeModal('settingsModal')">Fermer</button>
</div></div>

<div id="equalizerModal" class="modal"><div class="modal-content">
    <h2 style="margin-top:0;">Égaliseur</h2>
    <label style="display:flex;align-items:center;gap:10px;margin-bottom:18px;cursor:pointer;">
        <input type="checkbox" id="eqEnableToggle" style="width:auto;margin:0;" onchange="initAudioGraph();setEqEnabled(this.checked)"> Activer l'égaliseur
    </label>
    <div class="eq-preset-row">
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('flat')">Plat</button>
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('bass')">Basses</button>
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('treble')">Aigus</button>
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('vocal')">Voix</button>
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('rock')">Rock</button>
        <button type="button" class="btn btn-outline" onclick="applyEqPreset('pop')">Pop</button>
    </div>
    <?php foreach (['Boost Basses','60 Hz','230 Hz','910 Hz','3.6 kHz','14 kHz'] as $i => $lbl): ?>
    <div class="eq-row">
        <span class="eq-label"><?php echo htmlspecialchars($lbl); ?></span>
        <input type="range" min="-12" max="12" step="1" value="0" id="eq-slider-<?php echo $i; ?>" class="vol-slider"
               oninput="initAudioGraph();setEqBand(<?php echo $i; ?>,parseFloat(this.value));document.getElementById('eq-val-<?php echo $i; ?>').innerText=this.value+' dB'">
        <span class="eq-val" id="eq-val-<?php echo $i; ?>">0 dB</span>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" style="width:100%;justify-content:center;margin-top:10px;" onclick="closeModal('equalizerModal')">Fermer</button>
</div></div>

<div id="uploadModal" class="modal"><div class="modal-content">
    <h2 style="margin-top:0;">Upload</h2>
    <form id="upload-form">
        <input type="text" id="upload-title" placeholder="Titre (auto-détecté si vide)">
        <input type="text" id="upload-artist" placeholder="Artiste (auto-détecté si vide)">
        <label style="font-size:.85em;color:var(--text-muted);display:block;margin-bottom:5px;">Genre</label>
        <select id="upload-genre">
            <?php foreach ($genresList as $g): ?>
                <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-size:.85em;color:var(--text-muted);display:block;margin-bottom:5px;">Fichier Audio</label>
        <input type="file" id="upload-music" accept="audio/*" required>
        <label style="font-size:.85em;color:var(--text-muted);display:block;margin-bottom:5px;">Cover (optionnel)</label>
        <input type="file" id="upload-cover" accept="image/*">
        <div style="display:flex;gap:15px;margin-top:20px;">
            <button type="button" class="btn" style="flex:1;justify-content:center;color:var(--text-muted);border:1px solid var(--border-color);" onclick="closeModal('uploadModal')">Annuler</button>
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Publier</button>
        </div>
    </form>
</div></div>

<div id="editTrackModal" class="modal"><div class="modal-content">
    <h2 style="margin-top:0;">Modifier la piste</h2>
    <form id="edit-track-form">
        <input type="hidden" id="edit-track-id">
        <input type="text" id="edit-track-title" placeholder="Titre" required>
        <input type="text" id="edit-track-artist" placeholder="Artiste">
        <label style="font-size:.85em;color:var(--text-muted);display:block;margin-bottom:5px;">Genre</label>
        <select id="edit-track-genre">
            <?php foreach ($genresList as $g): ?>
                <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-size:.85em;color:var(--text-muted);display:block;margin-bottom:5px;">Nouvelle cover</label>
        <input type="file" id="edit-track-cover" accept="image/*">
        <div style="display:flex;gap:15px;margin-top:20px;">
            <button type="button" class="btn" style="flex:1;justify-content:center;color:var(--text-muted);border:1px solid var(--border-color);" onclick="closeModal('editTrackModal')">Annuler</button>
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Enregistrer</button>
        </div>
    </form>
</div></div>

<div id="playlistModal" class="modal"><div class="modal-content">
    <h2 id="modal-playlist-title" style="margin-top:0;">Playlist</h2>
    <form id="playlist-form">
        <input type="hidden" id="form-playlist-id">
        <input type="text" id="form-playlist-name" placeholder="Nom du mix" required>
        <input type="text" id="playlist-search" placeholder="🔍 Rechercher..." onkeyup="filterPlaylistTracks()" style="margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;font-size:.85em;color:var(--text-muted);margin-bottom:10px;">
            <span>Sélectionnez les titres :</span><span id="selected-count">0 sélectionné(s)</span>
        </div>
        <div class="song-select-container">
            <?php foreach ($all_tracks as $t): ?>
                <div class="song-select-item" onclick="toggleSelection(this)" data-title="<?php echo strtolower(htmlspecialchars($t['title'])); ?>">
                    <input type="checkbox" class="song-cb" data-id="<?php echo $t['id']; ?>">
                    <img src="<?php echo htmlspecialchars($t['cover_url']); ?>" loading="lazy" style="width:40px;height:40px;border-radius:8px;margin-right:12px;object-fit:cover;" onerror="this.onerror=null;this.src='covers/default.png'">
                    <div style="flex:1;overflow:hidden;">
                        <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($t['title']); ?></div>
                        <div style="font-size:.85em;color:var(--text-muted);"><?php echo htmlspecialchars($t['artist']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:15px;margin-top:20px;">
            <button type="button" class="btn" style="flex:1;justify-content:center;color:var(--text-muted);border:1px solid var(--border-color);" onclick="closeModal('playlistModal')">Annuler</button>
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;">Enregistrer</button>
        </div>
    </form>
</div></div>

<!-- Player -->
<div id="player-bar">
    <div class="player-info" onclick="openSmartPlayer()" style="cursor:pointer;">
        <img src="covers/<?php echo htmlspecialchars($default_cover); ?>" id="player-cover" loading="lazy">
        <div style="overflow:hidden;flex:1;">
            <div id="play-title" style="font-weight:700;font-size:.95em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Prêt à écouter</div>
            <div id="play-status" style="font-size:.75em;color:var(--accent);margin-top:2px;">Arrêté</div>
        </div>
    </div>
    <div class="progress-container">
        <div class="progress-bg" id="progress-area"><div class="progress-fill" id="progress-bar"></div></div>
        <div style="display:flex;justify-content:space-between;font-size:.7em;color:var(--text-muted);margin-top:6px;font-family:monospace;">
            <span id="curr-time">0:00</span><span id="total-time">0:00</span>
        </div>
    </div>
    <div class="controls">
        <button class="control-btn" id="shuffleBtn" onclick="toggleShuffle()"><svg viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg></button>
        <button class="control-btn" onclick="prevTrack()"><svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></button>
        <button id="masterPlay" onclick="togglePlay()"><svg viewBox="0 0 24 24" style="margin-left:2px;"><path d="M8 5v14l11-7z"/></svg></button>
        <button class="control-btn" onclick="nextTrack()"><svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></button>
        <button class="control-btn" id="loopBtn" onclick="toggleLoop()" title="Lecture normale"><svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg><span class="loop-badge">1</span></button>
        <div class="volume-container">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="var(--text-muted)"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
            <input type="range" id="desktop-vol" class="vol-slider" min="0" max="1" step="0.01" value="1">
        </div>
    </div>
</div>

<div id="full-player">
    <div class="fp-main">
        <div class="fp-header">
            <button class="fp-btn" onclick="closeFullPlayer()"><svg viewBox="0 0 24 24" style="width:30px;height:30px;fill:var(--player-text);"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></button>
            <span style="font-size:.8em;letter-spacing:1px;color:var(--text-muted);font-weight:600;">LECTURE EN COURS</span>
            <div style="display:flex;gap:6px;">
                <button class="fp-btn" id="lyricsBtn" onclick="toggleFpTab('lyrics')" title="Paroles"><svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:var(--player-text);"><path d="M20 2H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h9v2H6V9zm6 5H6v-2h6v2zm5-6H6V6h11v2z"/></svg></button>
                <button class="fp-btn" id="fpQueueBtn" onclick="toggleFpTab('queue')" title="File d'attente"><svg viewBox="0 0 24 24" style="width:24px;height:24px;fill:var(--player-text);"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg></button>
            </div>
        </div>
        <div class="fp-art-container"><img src="covers/<?php echo htmlspecialchars($default_cover); ?>" id="fp-cover" loading="lazy"></div>
        <div class="fp-info-area">
            <div id="fp-title"><span id="fp-title-text">Titre</span></div>
            <div id="fp-artist" style="font-size:1.1em;color:var(--accent);font-weight:500;">Artiste</div>
        </div>
        <div class="fp-progress-wrapper">
            <div class="progress-bg" id="fp-progress-area" style="height:6px;background:rgba(255,255,255,.2);"><div class="progress-fill" id="fp-progress-bar" style="background:linear-gradient(90deg,var(--primary),var(--accent));"></div></div>
            <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:.85em;color:var(--text-muted);font-family:monospace;"><span id="fp-curr-time">0:00</span><span id="fp-total-time">0:00</span></div>
            <div class="volume-container fp-volume-container">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="var(--player-text)"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                <input type="range" id="mobile-vol" class="vol-slider" min="0" max="1" step="0.01" value="1">
            </div>
        </div>
        <div class="fp-controls">
            <button class="control-btn" id="fp-shuffleBtn" onclick="toggleShuffle()" style="transform:scale(1.2);"><svg viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg></button>
            <button class="control-btn" onclick="prevTrack()" style="transform:scale(1.5);"><svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></button>
            <button id="fp-masterPlay" onclick="togglePlay()" style="background:white;border-radius:50%;width:75px;height:75px;border:none;display:flex;align-items:center;justify-content:center;box-shadow:0 0 40px rgba(255,255,255,.2);">
                <svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;margin-left:4px;"><path d="M8 5v14l11-7z"/></svg>
            </button>
            <button class="control-btn" onclick="nextTrack()" style="transform:scale(1.5);"><svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></button>
            <button class="control-btn" id="fp-loopBtn" onclick="toggleLoop()" style="transform:scale(1.2);position:relative;" title="Lecture normale"><svg viewBox="0 0 24 24"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg><span class="loop-badge">1</span></button>
        </div>
    </div>
    <div id="fp-sidebar">
        <div class="fp-sidebar-tabs">
            <button class="fp-tab-btn active" id="fpTabQueueBtn" onclick="openFpTab('queue')">File d'attente</button>
            <button class="fp-tab-btn" id="fpTabLyricsBtn" onclick="openFpTab('lyrics')">Paroles</button>
        </div>
        <div class="fp-sidebar-content">
            <div id="fp-queue-tab" class="fp-tab-pane active">
                <div id="fp-queue-list"><p style="color:var(--text-muted);">File vide...</p></div>
            </div>
            <div id="fp-lyrics-tab" class="fp-tab-pane">
                <div id="lyrics-content"><p class="lyrics-status">Aucune piste en cours.</p></div>
            </div>
        </div>
    </div>
</div>

<audio id="mainAudio"></audio>

<script>
    const ALL_MUSIC_DATA   = <?php echo json_encode($all_tracks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const CURRENT_USER_ID  = <?php echo json_encode($user_id); ?>;
    const IS_ADMIN         = <?php echo json_encode($is_admin); ?>;
    // Couleurs par défaut du site (panneau admin), utilisées uniquement pour
    // afficher un aperçu fidèle du swatch "Site (Défaut)" dans le thème.
    const SERVER_PRIMARY   = <?php echo json_encode($color_primary); ?>;
    const SERVER_ACCENT    = <?php echo json_encode($color_accent); ?>;
    // Identifiants réinjectés côté client pour parler directement à api.php,
    // exactement comme le fait le client Android (PurpleClient.postRequest) :
    // api.php n'a pas de session, chaque appel authentifié doit les fournir.
    const API_AUTH = {
        username: <?php echo json_encode($username); ?>,
        password: <?php echo json_encode($api_pw); ?>
    };

    async function apiCall(action, params = {}) {
        const body = new URLSearchParams({ ...params, username: API_AUTH.username, password: API_AUTH.password });
        try {
            const res = await fetch('api.php?action=' + encodeURIComponent(action), { method: 'POST', body });
            return await res.json();
        } catch (e) {
            return { status: 'error', message: 'Impossible de contacter api.php.' };
        }
    }
    async function apiCallForm(action, formData) {
        formData.append('username', API_AUTH.username);
        formData.append('password', API_AUTH.password);
        try {
            const res = await fetch('api.php?action=' + encodeURIComponent(action), { method: 'POST', body: formData });
            return await res.json();
        } catch (e) {
            return { status: 'error', message: 'Impossible de contacter api.php.' };
        }
    }

    const audio        = document.getElementById('mainAudio');
    const progressBar  = document.getElementById('progress-bar');
    const progressArea = document.getElementById('progress-area');
    const masterPlay   = document.getElementById('masterPlay');
    const playTitle    = document.getElementById('play-title');
    const playCover    = document.getElementById('player-cover');
    const playStatus   = document.getElementById('play-status');
    const queueList    = document.getElementById('queue-list');
    const queuePanel   = document.getElementById('queue-panel');

    let CURRENT_VIEW_DATA = []; let renderedCount = 0; const RENDER_CHUNK = 30;
    let originalQueue = []; let queue = []; let currentIndex = 0; let loopMode = 0; let isShuffle = false;
    let currentPlaylistId = null; let currentSection = 'accueil';
    let hiddenGenres = JSON.parse(localStorage.getItem('hiddenGenres') || '[]');

    const playIcon  = '<svg viewBox="0 0 24 24" style="margin-left:2px;"><path d="M8 5v14l11-7z"/></svg>';
    const pauseIcon = '<svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';

    const desktopVol = document.getElementById('desktop-vol');
    const mobileVol  = document.getElementById('mobile-vol');

    function toggleAccordion(header) {
        const item = header.parentElement;
        const content = item.querySelector('.adm-accordion-content');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.adm-accordion-item').forEach(el => {
            el.classList.remove('open');
            el.querySelector('.adm-accordion-content').style.display = 'none';
        });
        if (!isOpen) { item.classList.add('open'); content.style.display = 'block'; }
    }

    function updateVolume(val) {
        audio.volume = val; desktopVol.value = val; mobileVol.value = val;
        localStorage.setItem('purpleMusicVolume', val);
        const p = val * 100;
        const bg = `linear-gradient(90deg,var(--accent) ${p}%,rgba(255,255,255,.2) ${p}%)`;
        desktopVol.style.background = bg; mobileVol.style.background = bg;
    }
    desktopVol.addEventListener('input', e => updateVolume(e.target.value));
    mobileVol.addEventListener('input',  e => updateVolume(e.target.value));

    function escapeHTML(str) {
        if (str == null) return '';
        return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    let searchTimeout;
    function onSearchInput() { clearTimeout(searchTimeout); searchTimeout = setTimeout(filterAndSortTracks, 250); }

    function updateUrl() {
        const params = new URLSearchParams();
        if (currentSection !== 'accueil') params.set('page', currentSection);
        if (queue[currentIndex]?.id) params.set('v', queue[currentIndex].id);
        if (currentPlaylistId) params.set('list', currentPlaylistId);
        window.history.pushState({}, '', window.location.pathname + '?' + params.toString());
    }

    function toggleGenreSetting(genre, isChecked) {
        if (isChecked) { if (!hiddenGenres.includes(genre)) hiddenGenres.push(genre); }
        else { hiddenGenres = hiddenGenres.filter(g => g !== genre); }
        localStorage.setItem('hiddenGenres', JSON.stringify(hiddenGenres));
        filterAndSortTracks();
    }

    function renderTracksChunk() {
        const listContainer = document.getElementById('global-list');
        const chunk = CURRENT_VIEW_DATA.slice(renderedCount, renderedCount + RENDER_CHUNK);
        if (renderedCount === 0) listContainer.innerHTML = '';
        if (chunk.length === 0 && renderedCount === 0) {
            listContainer.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted);">Aucune piste trouvée.</div>'; return;
        }
        const fragment = document.createDocumentFragment();
        chunk.forEach((t, index) => {
            const idx = renderedCount + index + 1;
            const safeTitle  = escapeHTML(t.title);
            const safeArtist = escapeHTML(t.artist);
            const safeGenre  = escapeHTML(t.genre || 'Autre');
            const safeCover  = escapeHTML(t.cover_url);
            const jsTitle    = safeTitle.replace(/'/g,"\\'");
            const jsArtist   = safeArtist.replace(/'/g,"\\'");
            const jsGenre    = safeGenre.replace(/'/g,"\\'");
            let editButtons  = '';
            if (t.uploader_id == CURRENT_USER_ID || IS_ADMIN) {
                editButtons = `
                    <button class="btn btn-outline" style="font-size:.7em;padding:6px 10px;border-radius:8px;" onclick="openEditTrackModal(${t.id},'${jsTitle}','${jsArtist}','${jsGenre}')">✎</button>
                    <button class="btn btn-danger" style="border-radius:8px;" onclick="deleteTrack(${t.id})">✕</button>`;
            }
            const div = document.createElement('div'); div.className = 'track-item';
            div.innerHTML = `
                <div class="track-index">${idx}</div>
                <img src="${safeCover}" loading="lazy" class="mini-cover" onerror="this.onerror=null;this.src='covers/default.png'">
                <div style="cursor:pointer;overflow:hidden;" onclick="playTrackById(${t.id})">
                    <div style="font-weight:700;font-size:1.05em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;">${safeTitle}</div>
                    <div style="font-size:.85em;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        ${safeArtist} <span style="opacity:.6;font-size:.9em;">• ${safeGenre} • ▶ ${t.play_count||0}</span>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">${editButtons}</div>`;
            fragment.appendChild(div);
        });
        listContainer.appendChild(fragment); renderedCount += chunk.length;
    }

    function filterAndSortTracks() {
        const term  = document.getElementById('searchInput').value.toLowerCase();
        const sort  = document.getElementById('sortSelect').value;
        let filtered = ALL_MUSIC_DATA.filter(t => {
            if (hiddenGenres.includes(t.genre || 'Autre')) return false;
            return t.title.toLowerCase().includes(term) || t.artist.toLowerCase().includes(term);
        });
        filtered.sort((a, b) => {
            if (sort === 'popular')    return (b.play_count||0) - (a.play_count||0) || b.id - a.id;
            if (sort === 'date_desc')  return b.id - a.id;
            if (sort === 'date_asc')   return a.id - b.id;
            if (sort === 'alpha_asc')  return a.title.localeCompare(b.title);
            if (sort === 'alpha_desc') return b.title.localeCompare(a.title);
            if (sort === 'artist')     return a.artist.localeCompare(b.artist);
            return 0;
        });
        CURRENT_VIEW_DATA = filtered; renderedCount = 0; renderTracksChunk();
    }

    const _observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && renderedCount < CURRENT_VIEW_DATA.length) renderTracksChunk();
    }, { rootMargin: '200px' });

    document.addEventListener('DOMContentLoaded', () => {
        const savedVol = localStorage.getItem('purpleMusicVolume');
        updateVolume(savedVol !== null ? savedVol : 1);
        document.querySelectorAll('.genre-filter-cb').forEach(cb => {
            if (hiddenGenres.includes(cb.dataset.genre)) cb.checked = true;
        });
        _observer.observe(document.getElementById('load-more-trigger'));
        filterAndSortTracks();
        const p = new URLSearchParams(window.location.search);
        if (p.get('page')) showSection(p.get('page'), false);
        if (p.get('list')) currentPlaylistId = p.get('list');
        if (p.get('v'))    playTrackById(p.get('v'), false);

        renderThemeSwatches();
        const savedTheme = localStorage.getItem('theme_base');
        if (savedTheme) applyTheme(savedTheme);
        renderEqSliders();

        document.getElementById('upload-form').addEventListener('submit', handleUploadSubmit);
        document.getElementById('edit-track-form').addEventListener('submit', handleEditTrackSubmit);
        document.getElementById('playlist-form').addEventListener('submit', handlePlaylistSubmit);
    });

    window.onpopstate = () => window.location.reload();

    function formatTime(s) {
        if (isNaN(s) || !isFinite(s)) return '0:00';
        return Math.floor(s/60) + ':' + String(Math.floor(s%60)).padStart(2,'0');
    }
    function toggleQueue() { queuePanel.classList.toggle('open'); }
    function openSmartPlayer() {
        document.getElementById('full-player').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeFullPlayer() { document.getElementById('full-player').classList.remove('active'); document.body.style.overflow = 'auto'; }

    function updateQueueUI() {
        const containers = [queueList, document.getElementById('fp-queue-list')].filter(Boolean);
        if (!queue.length) {
            containers.forEach(c => c.innerHTML = '<p style="color:var(--text-muted);">File vide...</p>');
            return;
        }
        containers.forEach(c => c.innerHTML = '');
        queue.forEach((track, index) => {
            containers.forEach(container => {
                const div = document.createElement('div'); div.className = `queue-item ${index === currentIndex ? 'active' : ''}`;
                div.innerHTML = `
                    <img src="${escapeHTML(track.cover_url)}" loading="lazy" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
                    <div style="flex:1;overflow:hidden;">
                        <div style="font-size:.9em;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHTML(track.title)}</div>
                        <div style="font-size:.75em;color:var(--text-muted);">${escapeHTML(track.artist)}</div>
                    </div>
                    ${index === currentIndex ? '<span style="color:var(--accent);font-size:1.5em;">•</span>' : ''}`;
                div.onclick = () => { currentIndex = index; loadTrack(true); };
                container.appendChild(div);
            });
        });
    }

    function playTrackById(id, autoPlay = true) {
        if (!currentPlaylistId) {
            originalQueue = [...CURRENT_VIEW_DATA];
            queue = isShuffle ? shuffleArray([...originalQueue]) : [...originalQueue];
            currentIndex = queue.findIndex(t => t.id == id);
        } else {
            let i = queue.findIndex(t => t.id == id);
            if (i === -1) { currentPlaylistId = null; return playTrackById(id, autoPlay); }
            currentIndex = i;
        }
        if (currentIndex === -1) currentIndex = 0;
        loadTrack(autoPlay);
        if (autoPlay && window.innerWidth > 768 && !queuePanel.classList.contains('open')) toggleQueue();
    }

    async function playPlaylist(ids, pId = null) {
        const idList = String(ids).split(',').map(Number).filter(Boolean);
        let data = idList.map(id => ALL_MUSIC_DATA.find(t => t.id === id)).filter(Boolean);
        if (hiddenGenres.length) data = data.filter(t => !hiddenGenres.includes(t.genre || 'Autre'));
        if (!data.length) { alert('Aucune musique disponible.'); return; }
        currentPlaylistId = pId; originalQueue = [...data];
        queue = isShuffle ? shuffleArray([...data]) : [...data];
        currentIndex = 0; loadTrack(true);
        if (window.innerWidth > 768 && !queuePanel.classList.contains('open')) toggleQueue();
    }

    function loadTrack(autoPlay = true) {
        if (!queue[currentIndex]) return;
        const track = queue[currentIndex]; audio.src = track.stream_url;
        apiCall('increment_play', { track_id: track.id }).catch(() => {});
        track.play_count = (parseInt(track.play_count) || 0) + 1;
        const g = ALL_MUSIC_DATA.find(t => t.id == track.id); if (g) g.play_count = track.play_count;

        playTitle.innerText = track.title;
        playCover.src       = track.cover_url;
        playStatus.innerText = track.artist || 'Artiste inconnu';

        const fpTitle = document.getElementById('fp-title');
        fpTitle.innerHTML = `<span id="fp-title-text">${escapeHTML(track.title)}</span>`;
        document.getElementById('fp-artist').innerText = track.artist || 'Artiste inconnu';
        document.getElementById('fp-cover').src = track.cover_url;

        const titleSpan = document.getElementById('fp-title-text');
        titleSpan.classList.remove('scrolling-active');
        if (titleSpan.scrollWidth > fpTitle.clientWidth) titleSpan.classList.add('scrolling-active');

        ['curr-time','fp-curr-time'].forEach(id => document.getElementById(id).innerText = '0:00');
        ['total-time','fp-total-time'].forEach(id => document.getElementById(id).innerText = '0:00');
        ['progress-bar','fp-progress-bar'].forEach(id => document.getElementById(id).style.width = '0%');

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: track.title, artist: track.artist || 'Purple Music',
                artwork: [{ src: track.cover_url, sizes: '96x96', type: 'image/png' }]
            });
        }
        updateUrl();
        if (lyricsVisible) fetchLyrics(track); else { currentParsedLyrics = []; }
        const fpPauseIcon = '<svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
        const fpPlayIcon  = '<svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;margin-left:4px;"><path d="M8 5v14l11-7z"/></svg>';
        if (autoPlay) {
            initAudioGraph();
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            audio.play().catch(e => console.error(e));
            masterPlay.innerHTML = pauseIcon;
            document.getElementById('fp-masterPlay').innerHTML = fpPauseIcon;
        } else {
            masterPlay.innerHTML = playIcon;
            document.getElementById('fp-masterPlay').innerHTML = fpPlayIcon;
        }
        updateQueueUI();
    }

    audio.onloadedmetadata = () => {
        const t = formatTime(audio.duration);
        document.getElementById('total-time').innerText = t;
        document.getElementById('fp-total-time').innerText = t;
    };

    function nextTrack() {
        if (loopMode === 2) { audio.currentTime = 0; audio.play(); return; }
        if (currentIndex < queue.length - 1) { currentIndex++; loadTrack(true); }
        else if (loopMode === 1) { currentIndex = 0; loadTrack(true); }
        else {
            audio.pause(); audio.currentTime = 0; masterPlay.innerHTML = playIcon;
            document.getElementById('fp-masterPlay').innerHTML = '<svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;margin-left:4px;"><path d="M8 5v14l11-7z"/></svg>';
        }
    }
    function prevTrack() { if (currentIndex > 0) { currentIndex--; loadTrack(true); } }

    function togglePlay() {
        if (!audio.src) return;
        initAudioGraph();
        if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
        const fpP = '<svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;margin-left:4px;"><path d="M8 5v14l11-7z"/></svg>';
        const fpPa = '<svg viewBox="0 0 24 24" style="width:35px;height:35px;fill:black;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
        if (audio.paused) { audio.play(); masterPlay.innerHTML = pauseIcon; document.getElementById('fp-masterPlay').innerHTML = fpPa; }
        else { audio.pause(); masterPlay.innerHTML = playIcon; document.getElementById('fp-masterPlay').innerHTML = fpP; }
    }

    function toggleShuffle() {
        isShuffle = !isShuffle;
        document.getElementById('shuffleBtn').classList.toggle('active', isShuffle);
        document.getElementById('fp-shuffleBtn').classList.toggle('active', isShuffle);
        if (queue.length) {
            const cur = queue[currentIndex];
            queue = isShuffle ? shuffleArray([...originalQueue]) : [...originalQueue];
            currentIndex = queue.findIndex(t => t.id === cur.id);
            if (currentIndex === -1) currentIndex = 0;
            updateQueueUI();
        }
    }

    function toggleLoop() {
        loopMode = (loopMode + 1) % 3;
        const active = loopMode > 0;
        const single = loopMode === 2;
        const label  = single ? 'Répéter le titre' : (active ? 'Répéter la file' : 'Lecture normale');
        document.querySelectorAll('#loopBtn, #fp-loopBtn').forEach(btn => {
            btn.classList.toggle('active', active);
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.querySelector('.loop-badge').classList.toggle('show', single);
        });
    }

    function shuffleArray(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }

    audio.ontimeupdate = () => {
        const pct = (audio.currentTime / audio.duration) * 100 || 0;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('fp-progress-bar').style.width = pct + '%';
        document.getElementById('curr-time').innerText = formatTime(audio.currentTime);
        document.getElementById('fp-curr-time').innerText = formatTime(audio.currentTime);
        if (audio.duration) {
            document.getElementById('total-time').innerText = formatTime(audio.duration);
            document.getElementById('fp-total-time').innerText = formatTime(audio.duration);
        }
        updateLyricsHighlight();
    };
    audio.onended = nextTrack;

    progressArea.onclick = e => {
        const r = progressArea.getBoundingClientRect(); audio.currentTime = ((e.clientX - r.left) / r.width) * audio.duration;
    };
    document.getElementById('fp-progress-area').onclick = e => {
        const r = document.getElementById('fp-progress-area').getBoundingClientRect();
        audio.currentTime = ((e.clientX - r.left) / r.width) * audio.duration;
    };

    function showSection(id, doUrl = true) {
        document.getElementById('accueil').style.display   = id === 'accueil'   ? 'block' : 'none';
        document.getElementById('playlists').style.display = id === 'playlists' ? 'block' : 'none';
        document.querySelectorAll('nav span').forEach(s => s.classList.remove('active'));
        document.getElementById('nav-' + id)?.classList.add('active');
        document.querySelectorAll('.mob-nav-item').forEach(s => s.classList.remove('active'));
        document.getElementById('mob-nav-' + id)?.classList.add('active');
        window.scrollTo(0, 0); currentSection = id; if (doUrl) updateUrl();
    }

    function openModal(id)  { document.getElementById(id).style.display = 'block'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function openEditTrackModal(id, title, artist, genre) {
        document.getElementById('edit-track-id').value     = id;
        document.getElementById('edit-track-title').value  = title;
        document.getElementById('edit-track-artist').value = artist;
        document.getElementById('edit-track-cover').value  = '';
        if (genre) document.getElementById('edit-track-genre').value = genre;
        openModal('editTrackModal');
    }

    // ── Suppression piste / playlist (via api.php) ──────────────
    async function deleteTrack(id) {
        if (!confirm('Supprimer cette piste ?')) return;
        const res = await apiCall('delete_track', { track_id: id });
        if (res.status === 'success') location.reload();
        else alert(res.message || 'Erreur lors de la suppression.');
    }
    async function deletePlaylist(id) {
        if (!confirm('Supprimer cette playlist ?')) return;
        const res = await apiCall('playlist_mod', { playlist_id: id, mode: 'delete' });
        if (res.status === 'success') location.reload();
        else alert(res.message || 'Erreur lors de la suppression.');
    }

    // ── Upload (via api.php) ─────────────────────────────────────
    let lastUploadTime = 0;
    async function handleUploadSubmit(e) {
        e.preventDefault();
        if (Date.now() - lastUploadTime < 15000) { alert('Patiente quelques secondes avant un nouvel upload.'); return; }
        const musicFile = document.getElementById('upload-music').files[0];
        if (!musicFile) { alert('Choisis un fichier audio.'); return; }
        const fd = new FormData();
        fd.append('title',  document.getElementById('upload-title').value);
        fd.append('artist', document.getElementById('upload-artist').value);
        fd.append('genre',  document.getElementById('upload-genre').value);
        fd.append('music',  musicFile);
        const coverFile = document.getElementById('upload-cover').files[0];
        if (coverFile) fd.append('cover', coverFile);

        const btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true; const oldLabel = btn.innerText; btn.innerText = 'Envoi…';
        lastUploadTime = Date.now();
        const res = await apiCallForm('upload', fd);
        if (res.status === 'success') { location.reload(); return; }
        btn.disabled = false; btn.innerText = oldLabel;
        alert(res.message || "Erreur lors de l'upload.");
    }

    // ── Édition piste (via api.php) ──────────────────────────────
    async function handleEditTrackSubmit(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('track_id',  document.getElementById('edit-track-id').value);
        fd.append('title',     document.getElementById('edit-track-title').value);
        fd.append('artist',    document.getElementById('edit-track-artist').value);
        fd.append('new_genre', document.getElementById('edit-track-genre').value);
        const coverFile = document.getElementById('edit-track-cover').files[0];
        if (coverFile) fd.append('new_cover', coverFile);
        const res = await apiCallForm('edit_track', fd);
        if (res.status === 'success') location.reload();
        else alert(res.message || 'Erreur lors de la modification.');
    }

    // ── Playlists (via api.php) ───────────────────────────────────
    // api.php n'a pas de mode "remplacer toute la liste" : on calcule le
    // delta entre la sélection d'origine et la nouvelle, puis on envoie
    // une suite d'appels add/remove — comme le ferait un client mobile.
    let editingPlaylistOriginalIds = [];
    async function handlePlaylistSubmit(e) {
        e.preventDefault();
        const name = document.getElementById('form-playlist-name').value.trim();
        const pid  = document.getElementById('form-playlist-id').value;
        const selectedIds = Array.from(document.querySelectorAll('.song-cb:checked')).map(cb => parseInt(cb.dataset.id, 10));
        const btn = e.target.querySelector('button[type=submit]');
        btn.disabled = true;
        try {
            if (!pid) {
                const createRes = await apiCall('playlist_create', { name });
                if (createRes.status !== 'success') { alert(createRes.message || 'Erreur.'); return; }
                const playlists = await fetch('api.php?action=playlists').then(r => r.json());
                const mine = Array.isArray(playlists)
                    ? playlists.filter(p => p.name === name && p.creator === API_AUTH.username)
                    : [];
                const created = mine.sort((a, b) => b.id - a.id)[0];
                if (created) {
                    for (const id of selectedIds) {
                        await apiCall('playlist_mod', { playlist_id: created.id, mode: 'add', track_id: id });
                    }
                }
            } else {
                const originalIds = editingPlaylistOriginalIds.map(Number);
                const toAdd    = selectedIds.filter(id => !originalIds.includes(id));
                const toRemove = originalIds.filter(id => !selectedIds.includes(id));
                const renameRes = await apiCall('playlist_mod', { playlist_id: pid, mode: 'rename', new_name: name });
                if (renameRes.status !== 'success') { alert(renameRes.message || 'Erreur.'); return; }
                for (const id of toAdd)    await apiCall('playlist_mod', { playlist_id: pid, mode: 'add', track_id: id });
                for (const id of toRemove) await apiCall('playlist_mod', { playlist_id: pid, mode: 'remove', track_id: id });
            }
            location.reload();
        } finally {
            btn.disabled = false;
        }
    }

    function filterPlaylistTracks() {
        const term = document.getElementById('playlist-search').value.toLowerCase();
        document.querySelectorAll('.song-select-item').forEach(item => {
            item.style.display = item.dataset.title.includes(term) ? 'flex' : 'none';
        });
    }

    function toggleSelection(div) {
        const cb = div.querySelector('input'); cb.checked = !cb.checked;
        cb.checked ? div.classList.add('selected') : div.classList.remove('selected');
        updateSelectedCount();
    }
    function updateSelectedCount() {
        document.getElementById('selected-count').innerText = document.querySelectorAll('.song-cb:checked').length + ' sélectionné(s)';
    }

    function openCreateModal() {
        document.getElementById('modal-playlist-title').innerText = 'Nouvelle Playlist';
        document.getElementById('form-playlist-id').value = '';
        document.getElementById('form-playlist-name').value = '';
        document.getElementById('playlist-search').value = '';
        editingPlaylistOriginalIds = [];
        document.querySelectorAll('.song-select-item').forEach(div => { div.classList.remove('selected'); div.style.display = 'flex'; });
        document.querySelectorAll('.song-cb').forEach(cb => cb.checked = false);
        updateSelectedCount(); openModal('playlistModal');
    }

    function openEditModal(p) {
        document.getElementById('modal-playlist-title').innerText = 'Modifier Playlist';
        document.getElementById('form-playlist-id').value = p.id;
        document.getElementById('form-playlist-name').value = p.name;
        document.getElementById('playlist-search').value = '';
        const ids = String(p.song_ids).split(',').filter(Boolean);
        editingPlaylistOriginalIds = ids;
        document.querySelectorAll('.song-select-item').forEach(div => {
            const cb = div.querySelector('.song-cb');
            cb.checked = ids.includes(cb.dataset.id);
            cb.checked ? div.classList.add('selected') : div.classList.remove('selected');
            div.style.display = 'flex';
        });
        updateSelectedCount(); openModal('playlistModal');
    }

    // ===========================================================
    //  ÉGALISEUR (Web Audio API) — équivalent web du 5-band EQ +
    //  bass boost du client Android (EqualizerManager.kt).
    // ===========================================================
    let audioCtx = null, eqNodes = null;
    const EQ_BANDS = [
        { freq: 100,   type: 'lowshelf' },
        { freq: 60,    type: 'peaking' },
        { freq: 230,   type: 'peaking' },
        { freq: 910,   type: 'peaking' },
        { freq: 3600,  type: 'peaking' },
        { freq: 14000, type: 'peaking' },
    ];
    const EQ_PRESETS = {
        flat:   [0,0,0,0,0,0],
        bass:   [8,6,3,0,0,0],
        treble: [0,0,0,2,5,8],
        vocal:  [-2,-3,1,4,3,0],
        rock:   [4,3,-2,-1,2,5],
        pop:    [2,4,3,0,-1,2],
    };
    let eqSettings = JSON.parse(localStorage.getItem('eqSettings') || 'null') || { enabled: false, gains: [0,0,0,0,0,0] };

    function initAudioGraph() {
        if (audioCtx) return;
        try {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            let node = audioCtx.createMediaElementSource(audio);
            eqNodes = EQ_BANDS.map((b, i) => {
                const f = audioCtx.createBiquadFilter();
                f.type = b.type; f.frequency.value = b.freq;
                f.gain.value = eqSettings.enabled ? (eqSettings.gains[i] || 0) : 0;
                if (b.type === 'peaking') f.Q.value = 1;
                node.connect(f); node = f; return f;
            });
            node.connect(audioCtx.destination);
        } catch (e) { console.warn("Égaliseur indisponible sur ce navigateur.", e); }
    }
    function setEqEnabled(on) {
        eqSettings.enabled = on;
        if (eqNodes) eqNodes.forEach((f, i) => { f.gain.value = on ? (eqSettings.gains[i] || 0) : 0; });
        localStorage.setItem('eqSettings', JSON.stringify(eqSettings));
        const toggle = document.getElementById('eqEnableToggle');
        if (toggle) toggle.checked = on;
    }
    function setEqBand(i, val) {
        eqSettings.gains[i] = val;
        if (eqSettings.enabled && eqNodes) eqNodes[i].gain.value = val;
        localStorage.setItem('eqSettings', JSON.stringify(eqSettings));
    }
    function applyEqPreset(name) {
        eqSettings.gains = [...EQ_PRESETS[name]];
        eqSettings.enabled = true;
        initAudioGraph();
        if (eqNodes) eqNodes.forEach((f, i) => { f.gain.value = eqSettings.gains[i]; });
        localStorage.setItem('eqSettings', JSON.stringify(eqSettings));
        renderEqSliders();
    }
    function renderEqSliders() {
        const toggle = document.getElementById('eqEnableToggle');
        if (toggle) toggle.checked = eqSettings.enabled;
        EQ_BANDS.forEach((b, i) => {
            const slider = document.getElementById('eq-slider-' + i);
            if (slider) slider.value = eqSettings.gains[i] || 0;
            const label = document.getElementById('eq-val-' + i);
            if (label) label.innerText = (eqSettings.gains[i] || 0) + ' dB';
        });
    }

    // ===========================================================
    //  THÈMES (préréglages du client Android — ThemeUtils.kt porté
    //  en JS). Un simple thème "de base" génère panneau/accent/
    //  bordures/texte via les mêmes formules HSL.
    // ===========================================================
    const THEME_PRESETS = [
        { name: 'Site (Défaut)', base: null },
        { name: 'White Mode',     base: '#FFFFFF' },
        { name: 'AMOLED',         base: '#000000' },
        { name: 'Vibrant Purple', base: '#4A148C' },
        { name: 'Electric Blue',  base: '#0D47A1' },
        { name: 'Deep Teal',      base: '#004D40' },
        { name: 'Cherry',         base: '#880E4F' },
        { name: 'Midnight',       base: '#0A0E1A' },
        { name: 'Forest',         base: '#0D140D' },
        { name: 'Crimson',        base: '#140D0D' },
        { name: 'Slate',          base: '#1A1A1B' },
        { name: 'Jet Black',      base: '#0A0A0A' },
        { name: 'Material',       base: '#121212' },
    ];

    function hexToRgb(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const num = parseInt(hex, 16);
        return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
    }
    function rgbToHex(r, g, b) {
        return '#' + [r, g, b].map(v => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('');
    }
    function hexToRgba(hex, a) { const { r, g, b } = hexToRgb(hex); return `rgba(${r},${g},${b},${a})`; }
    function rgbToHsl(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;
        if (max === min) { h = s = 0; }
        else {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                default: h = (r - g) / d + 4;
            }
            h /= 6;
        }
        return [h * 360, s, l];
    }
    function hslToRgb(h, s, l) {
        h /= 360; let r, g, b;
        if (s === 0) { r = g = b = l; }
        else {
            const hue2rgb = (p, q, t) => { if (t < 0) t += 1; if (t > 1) t -= 1; if (t < 1/6) return p + (q - p) * 6 * t; if (t < 1/2) return q; if (t < 2/3) return p + (q - p) * (2/3 - t) * 6; return p; };
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3); g = hue2rgb(p, q, h); b = hue2rgb(p, q, h - 1/3);
        }
        return [r * 255, g * 255, b * 255];
    }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function deriveAccent(hex) {
        if (hex.toLowerCase() === '#000000') return '#ffffff';
        if (hex.toLowerCase() === '#ffffff') return '#8e44ad';
        const { r, g, b } = hexToRgb(hex);
        let [h, s, l] = rgbToHsl(r, g, b);
        const light = l > 0.5;
        if (light) { s = clamp(s + 0.5, 0.6, 1.0); l = clamp(l - 0.4, 0.3, 0.5); }
        else       { s = clamp(s + 0.4, 0.5, 0.9); l = clamp(l + 0.5, 0.6, 0.85); }
        if (s < 0.1) { h = 270; s = 0.6; if (light) l = 0.4; }
        return rgbToHex(...hslToRgb(h, s, l));
    }
    // --primary sert de fond de bouton avec du texte blanc dessus (.btn-primary),
    // contrairement à --accent qui sert de texte/icône SUR le fond sombre — deux
    // rôles opposés qu'on ne peut pas partager. deriveAccent pousse volontiers vers
    // des teintes claires (jusqu'à l=0.85, voire blanc pur sur AMOLED) pour rester
    // lisible sur un fond noir ; ce même ton clair rendrait le texte blanc des
    // boutons illisible. derivePrimary vise donc toujours une teinte assez sombre
    // pour du texte blanc — vérifié via un vrai calcul de contraste WCAG plutôt
    // qu'une luminosité fixe, car certaines teintes (teal, vert) restent "claires"
    // à l'œil même à une luminosité HSL modérée.
    function relLuminance(hex) {
        const { r, g, b } = hexToRgb(hex);
        const lin = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
    }
    function contrastRatio(hexA, hexB) {
        const l1 = relLuminance(hexA) + 0.05, l2 = relLuminance(hexB) + 0.05;
        return Math.max(l1, l2) / Math.min(l1, l2);
    }
    function derivePrimary(hex) {
        const { r, g, b } = hexToRgb(hex);
        let [h, s] = rgbToHsl(r, g, b);
        if (s < 0.12) { h = 270; s = 0.55; }
        s = clamp(Math.max(s, 0.45), 0.45, 0.85);
        let l = 0.42;
        let candidate = rgbToHex(...hslToRgb(h, s, l));
        while (l > 0.1 && contrastRatio('#ffffff', candidate) < 4.5) {
            l -= 0.02;
            candidate = rgbToHex(...hslToRgb(h, s, l));
        }
        return candidate;
    }
    function derivePanel(hex) {
        if (hex.toLowerCase() === '#ffffff') return '#f5f5f7';
        const { r, g, b } = hexToRgb(hex);
        let [h, s, l] = rgbToHsl(r, g, b);
        l = l > 0.5 ? clamp(l - 0.05, 0, 1) : clamp(l + 0.05, 0, 1);
        return rgbToHex(...hslToRgb(h, s, l));
    }
    function deriveBorder(hex) {
        if (hex.toLowerCase() === '#ffffff') return '#e0e0e0';
        const { r, g, b } = hexToRgb(hex);
        let [h, s, l] = rgbToHsl(r, g, b);
        l = l > 0.5 ? clamp(l - 0.15, 0, 1) : clamp(l + 0.15, 0, 1);
        return rgbToHex(...hslToRgb(h, s, l));
    }
    function deriveTextMuted(hex) {
        const { r, g, b } = hexToRgb(hex);
        let [h, s, l] = rgbToHsl(r, g, b);
        const light = l > 0.5;
        s = clamp(s * 0.5, 0, 1); l = light ? 0.4 : 0.7;
        return rgbToHex(...hslToRgb(h, s, l));
    }
    function deriveText(hex) { const { r, g, b } = hexToRgb(hex); const [, , l] = rgbToHsl(r, g, b); return l > 0.5 ? '#1a1a1b' : '#e0e0e0'; }
    // Surface "élevée" à N crans de l'arrière-plan (inputs, hover, focus…) —
    // mêmes crans que derivePanel (2) mais paramétrable pour les autres surfaces.
    function deriveElevated(hex, steps) {
        const { r, g, b } = hexToRgb(hex);
        let [h, s, l] = rgbToHsl(r, g, b);
        const step = 0.025;
        l = l > 0.5 ? clamp(l - steps * step, 0, 1) : clamp(l + steps * step, 0, 1);
        return rgbToHex(...hslToRgb(h, s, l));
    }

    const THEME_VARS = ['--bg-dark','--bg-panel','--primary','--accent','--primary-rgb','--accent-rgb','--text','--text-muted','--border-color','--search-bg','--header-bg','--mob-nav-bg','--player-bg','--fp-gradient-1','--fp-gradient-2','--modal-bg','--input-bg','--elevated-bg','--player-text'];
    function applyTheme(baseHex) {
        const style = document.documentElement.style;
        if (!baseHex) {
            THEME_VARS.forEach(v => style.removeProperty(v));
            localStorage.removeItem('theme_base');
            renderThemeSwatches();
            return;
        }
        const panel    = derivePanel(baseHex);
        const primary  = derivePrimary(baseHex);
        const accent   = deriveAccent(baseHex);
        const border   = deriveBorder(baseHex);
        const muted    = deriveTextMuted(baseHex);
        const text     = deriveText(baseHex);
        const inputBg  = deriveElevated(baseHex, 1);
        const elevated = deriveElevated(baseHex, 3);
        const { r: pr, g: pg, b: pb } = hexToRgb(primary);
        const { r: ar, g: ag, b: ab } = hexToRgb(accent);
        style.setProperty('--bg-dark', baseHex);
        style.setProperty('--bg-panel', panel);
        style.setProperty('--primary', primary);
        style.setProperty('--accent', accent);
        style.setProperty('--primary-rgb', `${pr},${pg},${pb}`);
        style.setProperty('--accent-rgb', `${ar},${ag},${ab}`);
        style.setProperty('--text', text);
        style.setProperty('--text-muted', muted);
        style.setProperty('--border-color', border);
        style.setProperty('--search-bg', panel);
        style.setProperty('--header-bg', hexToRgba(panel, 0.85));
        style.setProperty('--mob-nav-bg', hexToRgba(panel, 0.95));
        style.setProperty('--player-bg', hexToRgba(panel, 0.85));
        style.setProperty('--fp-gradient-1', panel);
        style.setProperty('--fp-gradient-2', baseHex);
        style.setProperty('--modal-bg', panel);
        style.setProperty('--input-bg', inputBg);
        style.setProperty('--elevated-bg', elevated);
        style.setProperty('--player-text', text);
        localStorage.setItem('theme_base', baseHex);
        renderThemeSwatches();
    }
    function renderThemeSwatches() {
        const grid = document.getElementById('theme-swatch-grid');
        if (!grid) return;
        const savedBase = localStorage.getItem('theme_base');
        grid.innerHTML = '';
        THEME_PRESETS.forEach(p => {
            const isActive = p.base === savedBase || (p.base === null && !savedBase);
            const div = document.createElement('div');
            div.className = 'theme-swatch' + (isActive ? ' active' : '');
            const bg = p.base || `linear-gradient(135deg,${SERVER_PRIMARY},${SERVER_ACCENT})`;
            div.innerHTML = `<div class="swatch-circle" style="background:${bg};"></div><span>${escapeHTML(p.name)}</span>`;
            div.onclick = () => applyTheme(p.base);
            grid.appendChild(div);
        });
    }

    // ===========================================================
    //  PAROLES SYNCHRONISÉES (lrclib.net — comme AppViewModel.kt)
    // ===========================================================
    let lyricsVisible = false;
    let currentParsedLyrics = [];
    let lyricsRequestId = 0;
    let currentFpTab = 'queue';

    // Panneau latéral du lecteur plein écran (File d'attente / Paroles),
    // façon YouTube Music : un seul panneau, deux onglets.
    function openFpTab(tab) {
        currentFpTab = tab;
        document.getElementById('fp-sidebar').classList.add('open');
        document.getElementById('fpTabQueueBtn').classList.toggle('active', tab === 'queue');
        document.getElementById('fpTabLyricsBtn').classList.toggle('active', tab === 'lyrics');
        document.getElementById('fp-queue-tab').classList.toggle('active', tab === 'queue');
        document.getElementById('fp-lyrics-tab').classList.toggle('active', tab === 'lyrics');
        lyricsVisible = (tab === 'lyrics');
        if (lyricsVisible && queue[currentIndex]) fetchLyrics(queue[currentIndex]);
        syncFpHeaderButtons();
    }
    function toggleFpTab(tab) {
        const sidebar = document.getElementById('fp-sidebar');
        if (sidebar.classList.contains('open') && currentFpTab === tab) {
            sidebar.classList.remove('open');
            lyricsVisible = false;
            syncFpHeaderButtons();
        } else {
            openFpTab(tab);
        }
    }
    function syncFpHeaderButtons() {
        const open = document.getElementById('fp-sidebar').classList.contains('open');
        document.getElementById('lyricsBtn').classList.toggle('active', open && currentFpTab === 'lyrics');
        document.getElementById('fpQueueBtn').classList.toggle('active', open && currentFpTab === 'queue');
    }

    function parseLrc(text) {
        const lines = [];
        const re = /\[(\d{2}):(\d{2})(?:[.:](\d{1,3}))?\](.*)/;
        text.split('\n').forEach(line => {
            const m = line.match(re);
            if (!m) return;
            const min = parseInt(m[1], 10), sec = parseInt(m[2], 10);
            const ms  = m[3] ? parseInt(m[3].padEnd(3, '0'), 10) : 0;
            const content = m[4].trim();
            if (content) lines.push({ time: min * 60 + sec + ms / 1000, text: content });
        });
        lines.sort((a, b) => a.time - b.time);
        return lines;
    }

    function renderLyricsRaw(lrc) {
        const panel = document.getElementById('lyrics-content');
        const parsed = parseLrc(lrc);
        if (!parsed.length) {
            panel.innerHTML = `<p class="lyrics-status" style="white-space:pre-line;">${escapeHTML(lrc)}</p>`;
            currentParsedLyrics = [];
            return;
        }
        currentParsedLyrics = parsed;
        panel.innerHTML = parsed.map(l => `<p class="lyric-line" data-time="${l.time}" onclick="seekLyric(${l.time})">${escapeHTML(l.text)}</p>`).join('');
    }

    async function fetchLyrics(track) {
        const panel = document.getElementById('lyrics-content');
        currentParsedLyrics = [];
        const myReq = ++lyricsRequestId;
        const cacheKey = 'lyrics_' + track.id;
        const cached = localStorage.getItem(cacheKey);
        if (cached !== null) { renderLyricsRaw(cached); return; }

        panel.innerHTML = '<p class="lyrics-status">Chargement des paroles…</p>';
        try {
            const url = 'https://lrclib.net/api/get?artist_name=' + encodeURIComponent(track.artist || '') + '&track_name=' + encodeURIComponent(track.title || '');
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (myReq !== lyricsRequestId) return;
            if (res.status === 404) { panel.innerHTML = '<p class="lyrics-status">Aucune parole disponible.</p>'; return; }
            if (!res.ok) throw new Error('http ' + res.status);
            const json = await res.json();
            const lrc = json.syncedLyrics || json.plainLyrics || '';
            if (!lrc) { panel.innerHTML = '<p class="lyrics-status">Aucune parole disponible.</p>'; return; }
            try { localStorage.setItem(cacheKey, lrc); } catch (e) {}
            renderLyricsRaw(lrc);
        } catch (e) {
            if (myReq === lyricsRequestId) panel.innerHTML = '<p class="lyrics-status">Erreur lors du chargement des paroles.</p>';
        }
    }

    function seekLyric(t) { audio.currentTime = t; if (audio.paused) togglePlay(); }

    function updateLyricsHighlight() {
        if (!lyricsVisible || !currentParsedLyrics.length) return;
        const t = audio.currentTime;
        let activeIdx = -1;
        for (let i = 0; i < currentParsedLyrics.length; i++) {
            if (currentParsedLyrics[i].time <= t) activeIdx = i; else break;
        }
        const lines = document.querySelectorAll('#lyrics-content .lyric-line');
        lines.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
        if (activeIdx >= 0 && lines[activeIdx]) lines[activeIdx].scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
</script>

<?php endif; ?>
</body>
</html>
