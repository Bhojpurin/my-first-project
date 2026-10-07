<?php
/**
 * audit.php - append-only activity log.
 * audit('update', 'news', 12, $oldRow, $newRow);
 * Never UPDATE/DELETE rows of audit_log from code.
 */
function audit(string $action, ?string $entity = null, $entity_id = null, ?array $old = null, ?array $new = null): void
{
    try {
        db_query(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, old_data, new_data, ip, user_agent)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $_SESSION['uid'] ?? null,
                $action,
                $entity,
                $entity_id === null ? null : (string) $entity_id,
                $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
                client_ip(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());   // logging must never break the page
    }
}
