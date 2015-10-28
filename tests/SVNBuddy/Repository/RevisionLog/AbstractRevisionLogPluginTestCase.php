<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\Repository\RevisionLog;


use aik099\SVNBuddy\Repository\RevisionLog\IRevisionLogPlugin;

abstract class AbstractRevisionLogPluginTestCase extends \PHPUnit_Framework_TestCase
{

	/**
	 * Revision log plugin.
	 *
	 * @var IRevisionLogPlugin
	 */
	protected $plugin;

	protected function setUp()
	{
		parent::setUp();

		$this->plugin = $this->createPlugin();
	}

	/**
	 * Expects query to "svn log".
	 *
	 * @return \SimpleXMLElement
	 */
	protected function getSvnLogFixture()
	{
		$svn_log_output = <<<OUTPUT
<?xml version="1.0"?>
<log>
<logentry
   revision="20128">
<author>alex</author>
<date>2015-10-13T13:30:16.473960Z</date>
<paths>
<path
   kind="file"
   action="M">/projects/project_a/trunk/sub-folder/file.tpl</path>
<path
   kind="dir"
   action="M">/projects/project_a/trunk/sub-folder</path>
</paths>
<msg>JRA-1 - task title</msg>
</logentry>
<logentry
   revision="20127">
<author>erik</author>
<date>2015-10-13T13:00:15.434252Z</date>
<paths>
<path
   kind="file"
   action="A"
   unknown-attribute="unknown-value">/projects/project_a/trunk/another_file.php</path>
</paths>
<msg>JRA-2 - task title</msg>
</logentry>
<logentry
   revision="20125">
<author>erik</author>
<date>2015-10-13T13:00:15.434252Z</date>
<paths>
<path
   kind="file"
   action="M">/projects/project_a/trunk/another_file.php</path>
</paths>
<msg>JRA-1 - task title (reverts JRA-3)</msg>
</logentry>
</log>
OUTPUT;

		return new \SimpleXMLElement($svn_log_output);
	}

	/**
	 * Creates plugin.
	 *
	 * @return IRevisionLogPlugin
	 */
	abstract protected function createPlugin();

}
