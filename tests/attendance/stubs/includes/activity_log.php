<?php function logSchoolActivity(...$a) { file_put_contents(sys_get_temp_dir() . '/att_activity.log', $a[4] . "\n", FILE_APPEND); }
