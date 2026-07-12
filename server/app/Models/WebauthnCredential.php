<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\TrustPath\EmptyTrustPath;

class WebauthnCredential extends Model
{
    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'counter',
        'transports',
        'attestation_type',
        'aaguid',
        'user_handle',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'counter'    => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toCredentialRecord(): CredentialRecord
    {
        $aaguid = filled($this->aaguid)
            ? Uuid::fromString((string) $this->aaguid)
            : Uuid::fromString('00000000-0000-0000-0000-000000000000');

        return CredentialRecord::create(
            publicKeyCredentialId: self::decodeStored((string) $this->credential_id),
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $this->transports ?? [],
            attestationType: (string) ($this->attestation_type ?: 'none'),
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: self::decodeStored((string) $this->public_key),
            userHandle: self::decodeStored((string) $this->user_handle),
            counter: (int) $this->counter,
        );
    }

    public static function fromCredentialRecord(int $userId, CredentialRecord $record): self
    {
        return new self([
            'user_id'           => $userId,
            'credential_id'     => self::encodeStored($record->publicKeyCredentialId),
            'public_key'        => self::encodeStored($record->credentialPublicKey),
            'counter'           => $record->counter,
            'transports'        => $record->transports,
            'attestation_type'  => $record->attestationType,
            'aaguid'            => $record->aaguid->toRfc4122(),
            'user_handle'       => self::encodeStored($record->userHandle),
        ]);
    }

    public static function encodeStored(string $value): string
    {
        return base64_encode($value);
    }

    public static function decodeStored(string $value): string
    {
        $decoded = base64_decode($value, true);

        return $decoded === false ? $value : $decoded;
    }
}
