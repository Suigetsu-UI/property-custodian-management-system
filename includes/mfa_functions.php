<?php

require_once __DIR__ . '/../config/config.php';

const PCMS_MFA_TOTP_DIGITS = 6;
const PCMS_MFA_TOTP_PERIOD = 30;
const PCMS_MFA_TOTP_WINDOW = 1;
const PCMS_MFA_SECRET_BYTES = 20;
const PCMS_MFA_DEFAULT_ISSUER = 'Smart AssetTrack';

/*
|--------------------------------------------------------------------------
| MFA Cryptography and TOTP Primitives
|--------------------------------------------------------------------------
| This helper intentionally contains no HTML, redirects, session changes,
| enrollment workflow, or login-flow decisions. Database transaction scope
| remains the responsibility of the caller.
*/

function assertMfaRuntimeReady(): void
{
    $requiredFunctions = [
        'sodium_crypto_secretbox',
        'sodium_crypto_secretbox_open',
        'sodium_memzero',
    ];
    $requiredConstants = [
        'SODIUM_CRYPTO_SECRETBOX_KEYBYTES',
        'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES',
        'SODIUM_CRYPTO_SECRETBOX_MACBYTES',
    ];

    if (!extension_loaded('sodium')) {
        throw new RuntimeException('MFA requires the Sodium PHP extension.');
    }

    foreach ($requiredFunctions as $function) {
        if (!function_exists($function)) {
            throw new RuntimeException(
                'MFA requires the Sodium secretbox functions.'
            );
        }
    }

    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException(
                'MFA requires the Sodium secretbox constants.'
            );
        }
    }
}

function getMfaEncryptionKey(): string
{
    assertMfaRuntimeReady();

    $encodedKey = getenv('MFA_ENCRYPTION_KEY');

    if ($encodedKey === false || $encodedKey === '') {
        throw new RuntimeException(
            'Missing required environment variable: MFA_ENCRYPTION_KEY.'
        );
    }

    $decodedKey = base64_decode($encodedKey, true);

    if (
        $decodedKey === false ||
        !hash_equals(base64_encode($decodedKey), $encodedKey) ||
        strlen($decodedKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        throw new RuntimeException(
            'MFA_ENCRYPTION_KEY must be canonical Base64 encoding of 32 bytes.'
        );
    }

    return $decodedKey;
}

function encryptMfaSecret(string $plainSecret): string
{
    if ($plainSecret === '') {
        throw new InvalidArgumentException('The MFA secret cannot be empty.');
    }

    $key = getMfaEncryptionKey();

    try {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox(
            $plainSecret,
            $nonce,
            $key
        );

        return 'v1:'
            . base64_encode($nonce)
            . ':'
            . base64_encode($ciphertext);
    } finally {
        sodium_memzero($key);
    }
}

function decryptMfaSecret(string $encryptedSecret): string
{
    $parts = explode(':', $encryptedSecret, 3);

    if (count($parts) !== 3 || $parts[0] !== 'v1') {
        throw new RuntimeException('Invalid encrypted MFA secret format.');
    }

    $nonce = decodeCanonicalMfaBase64($parts[1]);
    $ciphertext = decodeCanonicalMfaBase64($parts[2]);

    if (
        strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ||
        strlen($ciphertext) < SODIUM_CRYPTO_SECRETBOX_MACBYTES
    ) {
        throw new RuntimeException('Invalid encrypted MFA secret format.');
    }

    $key = getMfaEncryptionKey();

    try {
        $plainSecret = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $key
        );
    } finally {
        sodium_memzero($key);
    }

    if ($plainSecret === false) {
        throw new RuntimeException(
            'Encrypted MFA secret authentication failed.'
        );
    }

    return $plainSecret;
}

function decodeCanonicalMfaBase64(string $encoded): string
{
    if ($encoded === '') {
        throw new RuntimeException('Invalid encrypted MFA secret format.');
    }

    $decoded = base64_decode($encoded, true);

    if (
        $decoded === false ||
        !hash_equals(base64_encode($decoded), $encoded)
    ) {
        throw new RuntimeException('Invalid encrypted MFA secret format.');
    }

    return $decoded;
}

function generateMfaSecret(): string
{
    return encodeMfaBase32(random_bytes(PCMS_MFA_SECRET_BYTES));
}

