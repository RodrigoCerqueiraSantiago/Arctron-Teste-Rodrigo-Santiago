<?php
/**
 * Development only: allow WordPress to send the webhook to the Compose nginx service.
 */
add_filter('http_request_host_is_external', function ($allowed, $host) {
    return $host === 'nginx' ? true : $allowed;
}, 10, 2);
