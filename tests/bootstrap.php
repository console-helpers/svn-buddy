<?php

require_once __DIR__ . '/../vendor/autoload.php';

// See https://github.com/xdebug/xdebug/pull/699 .
if ( !defined('XDEBUG_CC_UNUSED') ) {
	define('XDEBUG_CC_UNUSED', 1);
}

if ( !defined('XDEBUG_CC_DEAD_CODE') ) {
	define('XDEBUG_CC_DEAD_CODE', 2);
}
