<?php
const ATT_FEATURES = ['students'=>['Students','',''],'staff'=>['Staff','',''],'camera'=>['','',''],'usb_scanner'=>['','',''],'photo_scan'=>['','',''],
 'name_search'=>['','',''],'late'=>['','',''],'checkout'=>['','',''],'half_day'=>['','',''],'smart_voice'=>['','',''],'undo'=>['','',''],
 'live_gates'=>['','',''],'class_progress'=>['','',''],'finalize_absent'=>['','',''],'offline_queue'=>['','','']];
const ATT_PRESETS = [];
const ATT_STUDENT_CODE_FIELDS = ['admission_no','rfid_uid']; const ATT_STAFF_CODE_FIELDS = ['employee_code'];
const ATT_STUDENT_DOB_COLUMN = 'dob'; const ATT_STAFF_DOB_COLUMN = ''; const ATT_STAFF_GENDER_COLUMN = '';
function attFeatures($pdo, $sid) { $e = array_fill_keys(array_keys(ATT_FEATURES), true);
  return ['effective' => $e, 'locked' => [], 'student_code_field' => 'admission_no', 'staff_code_field' => 'employee_code']; }
function attCodeColumn($pdo, $t, $f, $allow, $def) { return in_array($f, $allow, true) ? $f : $def; }
