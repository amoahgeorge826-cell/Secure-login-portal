<?php
function generateSecretKey(int $length = 32): string
{
    $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';

    if ($length < 32 || $length > 64) {
        throw new InvalidArgumentException('TOTP secrets must contain between 32 and 64 Base32 characters.');
    }

    for ($i = 0; $i < $length; $i++) {
        $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
    }

    return $secret;
}

function base32_decode(string $string): string
{
    $string = strtoupper(rtrim($string, '='));
    if ($string === '' || !preg_match('/\A[A-Z2-7]+\z/', $string)) {
        return '';
    }

    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0, $length = strlen($string); $i < $length; $i++) {
        $value = strpos($map, $string[$i]);
        $buffer = ($buffer << 5) | $value;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    return $output;
}

function findTotpCounter(string $secret, string $code, int $discrepancy = 1): ?int
{
    if (!preg_match('/\A[0-9]{6}\z/', $code) || $discrepancy < 0 || $discrepancy > 2) {
        return null;
    }

    $decodedSecret = base32_decode($secret);
    if ($decodedSecret === '') {
        return null;
    }

    $timeSlice = intdiv(time(), 30);
    for ($offset = -$discrepancy; $offset <= $discrepancy; $offset++) {
        $counter = $timeSlice + $offset;
        if ($counter < 0) {
            continue;
        }

        $calculatedCode = calculateCode($decodedSecret, $counter);
        if (hash_equals($calculatedCode, $code)) {
            return $counter;
        }
    }

    return null;
}

function verifyTotpCode(string $secret, string $code, int $discrepancy = 1): bool
{
    return findTotpCounter($secret, $code, $discrepancy) !== null;
}

function calculateCode(string $binarySecret, int $timeSlice): string
{
    $time = pack('N2', intdiv($timeSlice, 4294967296), $timeSlice % 4294967296);
    $hash = hash_hmac('sha1', $time, $binarySecret, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;

    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}
