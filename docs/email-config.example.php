<?php
/*
 * Copy this file to /home/mtpc/private/email-config.php on cPanel.
 * Never commit the real mailbox password to GitHub.
 */
$MTPC_EMAIL_IMAP_HOST = 'mail.mtpc.edu.vn';
$MTPC_EMAIL_IMAP_PORT = 993;
$MTPC_EMAIL_IMAP_ENCRYPTION = 'ssl';
$MTPC_EMAIL_USERNAME = 'your-mailbox@mtpc.edu.vn';
$MTPC_EMAIL_PASSWORD = 'replace-with-an-app-password-or-mailbox-password';
$MTPC_EMAIL_FOLDER = 'INBOX';
$MTPC_EMAIL_VALIDATE_CERT = true;

/* Keep true. Protect /public_html/admin with cPanel Directory Privacy. */
$MTPC_EMAIL_REQUIRE_AUTH = true;

