<?php
/**
 * jobs.php - small DB queue (table `jobs`). Used for receipt e-mails so a slow SMTP never blocks a donor.
 *   job_enqueue('send_receipt_email', ['donation_id' => 5]);
 *   jobs_run(10);     // called right after a donation, and every minute by cli/queue_worker.php (cron)
 */
function job_enqueue(string $type, array $payload): void
{
    db_query('INSERT INTO jobs (job_type, payload) VALUES (?, ?)', [$type, json_encode($payload)]);
}

function jobs_run(int $limit = 10): int
{
    // jobs stuck in "processing" for 10+ minutes (crashed worker) go back to the queue
    db_query("UPDATE jobs SET status='pending' WHERE status='processing' AND reserved_at < (NOW() - INTERVAL 10 MINUTE)");
    $done = 0;
    $rows = db_rows("SELECT * FROM jobs WHERE status='pending' AND available_at <= NOW() ORDER BY id LIMIT " . (int) $limit);
    foreach ($rows as $job) {
        // claim: only one worker can flip pending -> processing
        $claim = db_query("UPDATE jobs SET status='processing', reserved_at=NOW(), attempts=attempts+1 WHERE id=? AND status='pending'", [$job['id']]);
        if ($claim->rowCount() !== 1) continue;
        try {
            job_handle($job['job_type'], json_decode($job['payload'], true) ?: []);
            db_query("UPDATE jobs SET status='done', finished_at=NOW(), last_error=NULL WHERE id=?", [$job['id']]);
            $done++;
        } catch (Throwable $e) {
            $attempts = (int) $job['attempts'] + 1;
            $final = $attempts >= (int) $job['max_attempts'];
            db_query("UPDATE jobs SET status=?, last_error=?, available_at = NOW() + INTERVAL ? MINUTE WHERE id=?",
                     [$final ? 'failed' : 'pending', substr($e->getMessage(), 0, 500), 5 * $attempts, $job['id']]);
            error_log('job ' . $job['id'] . ' failed: ' . $e->getMessage());
        }
    }
    return $done;
}

function job_handle(string $type, array $p): void
{
    switch ($type) {
        case 'send_receipt_email':
            require_once ROOT_PATH . '/includes/donations.php';
            donation_send_receipt_email((int) ($p['donation_id'] ?? 0));
            return;
    }
    throw new RuntimeException('Unknown job type: ' . $type);
}
