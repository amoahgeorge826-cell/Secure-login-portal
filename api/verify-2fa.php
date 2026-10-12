<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/totp-helper.php';

if (!isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['temp_user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Your verification form expired. Refresh and sign in again.';
    } elseif (($_POST['action'] ?? '') === 'cancel') {
        unset(
            $_SESSION['temp_user_id'],
            $_SESSION['temp_username'],
            $_SESSION['temp_role'],
            $_SESSION['totp_secret']
        );
        header('Location: login.php');
        exit;
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT id, username, role, totp_secret, is_2fa_enabled
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $stmt->closeCursor();

            $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
            $counter = $user && (int) $user['is_2fa_enabled'] === 1
                && is_string($user['totp_secret'])
                ? findTotpCounter($user['totp_secret'], $code)
                : null;

            if (
                $user &&
                in_array($user['role'], ['Admin', 'User'], true) &&
                $counter !== null
            ) {
                $consume = $pdo->prepare(
                    'UPDATE users SET totp_last_counter = ?
                     WHERE id = ? AND is_2fa_enabled = 1
                       AND (totp_last_counter IS NULL OR totp_last_counter < ?)'
                );
                $consume->execute([$counter, $userId, $counter]);
                $accepted = $consume->rowCount() === 1;
                $consume->closeCursor();
            } else {
                $accepted = false;
            }

            if ($accepted) {
                $pdo->commit();

                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                unset(
                    $_SESSION['temp_user_id'],
                    $_SESSION['temp_username'],
                    $_SESSION['temp_role'],
                    $_SESSION['totp_secret']
                );

                header('Location: dashboard.php');
                exit;
            }

            $pdo->rollBack();
            $error = $counter !== null
                ? 'That authenticator code has already been used. Wait for the next code and try again.'
                : 'That code was not accepted. Check your authenticator and try again.';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Two-factor verification failed: ' . $e->getMessage());
            http_response_code(500);
            $error = 'We could not verify your sign-in right now. Please try again.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT username, role, is_2fa_enabled FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$pendingUser = $stmt->fetch();

if (
    !$pendingUser ||
    !in_array($pendingUser['role'], ['Admin', 'User'], true) ||
    (int) $pendingUser['is_2fa_enabled'] !== 1
) {
    unset(
        $_SESSION['temp_user_id'],
        $_SESSION['temp_username'],
        $_SESSION['temp_role'],
        $_SESSION['totp_secret']
    );
    header('Location: login.php');
    exit;
}

$isPendingAdmin = $pendingUser['role'] === 'Admin';
render_header('Verify your identity', 'Second step', 'Confirm the current code from your authenticator app. Your account role is checked again after the code is verified.');
?>
<div class="auth-layout">
    <section class="auth-card">
        <div class="form-heading">
            <h2>Enter your verification code</h2>
            <p>Your password was accepted for <strong><?php echo h($pendingUser['username']); ?></strong>. Complete this step to continue.</p>
        </div>
        <div class="pending-role-card">
            <span class="pending-role-icon" aria-hidden="true"><?php echo $isPendingAdmin ? '◆' : '●'; ?></span>
            <span><small>ACCOUNT ROLE</small><strong><?php echo $isPendingAdmin ? 'Administrator' : 'Standard user'; ?></strong></span>
            <span class="role-badge <?php echo $isPendingAdmin ? 'role-badge-admin' : 'role-badge-user'; ?>">
                <?php echo $isPendingAdmin ? 'Admin workspace' : 'Member workspace'; ?>
            </span>
        </div>
        <p class="field-hint role-verification-note">This role is read from your account record. The authenticator code verifies your account; it does not grant or change a role.</p>
        <?php render_notice($error, 'error'); ?>
        <form class="auth-form" method="POST" action="verify-2fa.php">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <div class="field">
                <label for="code">Authenticator code</label>
                <input class="code-input" id="code" name="code" type="text" inputmode="numeric"
                       pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code"
                       placeholder="000000" autofocus required>
                <small class="field-hint">Enter the six digits currently displayed in your authenticator app.</small>
            </div>
            <button class="btn btn-full" type="submit">Verify and continue to <?php echo $isPendingAdmin ? 'admin' : 'member'; ?> workspace <span aria-hidden="true">→</span></button>
        </form>
        <form class="cancel-auth-form" method="POST" action="verify-2fa.php">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="text-button" type="submit">Cancel and return to sign in</button>
        </form>
    </section>
    <aside>
        <div class="auth-side">
            <div>
                <span class="side-label">Role-aware verification</span>
                <h2>One more step to your workspace.</h2>
                <p>A valid code confirms the authenticator connected to your account. The system then loads your current role and opens the matching workspace.</p>
                <ul class="benefit-list">
                    <li><span class="check-mark" aria-hidden="true">✓</span> Role is checked against your account</li>
                    <li><span class="check-mark" aria-hidden="true">✓</span> Admin access requires a valid OTP</li>
                    <li><span class="check-mark" aria-hidden="true">✓</span> Each code can only be used once</li>
                </ul>
            </div>
            <div class="side-note">Your OTP proves control of your authenticator. Administrator permissions are determined by the account role stored on the server—not by the OTP itself.</div>
        </div>
        <div class="tip-card auth-tip">
            <span class="tip-icon" aria-hidden="true">i</span>
            <div><h3>Code not working?</h3><p>Check that your phone’s date and time are set automatically. Wait for a new code before trying again.</p></div>
        </div>
    </aside>
</div>
<?php render_footer(); ?>
