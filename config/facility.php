<?php

return [
    // Self-registration is appropriate for a local demo, but closed by default
    // on production servers. Admins can always provision staff under Users.
    'public_registration' => (bool) env('PUBLIC_REGISTRATION_ENABLED',
        in_array(env('APP_ENV', 'production'), ['local', 'testing', 'loadtest'], true)),
];
