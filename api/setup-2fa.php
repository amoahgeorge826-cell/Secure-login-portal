<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/totp-helper.php';
require_login();

$stmt = $pdo->prepare('SELECT id, username, totp_secret, is_2fa_enabled, totp_last_counter FROM users WHERE id = ?');
$stmt->execute([(int) $_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$noticeType = 'info';
$enabled = (int) $user['is_2fa_enabled'] === 1;
$secret = (string) ($user['totp_secret'] ?? '');

if (!$enabled && $secret === '') {
    $secret = generateSecretKey();
    $update = $pdo->prepare('UPDATE users SET totp_secret = ? WHERE id = ?');
    $update->execute([$secret, (int) $user['id']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$enabled) {
    $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
    if (!csrf_is_valid()) {
        $message = 'Your form expired or could not be verified. Refresh and try again.';
        $noticeType = 'error';
    } elseif (($_POST['action'] ?? '') === 'reset-secret') {
        $secret = generateSecretKey();
        $update = $pdo->prepare('UPDATE users SET totp_secret = ? WHERE id = ? AND is_2fa_enabled = 0');
        $update->execute([$secret, (int) $user['id']]);
        $message = 'A new setup key is ready. Remove any old pending entry from your authenticator and add this key instead.';
        $noticeType = 'success';
    } else {
        $counter = findTotpCounter($secret, $code);
        if ($counter !== null) {
            $update = $pdo->prepare(
                'UPDATE users SET is_2fa_enabled = 1, totp_last_counter = ?
                 WHERE id = ? AND (totp_last_counter IS NULL OR totp_last_counter < ?)'
            );
            $update->execute([$counter, (int) $user['id'], $counter]);
            if ($update->rowCount() === 1) {
                $enabled = true;
                $message = 'Two-factor authentication is now enabled for your account.';
                $noticeType = 'success';
            } else {
                $message = 'That authenticator code was already used. Wait for the next code and try again.';
                $noticeType = 'error';
            }
        } else {
            $message = 'That code did not match. Check the current code in your authenticator app and try again.';
            $noticeType = 'error';
        }
    }
}

$issuer = 'Secure Portal';
$account = rawurlencode($issuer) . ':' . rawurlencode($user['username']);
$provisioningUri = 'otpauth://totp/' . $account . '?' . http_build_query(
    ['secret' => $secret, 'issuer' => $issuer],
    '',
    '&',
    PHP_QUERY_RFC3986
);

render_header('Two-factor security', 'Protect your account', 'Add this account manually in a TOTP authenticator, then confirm a current six-digit code. Two-factor protection turns on only after the code is verified.');
?>
<div class="content-stack">
    <section class="panel">
        <div class="panel-heading">
            <div><h2>Authenticator app setup</h2><p>Works with Google Authenticator, Microsoft Authenticator, Authy, and other apps that support TOTP.</p></div>
            <span class="role-badge <?php echo $enabled ? 'role-badge-user' : 'role-badge-admin'; ?>">
                <span class="status-dot" aria-hidden="true"></span><?php echo $enabled ? 'Enabled' : 'Not enabled'; ?>
            </span>
        </div>
        <?php render_notice($message, $noticeType); ?>

        <?php if ($enabled): ?>
            <div class="tip-card">
                <span class="tip-icon" aria-hidden="true">✓</span>
                <div><h3>Your account has an extra layer of protection</h3><p>The code used to confirm setup cannot be reused. At your next sign-in, enter a fresh six-digit code from your authenticator app after entering your password.</p></div>
            </div>
            <p class="section-spacer"><a class="btn btn-secondary" href="dashboard.php">Return to your workspace</a></p>
        <?php else: ?>
            <div class="security-steps">
                <div class="step-card"><span class="step-number">1</span><strong>Open your authenticator</strong><span>Use any app that supports time-based one-time passwords (TOTP).</span></div>
                <div class="step-card"><span class="step-number">2</span><strong>Add this account</strong><span>Choose “Enter a setup key” or “Add manually.” Do not select a time-based counter.</span></div>
                <div class="step-card"><span class="step-number">3</span><strong>Confirm a code</strong><span>Enter the current six-digit code to finish enabling 2FA.</span></div>
            </div>

            <div class="field">
                <label for="totp-secret">Your private setup key</label>
                <div class="secret-box">
                    <code class="secret-code" id="totp-secret" tabindex="0"><?php echo h($secret); ?></code>
                    <button class="btn btn-secondary" type="button" data-copy-target="totp-secret">Copy key</button>
                </div>
                <small class="field-hint">Enter the key exactly as shown. Choose time-based (TOTP), 6 digits, and a 30-second interval. Keep this key private.</small>
            </div>
            <div class="authenticator-settings">
                <div><span>Account name</span><strong><?php echo h($issuer . ':' . $user['username']); ?></strong></div>
                <div><span>Code type</span><strong>Time-based (TOTP)</strong></div>
                <div><span>Code format</span><strong>6 digits · 30 seconds</strong></div>
            </div>
            <details class="provisioning-details">
                <summary>Manual setup details</summary>
                <ul class="detail-list">
                    <li>Issuer: <?php echo h($issuer); ?></li>
                    <li>Type: Time-based (TOTP), 6 digits, 30-second interval</li>
                </ul>
                <code><?php echo h($provisioningUri); ?></code>
            </details>

            <form class="reset-secret-form" method="POST" action="setup-2fa.php"
                  data-confirm="Replace your pending authenticator setup key? The old key will stop working.">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="reset-secret">
                <button class="btn btn-secondary" type="submit">Generate a new setup key</button>
                <small class="field-hint">Use this only if you lost the pending key or want to restart setup before enabling 2FA.</small>
            </form>

            <form class="inline-form auth-form" method="POST" action="setup-2fa.php">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <div class="field">
                    <label for="code">Six-digit verification code</label>
                    <input class="code-input" id="code" name="code" type="text" inputmode="numeric"
                           pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code"
                           placeholder="000000" required>
                    <small class="field-hint">Enter the current code with no spaces. If it expires while you are typing, wait for the next code.</small>
                </div>
                <button class="btn" type="submit">Verify and enable 2FA <span aria-hidden="true">→</span></button>
            </form>
        <?php endif; ?>
    </section>

    <aside class="tip-card">
        <span class="tip-icon" aria-hidden="true">!</span>
        <div><h3>Important recovery tip</h3><p>Before confirming, make sure the authenticator account is saved on a device you control. This simple demo does not provide recovery codes or self-service account recovery.</p></div>
    </aside>
</div>
<?php render_footer(); ?>
