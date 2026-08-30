<?php
/* Copy to /home/mtpc/private/zalo-oa-config.php on the hosting server. */
$MTPC_ZALO_OA_ACCESS_TOKEN = 'PASTE_ZALO_OA_ACCESS_TOKEN_HERE';
$MTPC_ZALO_OA_WEBHOOK_TOKEN = 'CREATE_A_LONG_RANDOM_WEBHOOK_TOKEN_HERE';
$MTPC_ZALO_OA_ID = 'YOUR_ZALO_OA_ID';
$MTPC_ZALO_OA_AUTO_REPLY = true;
/* Keep the default unless Zalo gives your app a different endpoint. */
$MTPC_ZALO_OA_SEND_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';
/* Optional: requires the OA permission to manage follower information. */
$MTPC_ZALO_OA_PROFILE_URL = 'https://openapi.zalo.me/v3.0/oa/user/detail';
/* Legacy fallback; leave unchanged unless Zalo changes the API. */
$MTPC_ZALO_OA_PROFILE_FALLBACK_URL = 'https://openapi.zalo.me/v2.0/oa/getprofile';
