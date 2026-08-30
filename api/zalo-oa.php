<?php
/* Public Zalo OA webhook entrypoint. The implementation is shared with admin. */
define('MTPC_ZALO_PUBLIC_ENTRYPOINT', true);
$shared = '/home/mtpc/public_html/admin/api/zalo-oa.php';
if (!is_file($shared)) $shared = dirname(__DIR__) . '/admin/api/zalo-oa.php';
require $shared;
