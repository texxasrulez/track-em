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
  'geo' => 
  array (
    'enabled' => true,
    'provider' => 'ip-api',
    'ip_api_base' => 'http://ip-api.com/json',
    'timeout_sec' => 0.8,
    'max_lookups' => 15,
  ),
  'dashboard' => 
  array (
    'row_limit' => 200,
    'show_icons' => true,
    'ip_tooltips' => true,
  ),
);
