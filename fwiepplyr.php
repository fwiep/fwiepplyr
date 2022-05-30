<?php
/**
 * Plugin for integrating the Plyr mediaplayer into an article's content
 *
 * Inspired by the AllVideos plugin from JoomlaWorks
 *
 * PHP version 8
 *
 * @category	Content
 * @package		Joomla
 * @subpackage	Content
 * @author		Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @copyright	2022 Frans-Willem Post
 * @license		https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link		https://www.fwiep.nl/
 */
defined('_JEXEC') or die('Restricted access');

use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Plugin\CMSPlugin;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\WebAsset\WebAssetManager;

/**
 * Plugin for integrating the Plyr mediaplayer into an article's content
 *
 * @category	Content
 * @package		Joomla
 * @subpackage	Content
 * @author		Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @copyright	2022 Frans-Willem Post
 * @license		https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link		https://www.fwiep.nl/
 * @since		4.0.0
 */
class PlgContentFwiepplyr extends CMSPlugin
{
	/** @var string $pluginPath */
	private $pluginPath = '';

	/** @var string $pluginPathFull */
	private $pluginPathFull = '';

	/** @var string $siteHost */
	private $siteHost = '';

	/** @var string $ytTemplate */
	private static $ytTemplate = 'https://www.youtube-nocookie.com/embed/' .
		'{SOURCE}?iv_load_policy=3&amp;modestbranding=1&amp;rel=0&amp;' .
		'playsinline=1&amp;enablejsapi=1&amp;origin={ORIGIN}';

	/**
	 * Process the article's text, replace plugin's tags with corresponding media
	 *
	 * @param   string	 $context context of the content being passed
	 * @param   object	 $article article object
	 * @param   mixed	 $params  article params
	 * @param   int|NULL $page	  'page' number
	 *
	 * @return void
	 */
	public function onContentPrepare(
		string $context, &$article, &$params, ?int $page = 0
	) : void {

		// Check if plugin is enabled
		if (PluginHelper::isEnabled('content', $this->_name) == false)
		{
			return;
		}

		$replacements = [
			'mp3' => '<audio controls="controls"><source type="audio/mpeg" ' .
				'src="{SOURCE}"/></audio>',
			'youtube' => '<div class="embed-responsive embed-responsive-16by9 ' .
				' plyr-video mb-3"><iframe class="embed-responsive-item" ' .
				'src="' . self::$ytTemplate . '" width="760" height="380" ' .
				'allowfullscreen></iframe></div>'
		];
		$replaceRE = implode('|', array_keys($replacements));

		// Should the plugin process any further?
		if (preg_match("#{(?:" . $replaceRE . ")}#is", $article->text) == false)
		{
			return;
		}

		/** @var SiteApplication $app */
		$app = Factory::getContainer()->get(SiteApplication::class);
		$document = $app->getDocument();

		if (is_null($document))
		{
			return;
		}

		/** @var WebAssetManager $wa */
		$wa = $document->getWebAssetManager();

		// Determine the plugin's absolute path to load additional files
		$this->siteHost = Uri::base();
		$this->pluginPath = 'plugins/content/' . $this->_name;
		$this->pluginPathFull = Uri::base(true) . '/' . $this->pluginPath;

		// Add the plugin's stylesheet
		$cssUrl = sprintf('%s/css/plyr.min.css', $this->pluginPath);
		$wa->registerAndUseStyle('plg_fwiepplyr.style', $cssUrl);

		// Add the plugin's compiled JavaScript
		$jsUrl = sprintf('%s/js/plyr.polyfilled.min.js', $this->pluginPath);
		$wa->registerAndUseScript('plg_fwiepplyr.script', $jsUrl, [], [], []);

		// Add the plugin's JavaScript initialization
		$wa->addInlineScript($this->getInitPlyrScript());

		// Replace the tags and their contents with the apropriate replacements
		foreach ($replacements as $tag => $replacement)
		{
			$matches = array();
			$re = "#(?:\{$tag\})(.*?)(?:\{/$tag\})#is";
			$count = preg_match_all($re, $article->text, $matches, PREG_SET_ORDER);

			if ($count == 0)
			{
				continue;
			}

			foreach ($matches as $m)
			{
				// $m[0] is complete tag match
				// $m[1] is tag content

				// Do some sanity checks
				switch ($tag)
				{
					case 'mp3':

						// If the file's extension isn't "mp3", skip to next
						$ext = pathinfo($m[1], PATHINFO_EXTENSION);

						if (strtolower($ext) != 'mp3')
						{
							continue 2;
						}

						// If the file doesn't exist locally, skip to next
						$fnOnDisk = JPATH_SITE . '/' . $m[1];

						if (!file_exists($fnOnDisk))
						{
							continue 2;
						}
						break;

					case 'youtube':

						// If the video ID doesn't match 11 valid characters,
						// skip to next
						if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $m[1]) != 1)
						{
							continue 2;
						}
						break;
				}

				$actualReplacement = str_replace(
					['{SOURCE}', '{ORIGIN}'],
					[$m[1], $this->siteHost],
					$replacement
				);
				$article->text = str_replace(
					$m[0], $actualReplacement, $article->text
				);
			}
		}

		return;
	}

	/**
	 * Gets the Plyr-initialization script
	 *
	 * @return string
	 */
	private function getInitPlyrScript() : string
	{
		return <<<EOF

function addPlyr(selector) {
	if (typeof Plyr == 'undefined') {
	  return;
	}
	const players = Plyr.setup(
		selector, {
			debug: false,
			iconUrl: '{$this->pluginPathFull}/img/plyr.svg',
			settings : [],
			youtube : {
				noCookie: true,
				enablejsapi: 1,
				modestbranding: 1,
				iv_load_policy: 3,
				origin: '{$this->siteHost}',
				playsinline: 1,
				rel: 0
			}
		}
	);
	return;
}
document.addEventListener("DOMContentLoaded", function(event) { 
	'use strict';
	addPlyr('audio, .plyr-video'); 
});
EOF;
	}
}
