<?php
/**
 * Plugin for integrating the Plyr mediaplayer into an article's content
 * 
 * Inspired by the AllVideos plugin from JoomlaWorks
 * 
 * PHP version 7
 *
 * @category   Content
 * @package    Joomla
 * @subpackage Content
 * @author     Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @copyright  2020 Frans-Willem Post
 * @license    https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link       https://fwiep.nl/     
 */
defined('_JEXEC') or die('Restricted access');

/**
 * Plugin for integrating the Plyr mediaplayer into an article's content
 *
 * @category   Content
 * @package    Joomla
 * @subpackage Content
 * @author     Frans-Willem Post (FWieP) <fwiep@fwiep.nl>
 * @copyright  2020 Frans-Willem Post
 * @license    https://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link       https://fwiep.nl/
 */
class PlgContentFwiepplyr extends JPlugin
{
    private $_pluginPath = '';
    private $_siteHost = '';
    
    private static $_ytTemplate = 'https://www.youtube-nocookie.com/embed/'.
        '{SOURCE}?iv_load_policy=3&amp;modestbranding=1&amp;rel=0&amp;'.
        'playsinline=1&amp;enablejsapi=1&amp;origin={ORIGIN}';

    /**
     * Process the article's text, replace plugin's tags with corresponding media
     *
     * @param string   $context context of the content being passed
     * @param object   $article article object
     * @param mixed    $params  article params
     * @param int|NULL $page    'page' number
     *
     * @return void
     */
    public function onContentPrepare(
        string $context, &$article, &$params, ?int $page = 0
    ) : void {
        
        // Check if plugin is enabled
        if (JPluginHelper::isEnabled('content', $this->_name) == false) {
            return;
        }
        $replacements = array(
            'mp3' => '<audio controls="controls"><source type="audio/mpeg" '.
                'src="{SOURCE}"/></audio>',
            'youtube' => '<div class="embed-responsive embed-responsive-16by9 '.
                ' plyr-video mb-3"><iframe class="embed-responsive-item" '.
                'src="'.self::$_ytTemplate.'" width="760" height="380" '.
                'allowfullscreen></iframe></div>'
        );
        $replaceRE = implode('|', array_keys($replacements));
        
        // Should the plugin process any further?
        if (preg_match("#{(?:".$replaceRE.")}#is", $article->text) == false) {
            return;
        }

        // Determine the plugin's absolute path to load additional files
        $document  = JFactory::getDocument();
        $this->_siteHost = JURI::base();
        $this->_pluginPath = sprintf(
            '%1$s/plugins/content/%2$s',
            JURI::base(true),
            $this->_name
        );
        
        // Add the plugin's stylesheet
        $cssUrl = sprintf('%s/css/plyr.min.css', $this->_pluginPath); 
        $document->addStyleSheet($cssUrl);
        
        // Indicate dependency on the jQuery framework
        JHtml::_('jquery.framework');
        
        // Add the plugin's compiled JavaScript
        $jsUrl = sprintf('%s/js/plyr.polyfilled.min.js', $this->_pluginPath);
        $document->addScript($jsUrl);
        
        // Add the plugin's JavaScript initialization
        $document->addScriptDeclaration($this->_getInitPlyrScript());
        
        // Replace the tags and their contents with the apropriate replacements
        foreach ($replacements as $tag => $replacement) {
            $matches = array();
            $re = "#(?:\{$tag\})(.*?)(?:\{/$tag\})#is";
            $count = preg_match_all($re, $article->text, $matches, PREG_SET_ORDER);
            
            if ($count == 0) {
                continue;
            }
            foreach ($matches as $m) {
                // $m[0] is complete tag match
                // $m[1] is tag content
                
                // Do some sanity checks
                switch ($tag) {
                
                case 'mp3':
                    
                    // If the file's extension isn't "mp3", skip to next
                    $ext = pathinfo($m[1], PATHINFO_EXTENSION);
                    if (strtolower($ext) != 'mp3') {
                        continue 2;
                    }
                    
                    // If the file doesn't exist locally, skip to next
                    $fnOnDisk = JPATH_SITE.'/'.$m[1];
                    if (!file_exists($fnOnDisk)) {
                        continue 2;
                    }
                    break;
                
                case 'youtube':
                    
                    // If the video ID doesn't match 11 valid characters,
                    // skip to next
                    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $m[1]) != 1) {
                        continue 2;
                    }
                    break;
                }

                $actualReplacement = str_replace(
                    ['{SOURCE}', '{ORIGIN}'],
                    [$m[1], $this->_siteHost],
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
    private function _getInitPlyrScript() : string
    {
        return <<<EOF

function addPlyr(selector) {
    if (typeof Plyr == 'undefined') {
      return;
    }
    const players = Plyr.setup(
        selector, {
            debug: false,
            iconUrl: '{$this->_pluginPath}/img/plyr.svg',
            settings : [],
            youtube : {
                noCookie: true,
                enablejsapi: 1,
                modestbranding: 1,
                iv_load_policy: 3,
                origin: '{$this->_siteHost}',
                playsinline: 1,
                rel: 0
            }
        }
    );
    return;
}
jQuery(function($){
    'use strict';
    addPlyr('audio, .plyr-video'); 
});
EOF;
    }
}