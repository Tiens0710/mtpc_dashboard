<?php
/* Copy to /home/mtpc/private/google-calendar-config.php (outside the web root). */
return array(
    /* Service account created in Google Cloud with Calendar API enabled. */
    'client_email' => 'service-account@project-id.iam.gserviceaccount.com',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nPASTE_PRIVATE_KEY_HERE\n-----END PRIVATE KEY-----\n",

    /* Share this calendar with the service account and allow it to edit events. */
    'calendar_id' => 'teacher-or-school-calendar@group.calendar.google.com',

    /* Optional for Google Workspace domain-wide delegation. */
    'impersonate_user' => '',
);
