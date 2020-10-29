<?php
/**
 * Ridiculously Responsive Social Sharing Buttons for Joomla
 *
 * @copyright  Copyright (C) 2015 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

// We require com_content's route helper
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');

/**
 * Ridiculously Responsive Social Sharing Buttons for Joomla Content Plugin
 *
 * @since  1.0
 */
class PlgContentJoomlarrssb extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  1.0
	 */
	protected $app;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Database object
	 *
	 * @var    JDatabaseDriver
	 * @since  1.0
	 */
	protected $db;

	/**
	 * Flag if the category has been processed
	 *
	 * Since Joomla lacks a plugin event specifically for category related data, we must process this ourselves using the
	 * available data from the request.
	 *
	 * @var    boolean
	 * @since  1.1
	 */
	private static $hasProcessedCategory = false;

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
		/*
		 * Validate the plugin should run in the current context
		 */

		// Context check - This only works for com_content
		if (strpos($context, 'com_content') === false)
		{
			return;
		}

		// Additional context check; we only want this for the component!
		if (strpos($this->app->scope, 'mod_') === 0)
		{
			return;
		}

		// Check if the plugin is enabled
		if (PluginHelper::isEnabled('content', 'joomlarrssb') == false)
		{
			return;
		}

		// Make sure the document is an HTML document
		$document = $this->app->getDocument();

		if ($document->getType() != 'html')
		{
			return;
		}

		/*
		 * Start processing the plugin event
		 */

		// Set the parameters
		$displayEmail       = $this->params->get('displayEmail', '1');
		$displayFacebook    = $this->params->get('displayFacebook', '1');
		$displayLinkedin    = $this->params->get('displayLinkedin', '1');
		$displayPinterest   = $this->params->get('displayPinterest', '1');
		$displayTwitter     = $this->params->get('displayTwitter', '1');
		$selectedCategories = $this->params->def('displayCategories', '');
		$position           = $this->params->def('displayPosition', 'top');
		$view               = $this->app->input->getCmd('view', '');
		$shorten            = $this->params->get('useYOURLS', true);

		// Check whether we're displaying the plugin in the current view
		if ($this->params->get('view' . ucfirst($view), '1') == '0')
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

		// Build the URL for the plugins to use - the site URL should only be the scheme and host segments, JRoute will take care of the rest
		$siteURL = Uri::getInstance()->toString(['scheme', 'host', 'port']);
		$itemURL = $siteURL . Route::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catid));

		// Check if we have an intro text image (Priority: fulltext image, intro image, content image, category image)
		$images = json_decode($article->images);

		if (isset($images->image_fulltext) && !empty($images->image_fulltext))
		{
			$imageOg = $images->image_fulltext;
		}
		elseif (isset($images->image_intro) && !empty($images->image_intro))
		{
			$imageOg = $images->image_intro;
		}
		else
		{
			// Get the content and merge in the template; first see if $article->text is defined
			if (!isset($article->text))
			{
				$article->text = $article->introtext;
			}

			// Always run this preg_match as the results are also used in the layout
			preg_match_all('/<img[^>]+>/i', $article->text, $images);

			if (isset($images[0][0]))
			{
				// https://paulund.co.uk/get-image-src-with-php
				preg_match_all('@src="([^"]+)"@', $images[0][0], $matches);
			}

			$imageOg = isset($matches[1][0]) ? $matches[1][0] : '';

			// Check for category image
			if (empty($imageOg))
			{
				// We get the article mostly from content plugin, so we need to do a query and can't do a join..
				$query = $this->db->getQuery(true);

				$query->select('params')
					->from($this->db->quoteName('#__categories'))
					->where($this->db->quoteName('id') . ' = ' . $this->db->q($article->catid));

				$this->db->setQuery($query);

				$result = $this->db->loadResult();

				if ($result)
				{
					$categoryParams = json_decode($result);

					if (isset($categoryParams->image) && !empty($categoryParams->image))
					{
						$imageOg = $categoryParams->image;
					}
				}
			}
		}

		// Make sure the image has an absolute URL
		if (!empty($imageOg))
		{
			// If the image isn't prefixed with http then assume it's relative and put the site URL in front
			if (strpos($imageOg, 'http') !== 0)
			{
				$imageOg = substr(Uri::root(), 0, -1) . (substr($imageOg, 0, 1) !== '/' ? '/' : '') . $imageOg;
			}
		}

		/*
		 * Add template metadata per the context
		 */

		// The metadata in this check should only be applied on a single article view
		if ($context === 'com_content.article')
		{
			if (!empty($imageOg))
			{
				if (!$document->getMetaData('og:image'))
				{
					$document->setMetaData('og:image', $imageOg, 'property');

					// The Image libary can not handle remote images.
					$imageForInfo = str_replace(Uri::root(), '', $imageOg);

					// Make sure the file exits ..
					if (file_exists($imageForInfo))
					{
						$imageInfo = Image::getImageFileProperties($imageForInfo);

						$document->setMetaData('og:image:width', $imageInfo->width, 'property');
						$document->setMetaData('og:image:height', $imageInfo->height, 'property');
						$document->setMetaData('og:image:type', $imageInfo->mime, 'property');
					}
				}

				if (!$document->getMetaData('twitter:image'))
				{
					$document->setMetaData('twitter:image', $imageOg);
				}
			}

			$description = !empty($article->metadesc) ? $article->metadesc : $article->introtext;
			$description = HTMLHelper::_('string.truncate', $description, 200, true, false);

			// OpenGraph metadata
			if (!$document->getMetaData('og:description'))
			{
				$document->setMetaData('og:description', $description, 'property');
			}

			if (!$document->getMetaData('og:title'))
			{
				$document->setMetaData('og:title', $article->title, 'property');
			}

			if (!$document->getMetaData('og:type'))
			{
				$document->setMetaData('og:type', 'article', 'property');
			}

			if (!$document->getMetaData('og:url'))
			{
				$document->setMetaData('og:url', $itemURL, 'property');
			}

			// Twitter Card metadata
			if (!$document->getMetaData('twitter:description'))
			{
				$document->setMetaData('twitter:description', $description);
			}

			if (!$document->getMetaData('twitter:title'))
			{
				$document->setMetaData('twitter:title', HTMLHelper::_('string.truncate', $article->title, 70, true, false));
			}
		}

		// Check that we're actually displaying a button
		if ($displayEmail == '0' && $displayFacebook == '0' && $displayLinkedin == '0' && $displayPinterest == '0' && $displayTwitter == '0')
		{
			return;
		}

		// Prevent recursion when crawled by YOURLs
		$agent = $this->app->input->server->get('HTTP_USER_AGENT', '', 'cmd');

		// Apply our shortened URL if configured only when whe are not offline and when it is not YOURLS itself
		if ($shorten && (stristr($agent, 'YOURLS') === false) && !$this->app->get('offline'))
		{
			$data = [
				'signature' => $this->params->get('YOURLSAPIKey'),
				'action'    => 'shorturl',
				'url'       => $itemURL,
				'format'    => 'simple'
			];

			try
			{
				$response = HttpFactory::getHttp()->post($this->params->get('YOURLSUrl', 'https://joom.la') . '/yourls-api.php', $data);

				if ($response->code == 200)
				{
					$itemURL = $response->body;
				}
			}
			catch (Exception $e)
			{
				// In case of an error connecting out here, we can still use the 'real' URL. Carry on.
			}
		}

		// Load the layout
		ob_start();
		$template = PluginHelper::getLayoutPath('content', 'joomlarrssb');
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
	 * Listener for the `onContentPrepare` event
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   object   &$article  The article object.  Note $article->text is also available
	 * @param   object   &$params   The article params
	 * @param   integer  $page      The 'page' number
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function onContentPrepare($context, &$article, &$params, $page)
	{
		/*
		 * Validate the plugin should run in the current context
		 */

		// Has the plugin already triggered?
		if (self::$hasProcessedCategory)
		{
			return;
		}

		// Context check - This only works for com_content
		if (strpos($context, 'com_content') === false)
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Check if the plugin is enabled
		if (PluginHelper::isEnabled('content', 'joomlarrssb') == false)
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Make sure the document is an HTML document
		$document = $this->app->getDocument();

		if ($document->getType() != 'html')
		{
			self::$hasProcessedCategory = true;

			return;
		}

		/*
		 * Start processing the plugin event
		 */

		// Set the parameters
		$view = $this->app->input->getCmd('view', '');

		// Check whether we're displaying the plugin in the current view
		if ($this->params->get('view' . ucfirst($view), '1') == '0')
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// The featured view is not yet supported and the article view never will be
		if (in_array($view, ['article', 'featured']))
		{
			self::$hasProcessedCategory = true;

			return;
		}

		// Get the requested category
		/** @var Category $category */
		$category = Table::getInstance('Category');
		$category->load($this->app->input->getUint('id'));

		// Build the URL for the plugins to use - the site URL should only be the scheme and host segments, JRoute will take care of the rest
		$siteURL = Uri::getInstance()->toString(['scheme', 'host', 'port']);
		$itemURL = $siteURL . Route::_(ContentHelperRoute::getCategoryRoute($category->id));

		// Check if there is a category image to use for the metadata
		$categoryParams = json_decode($category->params, true);

		if (isset($categoryParams['image']) && !empty($categoryParams['image']))
		{
			$imageURL = $categoryParams['image'];

			// If the image isn't prefixed with http then assume it's relative and put the site URL in front
			if (strpos($imageURL, 'http') !== 0)
			{
				$imageURL = substr(Uri::root(), 0, -1) . (substr($imageURL, 0, 1) !== '/' ? '/' : '') . $imageURL;
			}

			if (!$document->getMetaData('og:image'))
			{
				$document->setMetaData('og:image', $imageURL, 'property');

				// The Image libary can not handle remote images.
				$imageForInfo = str_replace(Uri::root(), '', $imageURL);

				if (file_exists($imageForInfo))
				{
					$imageInfo = Image::getImageFileProperties($imageForInfo);

					$document->setMetaData('og:image:width', $imageInfo->width, 'property');
					$document->setMetaData('og:image:height', $imageInfo->height, 'property');
					$document->setMetaData('og:image:type', $imageInfo->mime, 'property');
				}
			}

			if (!$document->getMetaData('twitter:image'))
			{
				$document->setMetaData('twitter:image', $imageURL);
			}
		}

		$description = !empty($category->metadesc) ? $category->metadesc : strip_tags($category->description);

		// OpenGraph metadata
		if (!$document->getMetaData('og:title'))
		{
			$document->setMetaData('og:title', $category->title, 'property');
		}

		if (!$document->getMetaData('og:type'))
		{
			$document->setMetaData('og:type', 'article', 'property');
		}

		if (!$document->getMetaData('og:url'))
		{
			$document->setMetaData('og:url', $itemURL, 'property');
		}

		// Twitter Card metadata
		if (!$document->getMetaData('twitter:title'))
		{
			$document->setMetaData('twitter:title', HTMLHelper::_('string.truncate', $category->title, 70, true, false));
		}

		// Add the description too if it isn't empty
		if (!empty($category->description))
		{
			if (!$document->getMetaData('og:description'))
			{
				$document->setMetaData('og:description', $description, 'property');
			}

			if (!$document->getMetaData('twitter:description'))
			{
				$document->setMetaData('twitter:description', $description);
			}
		}

		// We're done here
		self::$hasProcessedCategory = true;
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
