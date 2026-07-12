<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnService
{
    private const SESSION_CHALLENGE = 'webauthn.challenge';

    private const SESSION_OPTIONS = 'webauthn.options_type';

    private SerializerInterface $serializer;

    private AuthenticatorAttestationResponseValidator $attestationValidator;

    private AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct()
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($this->allowedOrigins(), true);

        $attestationManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);

        $this->serializer = (new WebauthnSerializerFactory($attestationManager))->create();
        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $factory->creationCeremony(),
        );
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $factory->requestCeremony(),
        );
    }

    /** @return array<string, mixed> */
    public function registrationOptions(User $user): array
    {
        $challenge = random_bytes(32);
        Session::put(self::SESSION_CHALLENGE, base64_encode($challenge));
        Session::put(self::SESSION_OPTIONS, 'registration');

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create(config('app.name'), $this->relyingPartyId()),
            user: PublicKeyCredentialUserEntity::create(
                (string) ($user->phone ?: $user->name),
                $this->userHandle($user),
                $user->name,
            ),
            challenge: $challenge,
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -7),
                PublicKeyCredentialParameters::create(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -257),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $this->existingDescriptors($user),
            timeout: 120_000,
        );

        return $this->normalizeOptions($options);
    }

    /** @return array<string, mixed> */
    public function assertionOptions(User $user): array
    {
        $challenge = random_bytes(32);
        Session::put(self::SESSION_CHALLENGE, base64_encode($challenge));
        Session::put(self::SESSION_OPTIONS, 'assertion');

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->relyingPartyId(),
            allowCredentials: $this->existingDescriptors($user),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 120_000,
        );

        return $this->normalizeOptions($options);
    }

    public function verifyRegistration(User $user, string $credentialJson): WebauthnCredential
    {
        $storedChallenge = $this->pullStoredChallenge('registration');
        $publicKeyCredential = $this->serializer->deserialize(
            $credentialJson,
            \Webauthn\PublicKeyCredential::class,
            'json',
        );

        $response = $publicKeyCredential->response;
        if (! $response instanceof \Webauthn\AuthenticatorAttestationResponse) {
            throw new \InvalidArgumentException('Phản hồi đăng ký sinh trắc không hợp lệ.');
        }

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create(config('app.name'), $this->relyingPartyId()),
            user: PublicKeyCredentialUserEntity::create(
                (string) ($user->phone ?: $user->name),
                $this->userHandle($user),
                $user->name,
            ),
            challenge: $storedChallenge,
        );

        $record = $this->attestationValidator->check(
            $response,
            $options,
            $this->relyingPartyId(),
        );

        $credential = WebauthnCredential::fromCredentialRecord($user->id, $record);
        $credential->save();

        return $credential;
    }

    public function verifyAssertion(User $user, string $credentialJson): WebauthnCredential
    {
        $storedChallenge = $this->pullStoredChallenge('assertion');
        $publicKeyCredential = $this->serializer->deserialize(
            $credentialJson,
            \Webauthn\PublicKeyCredential::class,
            'json',
        );

        $response = $publicKeyCredential->response;
        if (! $response instanceof \Webauthn\AuthenticatorAssertionResponse) {
            throw new \InvalidArgumentException('Phản hồi xác thực sinh trắc không hợp lệ.');
        }

        $credentialId = $publicKeyCredential->rawId;
        $stored = WebauthnCredential::query()
            ->where('user_id', $user->id)
            ->get()
            ->first(function (WebauthnCredential $credential) use ($credentialId): bool {
                return hash_equals(WebauthnCredential::decodeStored($credential->credential_id), $credentialId);
            });

        if (! $stored) {
            throw new \InvalidArgumentException('Thiết bị sinh trắc chưa được đăng ký.');
        }

        $record = $stored->toCredentialRecord();
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $storedChallenge,
            rpId: $this->relyingPartyId(),
            allowCredentials: [$record->getPublicKeyCredentialDescriptor()],
        );

        $updated = $this->assertionValidator->check(
            $record,
            $response,
            $options,
            $this->relyingPartyId(),
            $this->userHandle($user),
        );

        $stored->update(['counter' => $updated->counter]);

        return $stored->fresh();
    }

    public function userHasCredentials(User $user): bool
    {
        return WebauthnCredential::query()->where('user_id', $user->id)->exists();
    }

    public function userHandle(User $user): string
    {
        return hash('sha256', 'customer:' . $user->id, true);
    }

    /** @return array<int, PublicKeyCredentialDescriptor> */
    private function existingDescriptors(User $user): array
    {
        return WebauthnCredential::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (WebauthnCredential $credential): PublicKeyCredentialDescriptor => $credential->toCredentialRecord()->getPublicKeyCredentialDescriptor())
            ->all();
    }

    /** @return array<string, mixed> */
    private function normalizeOptions(object $options): array
    {
        $json = $this->serializer->serialize($options, 'json');
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($payload) ? $payload : [];
    }

    private function pullStoredChallenge(string $expectedType): string
    {
        $type = Session::pull(self::SESSION_OPTIONS);
        $encoded = Session::pull(self::SESSION_CHALLENGE);

        if ($type !== $expectedType || ! is_string($encoded) || $encoded === '') {
            throw new \InvalidArgumentException('Phiên xác thực sinh trắc đã hết hạn. Vui lòng thử lại.');
        }

        $challenge = base64_decode($encoded, true);

        if ($challenge === false || $challenge === '') {
            throw new \InvalidArgumentException('Thử thách xác thực không hợp lệ.');
        }

        return $challenge;
    }

    public function relyingPartyId(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    /** @return string[] */
    public function allowedOrigins(): array
    {
        $origins = array_filter([
            rtrim((string) config('app.url'), '/'),
            'http://127.0.0.1:8000',
            'http://localhost:8000',
            'https://127.0.0.1:8000',
            'https://localhost:8000',
        ]);

        return array_values(array_unique($origins));
    }

    public static function credentialIdForStorage(string $rawId): string
    {
        return Base64UrlSafe::encodeUnpadded($rawId);
    }
}
