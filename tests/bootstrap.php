<?php
/*
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

require_once __DIR__ . '/../vendor/autoload.php';

// See https://github.com/xdebug/xdebug/pull/699 .
if ( !defined('XDEBUG_CC_UNUSED') ) {
	define('XDEBUG_CC_UNUSED', 1);
}

if ( !defined('XDEBUG_CC_DEAD_CODE') ) {
	define('XDEBUG_CC_DEAD_CODE', 2);
}
