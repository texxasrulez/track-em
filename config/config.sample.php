<?php
return array (
  'base_url' => '',
  'database' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => '',
    'user' => '',
    'pass' => '',
  ),
  'theme' => 
  array (
    'active' => 'default',
  ),
  'i18n' => 
  array (
    'default' => 'en_US',
  ),
  'privacy' => 
  array (
    'respect_dnt' => true,
    'require_consent' => false,
    'ip_anonymize' => true,
    'ip_mask_bits' => 16,
  ),
  'geo' => [
  'enabled' => true,
  'provider'   => 'ip-api',
  'ip_api_base'=> 'http://ip-api.com/json'  // or point to your own HTTPS proxy
],
);
