<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/console-helpers/svn-buddy
 */

namespace ConsoleHelpers\SVNBuddy\Repository;


use ConsoleHelpers\SVNBuddy\Exception\RepositoryCommandException;
use ConsoleHelpers\SVNBuddy\Repository\Connector\Connector;

class RevisionUrlBuilder
{

	/**
	 * Repository connector.
	 *
	 * @var Connector
	 */
	private $_repositoryConnector;

	/**
	 * Repository URL.
	 *
	 * @var string
	 */
	private $_repositoryUrl;

	/**
	 * Creates revision url builder instance.
	 *
	 * @param Connector $repository_connector Repository connector.
	 * @param string    $repository_url       Repository URL.
	 */
	public function __construct(Connector $repository_connector, $repository_url)
	{
		$this->_repositoryConnector = $repository_connector;
		$this->_repositoryUrl = $repository_url;
	}

	/**
	 * Returns mask.
	 *
	 * @param string $style Style.
	 *
	 * @return string
	 */
	public function getMask($style = '')
	{
		try {
			$arcanist_config = \json_decode(
				$this->_repositoryConnector->getFileContent($this->_repositoryUrl . '/.arcconfig', 'HEAD'),
				true
			);
		}
		catch ( RepositoryCommandException $e ) {
			// Phabricator integration is not configured.
			return $this->addStyle($style, '{revision}');
		}

		// Phabricator integration is not configured correctly.
		if ( !\is_array($arcanist_config)
			|| !isset($arcanist_config['repository.callsign'], $arcanist_config['phabricator.uri'])
		) {
			return $this->addStyle($style, '{revision}');
		}

		// Phabricator integration is configured properly.
		$revision_title = $arcanist_config['phabricator.uri'];
		$revision_title .= 'r' . $arcanist_config['repository.callsign'] . '{revision}';

		return $this->addStyle($style, $revision_title);
	}

	/**
	 * Adds style around a text.
	 *
	 * @param string $style Style.
	 * @param string $text  Text.
	 *
	 * @return string
	 */
	protected function addStyle($style, $text)
	{
		return $style ? '<' . $style . '>' . $text . '</>' : $text;

	}

}
