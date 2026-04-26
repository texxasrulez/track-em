<?php
return [
    "base_url" => "",
    "database" => [
        "host" => "127.0.0.1",
        "port" => 3306,
        "name" => "",
        "user" => "",
        "pass" => "",
    ],
    "theme" => [
        "active" => "default",
    ],
    "i18n" => [
        "default" => "en_US",
    ],
    "privacy" => [
        "respect_dnt" => true,
        "require_consent" => false,
        "ip_anonymize" => true,
        "ip_mask_bits" => 16,
    ],
    "security" => [
        "trusted_proxies" => [],
    ],
    "geo" => [
        "enabled" => true,
        "provider" => "ip-api",
        "ip_api_base" => "http://ip-api.com/json", // or point to your own HTTPS proxy
        "allow_insecure_http" => false, // set true only if you explicitly accept plaintext provider traffic
    ],
];
