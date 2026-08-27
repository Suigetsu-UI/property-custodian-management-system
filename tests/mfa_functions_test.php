<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/mfa_functions.php';

$testsRun = 0;

function assertMfaTest(bool $condition, string $message): void
{
    global $testsRun;
    $testsRun++;

    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
}

function assertMfaThrows(
    callable $operation,
    string $expectedClass,
    string $message
): void {
    $thrown = null;

    try {
        $operation();
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    assertMfaTest(
        $thrown instanceof $expectedClass,
        $message
    );
}

function setMfaTestEnvironment(string $name, ?string $value): void
{
    if ($value === null) {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function withMfaTestEnvironment(
    string $name,
    ?string $value,
    callable $operation
): mixed {
    $original = getenv($name);
    $hadEnvEntry = array_key_exists($name, $_ENV);
    $originalEnvEntry = $_ENV[$name] ?? null;

    setMfaTestEnvironment($name, $value);

    try {
        return $operation();
    } finally {
        if ($original === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $original);
        }

        if ($hadEnvEntry) {
            $_ENV[$name] = $originalEnvEntry;
        } else {
            unset($_ENV[$name]);
        }
    }
}

assertMfaRuntimeReady();
assertMfaTest(
    extension_loaded('sodium') &&
    function_exists('sodium_crypto_secretbox'),
    'Sodium and secretbox must be available.'
);

$encryptionKey = getMfaEncryptionKey();
assertMfaTest(
    strlen($encryptionKey) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
    'The configured MFA key must decode to exactly 32 bytes.'
);
sodium_memzero($encryptionKey);

withMfaTestEnvironment(
    'MFA_ENCRYPTION_KEY',
    null,
    function (): void {
        assertMfaThrows(
            fn () => getMfaEncryptionKey(),
            RuntimeException::class,
            'A missing MFA key must fail closed.'
        );
    }
);
withMfaTestEnvironment(
    'MFA_ENCRYPTION_KEY',
    'not-valid-base64!',
    function (): void {
        assertMfaThrows(
            fn () => getMfaEncryptionKey(),
            RuntimeException::class,
            'Invalid Base64 MFA key material must fail closed.'
        );
    }
);
withMfaTestEnvironment(
    'MFA_ENCRYPTION_KEY',
    base64_encode(random_bytes(31)),
    function (): void {
        assertMfaThrows(
            fn () => getMfaEncryptionKey(),
            RuntimeException::class,
            'An MFA key with the wrong decoded length must fail closed.'
        );
    }
);

$plainSecret = 'JBSWY3DPEHPK3PXP';
$firstEncrypted = encryptMfaSecret($plainSecret);
$secondEncrypted = encryptMfaSecret($plainSecret);
assertMfaTest(
    str_starts_with($firstEncrypted, 'v1:'),
    'Encrypted secrets must use the versioned v1 storage format.'
);
assertMfaTest(
    decryptMfaSecret($firstEncrypted) === $plainSecret,
    'An encrypted MFA secret must decrypt to its original value.'
);
assertMfaTest(
    $firstEncrypted !== $secondEncrypted,
    'Fresh nonces must produce different ciphertexts for the same secret.'
);

[$version, $nonce, $ciphertext] = explode(':', $firstEncrypted, 3);
$ciphertextBytes = base64_decode($ciphertext, true);
$ciphertextBytes[0] = chr(ord($ciphertextBytes[0]) ^ 1);
$tampered = $version
    . ':'
    . $nonce
    . ':'
    . base64_encode($ciphertextBytes);
assertMfaThrows(
    fn () => decryptMfaSecret($tampered),
    RuntimeException::class,
    'Tampered ciphertext must be rejected.'
);

$generatedSecret = generateMfaSecret();
assertMfaTest(
    preg_match('/\A[A-Z2-7]{32}\z/', $generatedSecret) === 1 &&
    strlen(decodeMfaBase32($generatedSecret)) === PCMS_MFA_SECRET_BYTES,
    'Generated MFA secrets must be canonical 160-bit Base32 values.'
);
assertMfaTest(
    encodeMfaBase32('12345678901234567890') ===
        'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
    'Base32 encoding must match the RFC 4648 representation.'
);

$rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
assertMfaTest(
    generateTotpCode($rfcSecret, 59) === '287082',
    'The SHA-1 TOTP implementation must match the RFC vector at 59 seconds.'
);
assertMfaTest(
    generateTotpCode($rfcSecret, 900) === '026920',
    'TOTP values below 100000 must retain six-digit zero padding.'
);

$verificationTimestamp = 1234567890;
$currentStep = intdiv($verificationTimestamp, PCMS_MFA_TOTP_PERIOD);
$previousCode = generateTotpCodeForStep(
    decodeMfaBase32($rfcSecret),
    $currentStep - 1
);
$currentCode = generateTotpCodeForStep(
    decodeMfaBase32($rfcSecret),
    $currentStep
);
$nextCode = generateTotpCodeForStep(
    decodeMfaBase32($rfcSecret),
    $currentStep + 1
);
$outsideCode = generateTotpCodeForStep(
    decodeMfaBase32($rfcSecret),
    $currentStep - 2
);

assertMfaTest(
    verifyTotpCode(
        $rfcSecret,
        $previousCode,
        $verificationTimestamp
    ) === $currentStep - 1,
    'The previous TOTP timestep must be accepted and returned.'
);
assertMfaTest(
    verifyTotpCode(
        $rfcSecret,
        $currentCode,
        $verificationTimestamp
    ) === $currentStep,
    'The current TOTP timestep must be accepted and returned.'
);
assertMfaTest(
    verifyTotpCode(
        $rfcSecret,
        $nextCode,
        $verificationTimestamp
    ) === $currentStep + 1,
    'The next TOTP timestep must be accepted and returned.'
);
assertMfaTest(
    verifyTotpCode(
        $rfcSecret,
        $outsideCode,
        $verificationTimestamp
    ) === null,
    'A TOTP code outside the one-step window must be rejected.'
);

foreach (['', '12345', '1234567', '12A456', ' 123456'] as $invalidCode) {
    assertMfaTest(
        verifyTotpCode(
            $rfcSecret,
            $invalidCode,
            $verificationTimestamp
        ) === null,
        'Only an exact six-decimal-digit TOTP submission is valid.'
    );
}

$provisioningUri = buildTotpProvisioningUri(
    'EMPLOYEE 001',
    $generatedSecret
);
assertMfaTest(
    str_starts_with(
        $provisioningUri,
        'otpauth://totp/Smart%20AssetTrack:EMPLOYEE%20001?'
    ) &&
    str_contains($provisioningUri, 'issuer=Smart%20AssetTrack') &&
    str_contains($provisioningUri, 'algorithm=SHA1') &&
    str_contains($provisioningUri, 'digits=6') &&
    str_contains($provisioningUri, 'period=30'),
    'The provisioning URI must use the frozen issuer and TOTP parameters.'
);

withMfaTestEnvironment(
    'MFA_REQUIRE_ADMINISTRATOR',
    'true',
    function (): void {
        assertMfaTest(
            isMfaRequiredForRole('Administrator'),
            'Administrator MFA must be required when explicitly enabled.'
        );
    }
);
withMfaTestEnvironment(
    'MFA_REQUIRE_PROPERTY_CUSTODIAN',
    'false',
    function (): void {
        assertMfaTest(
            !isMfaRequiredForRole('Property Custodian'),
            'Property Custodian MFA must remain optional when disabled.'
        );
    }
);
withMfaTestEnvironment(
    'MFA_REQUIRE_ADMINISTRATOR',
    null,
    function (): void {
        assertMfaTest(
            isMfaRequiredForRole('Administrator'),
            'Administrator MFA must default to required.'
        );
    }
);
withMfaTestEnvironment(
    'MFA_REQUIRE_PROPERTY_CUSTODIAN',
    null,
    function (): void {
        assertMfaTest(
            !isMfaRequiredForRole('Property Custodian'),
            'Property Custodian MFA must default to optional.'
        );
    }
);

foreach (['true', '1', 'yes', 'on', 'TRUE', ' On '] as $enabledValue) {
    withMfaTestEnvironment(
        'MFA_REQUIRE_ADMINISTRATOR',
        $enabledValue,
        function (): void {
            assertMfaTest(
                isMfaRequiredForRole('Administrator'),
                'Only approved explicit true values should enable MFA policy.'
            );
        }
    );
}
foreach (['false', '0', 'no', 'off', 'enabled', '2'] as $disabledValue) {
    withMfaTestEnvironment(
        'MFA_REQUIRE_ADMINISTRATOR',
        $disabledValue,
        function (): void {
            assertMfaTest(
                !isMfaRequiredForRole('Administrator'),
                'Unapproved values must not become true through loose coercion.'
            );
        }
    );
}
assertMfaTest(
    !isMfaRequiredForRole('Unknown Role'),
    'Unknown roles must never inherit an MFA requirement implicitly.'
);

$pdo = getDbConnection();
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_NOT_APPROVED',
        null,
        1
    ),
    InvalidArgumentException::class,
    'Unapproved MFA event types must be rejected before database access.'
);
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_ENROLLED',
        null,
        1,
        $provisioningUri
    ),
    InvalidArgumentException::class,
    'Provisioning URIs must never be accepted as audit descriptions.'
);
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_ENROLLED',
        null,
        1,
        'Submitted code: 123456'
    ),
    InvalidArgumentException::class,
    'TOTP values must never be accepted as audit descriptions.'
);
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_ENROLLED',
        null,
        1,
        'Stored value: ' . $firstEncrypted
    ),
    InvalidArgumentException::class,
    'Encrypted MFA secrets must never be accepted as audit descriptions.'
);
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_ENROLLED',
        null,
        1,
        'Authenticator value: ' . $generatedSecret
    ),
    InvalidArgumentException::class,
    'Base32 MFA secrets must never be accepted as audit descriptions.'
);
assertMfaThrows(
    fn () => recordSecurityEvent(
        $pdo,
        'MFA_ENROLLED',
        null,
        1,
        'Key material: ' . (string) getenv('MFA_ENCRYPTION_KEY')
    ),
    InvalidArgumentException::class,
    'The MFA encryption key must never be accepted as an audit description.'
);

