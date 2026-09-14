<?php
require_once dirname(__DIR__) . '/PublicAccess.php';

final class PublicAccessAdmin
{
    public function __construct(private PDO $pdo) {}

    public function saveSettings(array $settings, int $administratorId): void
    {
        if (!PublicAccess::validSettings($settings)) {
            throw new InvalidArgumentException('Use a threshold from 1 to 500 years and a valid timezone.');
        }
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('UPDATE public_access_settings SET enabled = ?, threshold_years = ?,
                timezone = ?, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = 1');
            $query->execute([(int) $settings['enabled'], (int) $settings['threshold_years'], $settings['timezone'], $administratorId]);
            $this->audit(null, $administratorId, 'settings', json_encode($settings, JSON_THROW_ON_ERROR));
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function exclude(int $individualId, bool $excluded, int $administratorId): void
    {
        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare('SELECT exclude_from_public FROM individuals WHERE id = ? FOR UPDATE');
            $find->execute([$individualId]);
            $previous = $find->fetchColumn();
            if ($previous === false) { throw new InvalidArgumentException('Individual not found.'); }
            $update = $this->pdo->prepare('UPDATE individuals SET exclude_from_public = ? WHERE id = ?');
            $update->execute([(int) $excluded, $individualId]);
            $this->audit($individualId, $administratorId, 'exclusion', json_encode(['before' => (int) $previous, 'after' => (int) $excluded]));
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function audit(?int $individualId, int $administratorId, string $action, string $details): void
    {
        $query = $this->pdo->prepare('INSERT INTO public_access_audit
            (individual_id, administrator_id, action, details, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
        $query->execute([$individualId, $administratorId, $action, $details]);
    }
}
