<?php function sendSchoolNotification($sid, $ev, $data) {
  if (getenv('FAIL_NOTIFY')) throw new RuntimeException('gateway down');
  Database::connect()->prepare("INSERT INTO sent_log (school_id, event_type, payload) VALUES (?,?,?)")->execute([$sid, $ev, json_encode($data, JSON_UNESCAPED_UNICODE)]);
  return true; }
