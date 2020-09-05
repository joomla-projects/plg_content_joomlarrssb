<?php
/**
 * Ridiculously Responsive Social Sharing Buttons for Joomla
 *
 * @copyright  Copyright (C) 2015 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  2.0
 */
class PlgContentJoomlarrssbScript extends InstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @since   2.0
	 */
	public function __construct()
	{
		$this->minimumJoomla = '3.9';
		$this->minimumPhp    = '7.2.5';
	}
}
