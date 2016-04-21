<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Helper;


use Symfony\Component\Console\Helper\Helper;

class SizeHelper extends Helper
{

	/**
	 * {@inheritdoc}
	 */
	public function getName()
	{
		return 'size';
	}

	/**
	 * Formats file/memory size in nice way
	 *
	 * @param integer $bytes Bytes.
	 *
	 * @return string
	 */
	public function formatSize($bytes)
	{
		if ( $bytes >= 1099511627776 ) {
			$return = round($bytes / 1024 / 1024 / 1024 / 1024, 2);
			$suffix = 'TB';
		}
		elseif ( $bytes >= 1073741824 ) {
			$return = round($bytes / 1024 / 1024 / 1024, 2);
			$suffix = 'GB';
		}
		elseif ( $bytes >= 1048576 ) {
			$return = round($bytes / 1024 / 1024, 2);
			$suffix = 'MB';
		}
		elseif ( $bytes >= 1024 ) {
			$return = round($bytes / 1024, 2);
			$suffix = 'KB';
		}
		else {
			$return = $bytes;
			$suffix = 'Byte';
		}

		$return .= ' ' . $suffix;

		return $return;
	}

}
