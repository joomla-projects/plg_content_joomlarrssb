<?php
/**
 * Ridiculously Responsive Social Sharing Buttons for joomla.org
 *
 * @copyright  Copyright (C) 2015 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

// We require com_content's route helper
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');
JLoader::register('JHttpFactory', JPATH_SITE . 'libraries/joomla/http/factory.php');

/**
 * Ridiculously Responsive Social Sharing Buttons for joomla.org Content Plugin
 *
 * @since  1.0
 */
class PlgContentJoomlarrssb extends JPlugin
{
	/**
	 * Application object
	 *
	 * @var    JApplicationCms
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Database object
	 *
	 * @var    JDatabaseDriver
	 * @since  1.0
	 */
	protected $db;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Listener for the `onContentAfterTitle` event
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   object   &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onContentAfterTitle($context, &$article, &$params, $page)
	{
		// Set the parameters
		$document           = JFactory::getDocument();
		$displayEmail       = $this->params->get('displayEmail', '1');
		$displayFacebook    = $this->params->get('displayFacebook', '1');
		$displayGoogle      = $this->params->get('displayGoogle', '1');
		$displayLinkedin    = $this->params->get('displayLinkedin', '1');
		$displayPinterest   = $this->params->get('displayPinterest', '1');
		$displayTwitter     = $this->params->get('displayTwitter', '1');
		$selectedCategories = $this->params->def('displayCategories', '');
		$position           = $this->params->def('displayPosition', 'top');
		$view               = $this->app->input->getCmd('view', '');
		$shorten            = $this->params->def('useYOURLS', true);

		// Check if the plugin is enabled
		if (JPluginHelper::isEnabled('content', 'joomlarrssb') == false)
		{
			return;
		}

		// Make sure the document is an HTML document
		if ($document->getType() != 'html')
		{
			return;
		}

		// Check whether we're displaying the plugin in the current view
		if ($this->params->get('view' . ucfirst($view), '1') == '0')
		{
			return;
		}

		// Check that we're actually displaying a button
		if ($displayEmail == '0' && $displayFacebook == '0' && $displayGoogle == '0' && $displayLinkedin == '0' && $displayPinterest == '0' && $displayTwitter == '0')
		{
			return;
		}

		// If we're not in the article view, we have to get the full $article object ourselves
		if ($view == 'featured' || $view == 'category')
		{
			/*
			 * We only want to handle com_content items; if this function returns null, there's no DB item
			 * Also, make sure the object isn't already loaded and undo previous plugin processing
			 */
			$data = $this->loadArticle($article);

			if ((!is_null($data)) && (!isset($article->catid)))
			{
				$article = $data;
			}
		}

		// Make sure we have a category ID, otherwise, end processing
		$properties = get_object_vars($article);

		if (!array_key_exists('catid', $properties))
		{
			return;
		}

		// Get the current category
		if (is_null($article->catid))
		{
			$currentCategory = 0;
		}
		else
		{
			$currentCategory = $article->catid;
		}

		// Define category restrictions
		if (is_array($selectedCategories))
		{
			$categories = $selectedCategories;
		}
		elseif ($selectedCategories == '')
		{
			$categories = [$currentCategory];
		}
		else
		{
			$categories = [$selectedCategories];
		}

		// If we aren't in a defined category, exit
		if (!in_array($currentCategory, $categories))
		{
			// If we made it this far, we probably deleted the text object; reset it
			if (!isset($article->text))
			{
				$article->text = $article->introtext;
			}

			return;
		}

		// Create the article slug
		$article->slug = $article->alias ? ($article->id . ':' . $article->alias) : $article->id;

		// Build the URL for the plugins to use
		$siteURL = substr(JUri::root(), 0, -1);
		$itemURL = $siteURL . JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));

		$document->addCustomTag('<meta property="og:title" content="' . $article->title . '"/>');
		$document->addCustomTag('<meta property="og:type" content="article"/>');
		$document->addCustomTag('<meta property="og:url" content="' . $itemURL . '"/>');

		if($shorten)
		{
			$http = JHttpFactory::getHttp();
			$data = array(
					'signature' => $this->params->def('YOURLSAPIKey', '2909bc72e7'),
					'action' => 'shorturl',
					'url' => $itemURL,
					'format' => 'simple'
			);
			$response = $http->post( $this->params->def('YOURLSUrl', 'http://joom.la').'/yourls-api.php', $data);
			if($response->code == 200)
			{
				$itemURL = $response->body;
			}
		}

		// Get the content and merge in the template; first see if $article->text is defined
		if (!isset($article->text))
		{
			$article->text = $article->introtext;
		}

		// Add extra template metadata
		$pattern = "/<img[^>]*src\=['\"]?(([^>]*)(jpg|gif|JPG|png|jpeg))['\"]?/";
		preg_match($pattern, $article->text, $matches);

		if (!empty($matches))
		{
			$document->addCustomTag('<meta property="og:image" content="' . $siteURL . '/' . $matches[1] . '"/>');
		}

		// Load the layout
		ob_start();
		$template = JPluginHelper::getLayoutPath('content', 'joomlarrssb');
		include $template;
		$output = ob_get_clean();

		// Add the output
		if ($position == 'top')
		{
			$article->introtext = $output . $article->introtext;
			$article->text      = $output . $article->text;
		}
		else
		{
			$article->introtext = $output . $article->introtext;
			$article->text .= $output;
		}

		return;
	}

	/**
	 * Function to retrieve the full article object
	 *
	 * @param   object  $article  The content object
	 *
	 * @return  object  The full content object
	 *
	 * @since   1.0
	 */
	private function loadArticle($article)
	{
		// Query the database for the article text
		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__content'))
			->where($this->db->quoteName('introtext') . ' = ' . $this->db->quote($article->text));
		$this->db->setQuery($query);

		return $this->db->loadObject();
	}
}
