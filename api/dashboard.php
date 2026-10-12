<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_login();

$stmt = $pdo->prepare('SELECT id, username, email, role, totp_secret, is_2fa_enabled, created_at FROM users WHERE id = ?');
$stmt->execute([(int) $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['Admin', 'User'], true)) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$isAdmin = $user['role'] === 'Admin';
$totalAccounts = 0;
$adminCount = 0;
$protectedAccounts = 0;
$accountList = [];
if ($isAdmin) {
    $stats = $pdo->query(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN role = 'Admin' THEN 1 ELSE 0 END) AS admins,
                SUM(CASE WHEN is_2fa_enabled = 1 THEN 1 ELSE 0 END) AS protected
         FROM users"
    )->fetch();
    $totalAccounts = (int) $stats['total'];
    $adminCount = (int) $stats['admins'];
    $protectedAccounts = (int) $stats['protected'];
    $accountList = $pdo->query('SELECT username, email, role, created_at FROM users ORDER BY id DESC LIMIT 100')->fetchAll();
}

render_header(
    $isAdmin ? 'Administration overview' : 'Member home',
    $isAdmin ? 'Administrator workspace' : 'Your personal workspace',
    $isAdmin
        ? 'Monitor portal access, review account roles, and run administrator tools from one place.'
        : 'Manage your personal sign-in security and see the account details associated with your membership.'
);
?>
<div class="content-stack dashboard-<?php echo $isAdmin ? 'admin' : 'user'; ?>">
    <section class="panel role-hero">
        <div class="panel-heading">
            <div class="dashboard-welcome">
                <div class="avatar" aria-hidden="true"><?php echo h(mb_strtoupper(mb_substr($user['username'], 0, 1, 'UTF-8'), 'UTF-8')); ?></div>
                <div class="welcome-copy">
                    <span class="metric-label"><?php echo $isAdmin ? 'PORTAL ADMINISTRATOR' : 'MEMBER ACCOUNT'; ?></span>
                    <h2><?php echo $isAdmin ? 'Good to see you' : 'Welcome back'; ?>, <?php echo h($user['username']); ?></h2>
                    <p><?php echo $isAdmin ? 'You have administrator access to portal management tools.' : 'You are signed in to your personal member workspace.'; ?></p>
                </div>
            </div>
            <span class="role-badge <?php echo $isAdmin ? 'role-badge-admin' : 'role-badge-user'; ?>">
                <span class="status-dot" aria-hidden="true"></span>
                <?php echo $isAdmin ? 'Administrator' : 'Standard user'; ?>
            </span>
        </div>

        <div class="metric-grid">
            <article class="metric-card">
                <span class="metric-label"><?php echo $isAdmin ? 'PORTAL ACCOUNTS' : 'YOUR SECURITY'; ?></span>
                <div class="metric-value">
                    <?php if ($isAdmin): ?>
                        <?php echo h((string) $totalAccounts); ?> registered
                    <?php else: ?>
                        <span class="status-dot <?php echo (int) $user['is_2fa_enabled'] === 1 ? 'dot-enabled' : 'dot-disabled'; ?>"></span>
                        <?php echo (int) $user['is_2fa_enabled'] === 1 ? '2FA enabled' : '2FA not enabled'; ?>
                    <?php endif; ?>
                </div>
                <p class="metric-caption"><?php echo $isAdmin ? h((string) $adminCount) . ' administrator account(s)' : 'Add a second sign-in step for more protection.'; ?></p>
            </article>
            <article class="metric-card">
                <span class="metric-label"><?php echo $isAdmin ? 'ACCOUNTS WITH 2FA' : 'MEMBER SINCE'; ?></span>
                <div class="metric-value">
                    <?php echo $isAdmin
                        ? h((string) $protectedAccounts) . ' of ' . h((string) $totalAccounts)
                        : h(date('M Y', strtotime($user['created_at']))); ?>
                </div>
                <p class="metric-caption"><?php echo $isAdmin ? 'Members with authenticator verification enabled.' : 'Your membership is active in the portal.'; ?></p>
            </article>
            <article class="metric-card">
                <span class="metric-label"><?php echo $isAdmin ? 'YOUR SECURITY' : 'ACCESS LEVEL'; ?></span>
                <?php if ($isAdmin): ?>
                    <div class="metric-value <?php echo (int) $user['is_2fa_enabled'] === 1 ? 'security-state-enabled' : 'security-state-disabled'; ?>">
                        <span class="status-dot"></span><?php echo (int) $user['is_2fa_enabled'] === 1 ? 'Protected' : 'Not enabled'; ?>
                    </div>
                    <p class="metric-caption"><?php echo (int) $user['is_2fa_enabled'] === 1 ? 'Your administrator sign-in has 2FA.' : 'Protect your administrator sign-in.'; ?></p>
                <?php else: ?>
                    <div class="metric-value">Standard user</div>
                    <p class="metric-caption">Member permissions are active</p>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <section class="panel security-panel">
        <div class="panel-heading">
            <div>
                <span class="metric-label"><?php echo $isAdmin ? 'PRIVILEGED ACCOUNT' : 'PERSONAL SETTINGS'; ?></span>
                <h2><?php echo $isAdmin ? 'Secure your admin access' : 'Your account security'; ?></h2>
                <p><?php echo $isAdmin
                    ? 'Administrator accounts can manage portal data. Keep yours protected with an authenticator.'
                    : 'Only you can manage your personal two-factor sign-in setting.'; ?></p>
            </div>
            <a class="btn btn-secondary" href="setup-2fa.php"><?php echo (int) $user['is_2fa_enabled'] === 1 ? 'View security status' : 'Set up two-factor'; ?> <span aria-hidden="true">→</span></a>
        </div>
        <div class="tip-card">
            <span class="tip-icon" aria-hidden="true">✦</span>
            <div><h3>Why enable two-factor authentication?</h3><p>A second, short-lived code helps protect your account if someone learns your password. Use a trusted authenticator app and keep your device time set automatically.</p></div>
        </div>
    </section>

    <?php if ($isAdmin): ?>
        <section class="panel admin-tools-panel" id="admin-tools">
            <div class="panel-heading">
                <div>
                    <span class="metric-label">ADMINISTRATION</span>
                    <h2>Portal management</h2>
                    <p>These controls are available only to administrator accounts.</p>
                </div>
                <span class="role-badge role-badge-admin"><span class="status-dot" aria-hidden="true"></span>Admin only</span>
            </div>
            <div class="admin-callout">
                <div class="admin-callout-head"><span class="admin-icon" aria-hidden="true">◆</span><h3>Database backup</h3></div>
                <p>Create a consistent SQLite backup. The backup is stored outside the public web directory.</p>
                <div class="admin-actions">
                    <form method="POST" action="backup.php">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                        <button class="btn" type="submit">Create database backup <span aria-hidden="true">→</span></button>
                    </form>
                    <a href="GUIDE.md">Read administrator guide</a>
                </div>
            </div>
        </section>

        <section class="panel" id="account-directory">
            <div class="panel-heading">
                <div><span class="metric-label">MEMBERS</span><h2>Account directory</h2><p>Review account roles and membership dates.</p></div>
                <span class="metric-label"><?php echo h((string) $totalAccounts); ?> total</span>
            </div>
            <?php if ($accountList): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach ($accountList as $account): ?>
                            <tr>
                                <td><?php echo h($account['username']); ?></td>
                                <td><?php echo h($account['email']); ?></td>
                                <td><span class="role-tag <?php echo $account['role'] === 'Admin' ? 'role-tag-admin' : ''; ?>"><?php echo h($account['role']); ?></span></td>
                                <td><?php echo h(date('M j, Y', strtotime($account['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalAccounts > count($accountList)): ?>
                    <p class="field-hint">Showing the most recent 100 accounts.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">No accounts are available yet.</div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="panel" id="my-profile">
            <div class="panel-heading">
                <div><span class="metric-label">YOUR PROFILE</span><h2>Account details</h2><p>Your personal profile information. Contact an administrator if these details need to change.</p></div>
                <span class="role-badge role-badge-user"><span class="status-dot" aria-hidden="true"></span>Member</span>
            </div>
            <dl class="profile-grid">
                <div><dt>Username</dt><dd><?php echo h($user['username']); ?></dd></div>
                <div><dt>Email address</dt><dd><?php echo h($user['email']); ?></dd></div>
                <div><dt>Account role</dt><dd><span class="role-tag">Standard user</span></dd></div>
                <div><dt>Member since</dt><dd><?php echo h(date('F j, Y', strtotime($user['created_at']))); ?></dd></div>
            </dl>
            <div class="tip-card section-spacer">
                <span class="tip-icon" aria-hidden="true">i</span>
                <div><h3>About your access</h3><p>Your member workspace includes your profile and security settings. Account directory and database management tools are restricted to administrators.</p></div>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
