<?php
/* PHP 5.6 compatible. Read only explicitly supported server configuration names. */
function mtpc_zalo_apply_env($config) {
    $fields = array(
        'access_token' => array('MTPC_ZALO_OA_ACCESS_TOKEN', 'ZALO_OA_ACCESS_TOKEN', 'ZALO_ACCESS_TOKEN'),
        'webhook_token' => array('MTPC_ZALO_OA_WEBHOOK_TOKEN', 'ZALO_OA_WEBHOOK_TOKEN', 'ZALO_WEBHOOK_TOKEN'),
        'oa_id' => array('MTPC_ZALO_OA_ID', 'ZALO_OA_ID'),
        'gmf_asset_id' => array('MTPC_ZALO_OA_ASSET_ID', 'ZALO_OA_ASSET_ID', 'ZALO_GMF_ASSET_ID'),
        'auto_reply' => array('MTPC_ZALO_OA_AUTO_REPLY', 'ZALO_OA_AUTO_REPLY')
    );
    foreach ($fields as $field => $names) {
        foreach ($names as $name) {
            $candidates = array(getenv($name));
            if (isset($_ENV[$name])) $candidates[] = $_ENV[$name];
            if (isset($_SERVER[$name])) $candidates[] = $_SERVER[$name];
            if (isset($_SERVER['REDIRECT_' . $name])) $candidates[] = $_SERVER['REDIRECT_' . $name];
            foreach ($candidates as $value) {
                if (!is_scalar($value) || trim((string)$value) === '') continue;
                $value = trim((string)$value);
                if ($field === 'auto_reply') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) continue;
                }
                $config[$field] = $value;
                continue 3;
            }
        }
    }
    return $config;
}
