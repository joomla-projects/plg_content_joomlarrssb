<?php
/**
 * Ridiculously Responsive Social Sharing Buttons for joomla.org
 *
 * @copyright  Copyright (C) 2015 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  2.0
 */
class PlgContentJoomlarrssbScript extends JInstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @since   2.0
	 */
	public function __construct()
	{
		$this->minimumJoomla = '3.6';
		$this->minimumPhp    = '5.4';
	}
}
