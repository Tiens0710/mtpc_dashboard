<?php
/* Copy to /home/mtpc/private/zalo-oa-config.php on the hosting server. */
$MTPC_ZALO_OA_ACCESS_TOKEN = 'PASTE_ZALO_OA_ACCESS_TOKEN_HERE';
$MTPC_ZALO_OA_WEBHOOK_TOKEN = 'CREATE_A_LONG_RANDOM_WEBHOOK_TOKEN_HERE';
$MTPC_ZALO_OA_ID = 'YOUR_ZALO_OA_ID';
$MTPC_ZALO_OA_AUTO_REPLY = true;
/* Optional: asset_id GMF còn dùng được. Khi đã cấu hình, Orb không cần hỏi mã này mỗi lần tạo nhóm. */
$MTPC_ZALO_OA_ASSET_ID = 'PASTE_GMF_ASSET_ID_HERE';
/* Keep the default unless Zalo gives your app a different endpoint. */
$MTPC_ZALO_OA_SEND_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';
$MTPC_ZALO_OA_GROUP_API_BASE = 'https://openapi.zalo.me/v3.0/oa/group';
/* Optional: requires the OA permission to manage follower information. */
$MTPC_ZALO_OA_PROFILE_URL = 'https://openapi.zalo.me/v3.0/oa/user/detail';
/* Legacy fallback; leave unchanged unless Zalo changes the API. */
$MTPC_ZALO_OA_PROFILE_FALLBACK_URL = 'https://openapi.zalo.me/v2.0/oa/getprofile';
/* Optional: used to refresh exact message text and from_display_name. */
$MTPC_ZALO_OA_CONVERSATION_URL = 'https://openapi.zalo.me/v2.0/oa/conversation';
