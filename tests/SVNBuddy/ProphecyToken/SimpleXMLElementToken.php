<?php
/**
 * This file is part of the SVN-Buddy library.
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @copyright Alexander Obuhovich <aik.bold@gmail.com>
 * @link      https://github.com/aik099/svn-buddy
 */

namespace Tests\aik099\SVNBuddy\ProphecyToken;


use Prophecy\Argument\Token\TokenInterface;

class SimpleXMLElementToken implements TokenInterface
{

	/**
	 * XML.
	 *
	 * @var string
	 */
	private $_xml;

	/**
	 * Creates token for matching to SimpleXMLElement object.
	 *
	 * @param \SimpleXMLElement $element Element.
	 */
	public function __construct(\SimpleXMLElement $element)
	{
		$this->_xml = $element->asXML();
	}

	/**
	 * Calculates token match score for provided argument.
	 *
	 * @param string $argument Argument.
	 *
	 * @return boolean|integer
	 */
	public function scoreArgument($argument)
	{
		if ( $argument instanceof \SimpleXMLElement ) {
			return $argument->asXML() === $this->_xml ? 6 : false;
		}

		return false;
	}

	/**
	 * Returns true if this token prevents check of other tokens (is last one).
	 *
	 * @return boolean|integer
	 */
	public function isLast()
	{
		return false;
	}

	/**
	 * Returns string representation for token.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return sprintf('xml("%s")', $this->_xml);
	}

}
