<?php
/**
 * Copy to /home/mtpc/private/moodle-config.php on the hosting server.
 * Never commit the real Moodle token to GitHub.
 */
return array(
    // Không có dấu / ở cuối.
    'moodle_url' => 'https://blearning.mtpc.edu.vn',

    // Tạo trong Moodle: Site administration > Server > Web services > Manage tokens.
    // Nên dùng một service account riêng, không dùng mật khẩu/token admin cá nhân.
    'moodle_token' => 'MOODLE_WEBSERVICE_TOKEN',
);
