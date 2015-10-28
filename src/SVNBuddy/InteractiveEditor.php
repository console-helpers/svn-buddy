<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace aik099\SVNBuddy;


class InteractiveEditor
{

	/**
	 * Document name.
	 *
	 * @var string
	 */
	private $_documentName = '';

	/**
	 * Content.
	 *
	 * @var string
	 */
	private $_content = '';

	/**
	 * Set the document name. Depending on the editor, this may be exposed to
	 * the user and can give them a sense of what they're editing.
	 *
	 * @param string $name Document name.
	 *
	 * @return self
	 */
	public function setDocumentName($name)
	{
		$this->_documentName = preg_replace('/[^A-Z0-9._-]+/i', '', $name);

		return $this;
	}

	/**
	 * Get the current document name. See @{method:setName} for details.
	 *
	 * @return string Current document name.
	 */
	public function getDocumentName()
	{
		if ( !strlen($this->_documentName) ) {
			return 'untitled';
		}

		return $this->_documentName;
	}

	/**
	 * Set the text content to be edited.
	 *
	 * @param string $content New content.
	 *
	 * @return self
	 */
	public function setContent($content)
	{
		$this->_content = $content;

		return $this;
	}

	/**
	 * Retrieve the current content.
	 *
	 * @return string
	 */
	public function getContent()
	{
		return $this->_content;
	}

	/**
	 * Launch an editor and edit the content. The edited content will be returned.
	 *
	 * @return string Edited content.
	 * @throws \RuntimeException When any of temporary file operation fails.
	 */
	public function launch()
	{
		$tmp_file = tempnam(sys_get_temp_dir(), $this->getDocumentName() . '_');

		if ( $tmp_file === false ) {
			throw new \RuntimeException('Unable to create temporary file.');
		}

		if ( file_put_contents($tmp_file, $this->getContent()) === false ) {
			throw new \RuntimeException('Unable to write content to temporary file.');
		}

		$exit_code = $this->_invokeEditor($this->_getEditor(), $tmp_file);

		if ( $exit_code ) {
			unlink($tmp_file);
			throw new \RuntimeException('Editor exited with an error code (#' . $exit_code . ').');
		}

		$new_content = file_get_contents($tmp_file);

		if ( $new_content === false ) {
			throw new \RuntimeException('Unable to read content from temporary file.');
		}

		unlink($tmp_file);

		$this->setContent($new_content);

		return $this->getContent();
	}

	/**
	 * Opens the editor.
	 *
	 * @param string $editor Editor.
	 * @param string $file   Path.
	 *
	 * @return integer
	 * @throws \RuntimeException When failed to open the editor.
	 */
	private function _invokeEditor($editor, $file)
	{
		$command = $editor . ' ' . escapeshellarg($file);

		$pipes = array();
		$spec = array(STDIN, STDOUT, STDERR);

		$proc = proc_open($command, $spec, $pipes);

		if ( !is_resource($proc) ) {
			throw new \RuntimeException('Failed to run: ' . $command);
		}

		return proc_close($proc);
	}

	/**
	 * Get the name of the editor program to use. The value of the environmental
	 * variable $EDITOR will be used if available; otherwise, the `editor` binary
	 * if present; otherwise the best editor will be selected.
	 *
	 * @return string Command-line editing program.
	 * @throws \LogicException When editor can't be found.
	 */
	private function _getEditor()
	{
		$editor = getenv('EDITOR');

		if ( $editor ) {
			return $editor;
		}

		// Look for `editor` in PATH, some systems provide an editor which is linked to something sensible.
		if ( $this->_fileExistsInPath('editor') ) {
			return 'editor';
		}

		if ( $this->_fileExistsInPath('nano') ) {
			return 'nano';
		}

		throw new \LogicException(
			'Unable to launch an interactive text editor. Set the EDITOR environment variable to an appropriate editor.'
		);
	}

	/**
	 * Determines if file exists in PATH.
	 *
	 * @param string $file File.
	 *
	 * @return boolean
	 */
	private function _fileExistsInPath($file)
	{
		$output = '';
		$exit_code = 0;
		exec('which ' . escapeshellarg($file) . ' 2>&1', $output, $exit_code);

		return $exit_code == 0;
	}

}
