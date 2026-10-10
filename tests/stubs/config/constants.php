<?php // TEST STUB
if (!defined('BASE_URL')) define('BASE_URL', '');
if (!defined('ROLE_SCHOOL_ADMIN')) define('ROLE_SCHOOL_ADMIN', 'school_admin');
if (!defined('ROLE_TEACHER')) define('ROLE_TEACHER', 'teacher');
if (!defined('VAPID_PUBLIC_KEY')) define('VAPID_PUBLIC_KEY', 'test');
if (!defined('BUS_ROUTER_URL')) define('BUS_ROUTER_URL', '');
if (getenv('BUS_TEST_REDIS') && !defined('BUS_REDIS_HOST')) define('BUS_REDIS_HOST', getenv('BUS_TEST_REDIS'));