function encodeMfaBase32(string $binary): string
{
    if ($binary === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bitsInBuffer = 0;
    $encoded = '';

    foreach (unpack('C*', $binary) as $byte) {
        $buffer = ($buffer << 8) | $byte;
        $bitsInBuffer += 8;

        while ($bitsInBuffer >= 5) {
            $bitsInBuffer -= 5;
            $encoded .= $alphabet[($buffer >> $bitsInBuffer) & 31];
        }

        $buffer = $bitsInBuffer === 0
            ? 0
            : $buffer & ((1 << $bitsInBuffer) - 1);
    }

    if ($bitsInBuffer > 0) {
        $encoded .= $alphabet[($buffer << (5 - $bitsInBuffer)) & 31];
    }

    return $encoded;
}

function decodeMfaBase32(string $encoded): string
{
    $normalized = strtoupper(trim($encoded));

    if (
        $normalized === '' ||
        !preg_match('/\A[A-Z2-7]+\z/', $normalized) ||
        in_array(strlen($normalized) % 8, [1, 3, 6], true)
    ) {
        throw new InvalidArgumentException('Invalid Base32 MFA secret.');
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $lookup = array_flip(str_split($alphabet));
    $buffer = 0;
    $bitsInBuffer = 0;
    $decoded = '';

    foreach (str_split($normalized) as $character) {
        $buffer = ($buffer << 5) | $lookup[$character];
        $bitsInBuffer += 5;

        while ($bitsInBuffer >= 8) {
            $bitsInBuffer -= 8;
            $decoded .= chr(($buffer >> $bitsInBuffer) & 255);
        }

        $buffer = $bitsInBuffer === 0
            ? 0
            : $buffer & ((1 << $bitsInBuffer) - 1);
    }

    if ($bitsInBuffer > 0 && $buffer !== 0) {
        throw new InvalidArgumentException(
            'Invalid non-canonical Base32 MFA secret.'
        );
    }

    return $decoded;
}

function buildTotpProvisioningUri(
    string $employeeId,
    string $secret
): string {
    $normalizedEmployeeId = trim($employeeId);

    if ($normalizedEmployeeId === '') {
        throw new InvalidArgumentException('Employee ID is required for MFA.');
    }

    $normalizedSecret = strtoupper(trim($secret));
    decodeMfaBase32($normalizedSecret);

    $issuer = getMfaIssuer();
    $label = rawurlencode($issuer)
        . ':'
        . rawurlencode($normalizedEmployeeId);
    $query = http_build_query(
        [
            'secret' => $normalizedSecret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => PCMS_MFA_TOTP_DIGITS,
            'period' => PCMS_MFA_TOTP_PERIOD,
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

    return 'otpauth://totp/' . $label . '?' . $query;
}

function getMfaIssuer(): string
{
    $issuer = getenv('MFA_ISSUER');

    if ($issuer === false || trim($issuer) === '') {
        return PCMS_MFA_DEFAULT_ISSUER;
    }

    return trim($issuer);
}

function generateTotpCode(
    string $secret,
    ?int $timestamp = null
): string {
    $effectiveTimestamp = $timestamp ?? time();

    if ($effectiveTimestamp < 0) {
        throw new InvalidArgumentException(
            'The TOTP timestamp cannot be negative.'
        );
    }

    $secretBytes = decodeMfaBase32($secret);
    $step = intdiv($effectiveTimestamp, PCMS_MFA_TOTP_PERIOD);

    return generateTotpCodeForStep($secretBytes, $step);
}

function generateTotpCodeForStep(
    string $secretBytes,
    int $step
): string {
    if ($step < 0) {
        throw new InvalidArgumentException(
            'The TOTP timestep cannot be negative.'
        );
    }

    $high = intdiv($step, 4294967296);
    $low = $step % 4294967296;
    $counter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $counter, $secretBytes, true);
    $offset = ord($hash[strlen($hash) - 1]) & 15;
    $binaryCode = unpack('N', substr($hash, $offset, 4))[1]
        & 0x7fffffff;
    $code = (string) ($binaryCode % (10 ** PCMS_MFA_TOTP_DIGITS));

    return str_pad(
        $code,
        PCMS_MFA_TOTP_DIGITS,
        '0',
        STR_PAD_LEFT
    );
}

function verifyTotpCode(
    string $secret,
    string $submittedCode,
    ?int $timestamp = null
): ?int {
    if (!preg_match('/\A\d{6}\z/', $submittedCode)) {
        return null;
    }

    $effectiveTimestamp = $timestamp ?? time();

    if ($effectiveTimestamp < 0) {
        throw new InvalidArgumentException(
            'The TOTP timestamp cannot be negative.'
        );
    }

    $secretBytes = decodeMfaBase32($secret);
    $currentStep = intdiv(
        $effectiveTimestamp,
        PCMS_MFA_TOTP_PERIOD
    );

    $offsets = [0];

    for ($distance = 1; $distance <= PCMS_MFA_TOTP_WINDOW; $distance++) {
        $offsets[] = -$distance;
        $offsets[] = $distance;
    }

    foreach ($offsets as $offset) {
        $candidateStep = $currentStep + $offset;

        if ($candidateStep < 0) {
            continue;
        }

        if (
            hash_equals(
                generateTotpCodeForStep($secretBytes, $candidateStep),
                $submittedCode
            )
        ) {
            return $candidateStep;
        }
    }

    return null;
}

function claimMfaTimestep(
    PDO $pdo,
    int $userId,
    int $matchedStep
): bool {
    if ($userId <= 0 || $matchedStep < 0) {
        throw new InvalidArgumentException(
            'Invalid MFA replay-protection claim.'
        );
    }

    $stmt = $pdo->prepare(
        'UPDATE public.users
         SET mfa_last_used_step = :set_step
         WHERE id = :user_id
           AND (
               mfa_last_used_step IS NULL
               OR mfa_last_used_step < :compare_step
           )'
    );
    $stmt->bindValue(':set_step', $matchedStep, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':compare_step', $matchedStep, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() === 1;
}

function isMfaRequiredForRole(string $role): bool
{
    return match (strtolower(trim($role))) {
        'administrator' => readMfaBooleanPolicy(
            'MFA_REQUIRE_ADMINISTRATOR',
            true
        ),
        'property custodian' => readMfaBooleanPolicy(
            'MFA_REQUIRE_PROPERTY_CUSTODIAN',
            false
        ),
        default => false,
    };
}

function readMfaBooleanPolicy(
    string $environmentName,
    bool $default
): bool {
    $value = getenv($environmentName);

    if ($value === false || trim($value) === '') {
        return $default;
    }

    return in_array(
        strtolower(trim($value)),
        ['true', '1', 'yes', 'on'],
        true
    );
}

function recordSecurityEvent(
    PDO $pdo,
    string $eventType,
    ?int $actorUserId,
    int $targetUserId,
    ?string $description = null
): void {
    $allowedEventTypes = [
        'MFA_ENROLLED',
        'MFA_DISABLED',
        'MFA_RESET_BY_ADMIN',
        'MFA_CHALLENGE_BLOCKED',
    ];
    $normalizedEventType = trim($eventType);

    if (!in_array($normalizedEventType, $allowedEventTypes, true)) {
        throw new InvalidArgumentException(
            'Invalid MFA security event type.'
        );
    }

    if (
        $targetUserId <= 0 ||
        ($actorUserId !== null && $actorUserId <= 0)
    ) {
        throw new InvalidArgumentException(
            'Invalid MFA security event user reference.'
        );
    }

    $safeDescription = normalizeMfaSecurityEventDescription($description);
    $stmt = $pdo->prepare(
        'INSERT INTO public.security_events (
            event_type,
            actor_user_id,
            target_user_id,
            description
         ) VALUES (
            :event_type,
            :actor_user_id,
            :target_user_id,
            :description
         )'
    );
    $stmt->execute([
        'event_type' => $normalizedEventType,
        'actor_user_id' => $actorUserId,
        'target_user_id' => $targetUserId,
        'description' => $safeDescription,
    ]);
}

function normalizeMfaSecurityEventDescription(
    ?string $description
): ?string {
    if ($description === null || trim($description) === '') {
        return null;
    }

    $normalized = trim($description);

    if (strlen($normalized) > 1000) {
        throw new InvalidArgumentException(
            'MFA security event description is too long.'
        );
    }

    $sensitivePatterns = [
        '/otpauth:\/\/totp\//i',
        '/MFA_ENCRYPTION_KEY/i',
        '/(?<![A-Za-z0-9])v1:[A-Za-z0-9+\/=]+:[A-Za-z0-9+\/=]+(?![A-Za-z0-9+\/=])/',
        '/(?<!\d)\d{6}(?!\d)/',
        '/\b[A-Z2-7]{32,}\b/i',
        '/(?:password|secret|encryption[ _-]?key)\s*[:=]/i',
        '/(?<![A-Za-z0-9+\/])[A-Za-z0-9+\/]{43}=(?![A-Za-z0-9+\/=])/',
    ];

    foreach ($sensitivePatterns as $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            throw new InvalidArgumentException(
                'MFA security event descriptions cannot contain secret material.'
            );
        }
    }

    return $normalized;
}