$fixtureEmployeeId = 'MFA-TEST-' . bin2hex(random_bytes(8));

try {
    $pdo->beginTransaction();
    $insertUser = $pdo->prepare(
        'INSERT INTO public.users (
            employee_id,
            full_name,
            password_hash,
            role,
            is_active,
            mfa_enabled,
            mfa_secret_enc,
            mfa_enrolled_at,
            mfa_last_used_step
         ) VALUES (
            :employee_id,
            :full_name,
            :password_hash,
            :role,
            TRUE,
            TRUE,
            :mfa_secret_enc,
            CURRENT_TIMESTAMP,
            :initial_step
         )
         RETURNING id'
    );
    $insertUser->execute([
        'employee_id' => $fixtureEmployeeId,
        'full_name' => 'MFA Replay Test Fixture',
        'password_hash' => password_hash(
            bin2hex(random_bytes(16)),
            PASSWORD_DEFAULT
        ),
        'role' => 'Administrator',
        'mfa_secret_enc' => 'test-transaction-only',
        'initial_step' => 100,
    ]);
    $fixtureUserId = (int) $insertUser->fetchColumn();

    assertMfaTest(
        claimMfaTimestep($pdo, $fixtureUserId, 101),
        'A newer MFA timestep must be claimed atomically.'
    );
    assertMfaTest(
        !claimMfaTimestep($pdo, $fixtureUserId, 101),
        'A replayed MFA timestep must be rejected atomically.'
    );
    assertMfaTest(
        !claimMfaTimestep($pdo, $fixtureUserId, 100),
        'An older MFA timestep must be rejected atomically.'
    );
    assertMfaTest(
        claimMfaTimestep($pdo, $fixtureUserId, 102),
        'A later MFA timestep must remain claimable.'
    );

    $readStep = $pdo->prepare(
        'SELECT mfa_last_used_step FROM public.users WHERE id = :id'
    );
    $readStep->execute(['id' => $fixtureUserId]);
    assertMfaTest(
        (int) $readStep->fetchColumn() === 102,
        'The replay claim must preserve the newest accepted timestep.'
    );

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

echo "MFA primitive tests passed: {$testsRun}" . PHP_EOL;
