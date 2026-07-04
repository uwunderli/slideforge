<?php
/**
 * Einladungs-Tokens: Alternative zur offenen Registrierung.
 * Ein Admin erzeugt einen Link mit Token, die eingeladene Person kann sich
 * damit registrieren, auch wenn die offene Registrierung deaktiviert ist.
 */
class InviteToken
{
    public static function create(string $createdByUserId, string $email = ''): array
    {
        $invite = [
            'token' => Storage::generateId(16),
            'email' => $email,
            'created_by' => $createdByUserId,
            'created_at' => date('c'),
            'used' => false,
            'used_by' => null,
        ];
        Storage::update(INVITES_FILE, function ($invites) use ($invite) {
            $invites[] = $invite;
            return $invites;
        }, []);
        return $invite;
    }

    public static function find(string $token): ?array
    {
        $invites = Storage::read(INVITES_FILE, []);
        foreach ($invites as $inv) {
            if ($inv['token'] === $token) {
                return $inv;
            }
        }
        return null;
    }

    public static function isValid(string $token): bool
    {
        $inv = self::find($token);
        return $inv !== null && !$inv['used'];
    }

    public static function consume(string $token, string $usedByUserId): void
    {
        Storage::update(INVITES_FILE, function ($invites) use ($token, $usedByUserId) {
            foreach ($invites as &$inv) {
                if ($inv['token'] === $token) {
                    $inv['used'] = true;
                    $inv['used_by'] = $usedByUserId;
                    $inv['used_at'] = date('c');
                }
            }
            return $invites;
        }, []);
    }

    public static function listAll(): array
    {
        $invites = Storage::read(INVITES_FILE, []);
        usort($invites, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $invites;
    }

    public static function delete(string $token): void
    {
        Storage::update(INVITES_FILE, function ($invites) use ($token) {
            return array_values(array_filter($invites, fn($i) => $i['token'] !== $token));
        }, []);
    }
}
