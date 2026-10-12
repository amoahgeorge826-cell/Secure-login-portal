<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once dirname(__DIR__) . '/config.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function render_header(string $title, string $eyebrow, string $description): void
{
    $isAuthenticated = isset($_SESSION['user_id'], $_SESSION['role']);
    $role = $isAuthenticated ? (string) $_SESSION['role'] : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101b32">
    <title><?php echo h($title); ?> | Secure Portal</title>
    <link rel="stylesheet" href="style.css">
    <script src="app.js" defer></script>
</head>
<body>
    <div class="site-frame">
        <header class="topbar">
            <a class="brand" href="<?php echo $isAuthenticated ? 'dashboard.php' : 'login.php'; ?>">
                <span class="brand-mark" aria-hidden="true">S</span>
                <span class="brand-copy"><strong>Secure Portal</strong><small>Identity & access</small></span>
            </a>
            <nav class="topnav" aria-label="Main navigation">
                <?php if ($isAuthenticated): ?>
                    <a href="dashboard.php">Overview</a>
                    <?php if ($role === 'Admin'): ?>
                        <a href="dashboard.php#admin-tools">Administration</a>
                        <a href="dashboard.php#account-directory">Members</a>
                    <?php else: ?>
                        <a href="dashboard.php#my-profile">My profile</a>
                    <?php endif; ?>
                    <a href="setup-2fa.php">Security</a>
                    <span class="role-pill <?php echo $role === 'Admin' ? 'role-admin' : 'role-user'; ?>">
                        <span class="status-dot" aria-hidden="true"></span><?php echo h($role); ?> access
                    </span>
                    <form class="nav-logout" method="POST" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                        <button class="nav-action" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a href="login.php">Sign in</a>
                    <a class="nav-action" href="register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </header>

        <main class="main-content">
            <section class="page-heading">
                <span class="eyebrow"><?php echo h($eyebrow); ?></span>
                <h1><?php echo h($title); ?></h1>
                <p><?php echo h($description); ?></p>
            </section>
<?php
}

function render_footer(): void
{
    ?>
            <footer class="site-footer">
                <span>Secure Portal <span aria-hidden="true">·</span> Private by design</span>
                <a href="GUIDE.md">Help &amp; getting started</a>
            </footer>
        </main>
    </div>
</body>
</html>
<?php
}

function render_notice(string $message, string $type = 'info'): void
{
    if ($message === '') {
        return;
    }
    ?>
    <div class="notice notice-<?php echo h($type); ?>" role="<?php echo $type === 'error' ? 'alert' : 'status'; ?>">
        <span class="notice-icon" aria-hidden="true"><?php echo $type === 'error' ? '!' : ($type === 'success' ? '✓' : 'i'); ?></span>
        <p><?php echo h($message); ?></p>
    </div>
    <?php
}
