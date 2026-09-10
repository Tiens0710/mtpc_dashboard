<?php
/* Copy to /home/mtpc/private/zalo-oa-config.php on the hosting server. */
/* Non-empty PHP environment values override this file:
 * MTPC_ZALO_OA_ACCESS_TOKEN / ZALO_OA_ACCESS_TOKEN / ZALO_ACCESS_TOKEN
 * MTPC_ZALO_OA_WEBHOOK_TOKEN / ZALO_OA_WEBHOOK_TOKEN / ZALO_WEBHOOK_TOKEN
 * MTPC_ZALO_OA_ID / ZALO_OA_ID
 * MTPC_ZALO_OA_ASSET_IDS / ZALO_OA_ASSET_IDS / ZALO_GMF_ASSET_IDS
 * (comma-separated when supplied as an environment variable)
 * MTPC_ZALO_OA_ASSET_ID / ZALO_OA_ASSET_ID / ZALO_GMF_ASSET_ID (legacy single asset)
 * MTPC_ZALO_OA_APP_ID / ZALO_OA_APP_ID / ZALO_APP_ID
 * MTPC_ZALO_OA_SECRET_KEY / ZALO_OA_SECRET_KEY / ZALO_SECRET_KEY
 * MTPC_ZALO_OA_REFRESH_TOKEN / ZALO_OA_REFRESH_TOKEN / ZALO_REFRESH_TOKEN
 * MTPC_ZALO_OA_AUTO_REPLY / ZALO_OA_AUTO_REPLY (true/false or 1/0).
 * These must be exposed to PHP on admin.mtpc.edu.vn; a .env file alone is not loaded.
 * Refreshed token pairs are stored outside the web root in
 * /home/mtpc/private/mtpc-zalo-oa/token-state.json.
 */
$MTPC_ZALO_OA_ACCESS_TOKEN = 'PASTE_ZALO_OA_ACCESS_TOKEN_HERE';
$MTPC_ZALO_OA_REFRESH_TOKEN = 'PASTE_ZALO_OA_REFRESH_TOKEN_HERE';
$MTPC_ZALO_OA_APP_ID = 'PASTE_ZALO_APP_ID_HERE';
$MTPC_ZALO_OA_SECRET_KEY = 'PASTE_ZALO_APP_SECRET_KEY_HERE';
$MTPC_ZALO_OA_WEBHOOK_TOKEN = 'CREATE_A_LONG_RANDOM_WEBHOOK_TOKEN_HERE';
$MTPC_ZALO_OA_ID = 'YOUR_ZALO_OA_ID';
$MTPC_ZALO_OA_AUTO_REPLY = true;
/* Optional: các asset_id GMF còn dùng được. Mỗi asset chỉ tạo được một nhóm. */
$MTPC_ZALO_OA_ASSET_IDS = array(
    'PASTE_UNUSED_GMF_ASSET_ID_HERE'
);
/* Legacy fallback for installations that only have one unused asset. */
$MTPC_ZALO_OA_ASSET_ID = '';
/* Keep the default unless Zalo gives your app a different endpoint. */
$MTPC_ZALO_OA_SEND_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';
$MTPC_ZALO_OA_GROUP_API_BASE = 'https://openapi.zalo.me/v3.0/oa/group';
/* Optional: requires the OA permission to manage follower information. */
$MTPC_ZALO_OA_PROFILE_URL = 'https://openapi.zalo.me/v3.0/oa/user/detail';
/* Legacy fallback; leave unchanged unless Zalo changes the API. */
$MTPC_ZALO_OA_PROFILE_FALLBACK_URL = 'https://openapi.zalo.me/v2.0/oa/getprofile';
/* Optional: used to refresh exact message text and from_display_name. */
$MTPC_ZALO_OA_CONVERSATION_URL = 'https://openapi.zalo.me/v2.0/oa/conversation';
