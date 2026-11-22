<?php
/**
 * Apenas no ambiente local: permite o envio do webhook ao nginx do Compose.
 */
add_filter('http_request_host_is_external', function ($allowed, $host) {
    return $host === 'nginx' ? true : $allowed;
}, 10, 2);
