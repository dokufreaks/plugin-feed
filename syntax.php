<?php
/**
 * Feed Plugin: creates a feed link for a given blog namespace
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Class syntax_plugin_feed
 */
class syntax_plugin_feed extends DokuWiki_Syntax_Plugin {

    /**
     * To support feeds in your plugin, add an array here
     *
     * The array key is important:
     *   - $plugin->getLang($key) is used for the feed title
     *   - and a function 'get'.$key (for example getTopic for 'topic') must exist in your helper.php!
     *
     * The first param should eigther be 'id' or 'ns' as it will go through cleanID()
     *
     * Unless the second parameter is 'num', your plugin will have to handle it on its own
     */
    protected function _registeredFeeds() {
        $feeds = array(
            'blog'      => array('plugin' => 'blog',        'params' => array('ns', 'num')),
            'comments'  => array('plugin' => 'discussion',  'params' => array('ns', 'num')),
            'threads'   => array('plugin' => 'discussion',  'params' => array('ns', 'num')),
            'editor'    => array('plugin' => 'editor',      'params' => array('ns', 'user')),
            'topic'     => array('plugin' => 'tag',         'params' => array('ns', 'tag')),
            'tasks'     => array('plugin' => 'task',        'params' => array('ns', 'filter')),
        );
        foreach($feeds as $key => $value) {
            if(!@file_exists(DOKU_PLUGIN . $value['plugin'] . '/helper.php')) {
                unset($feeds[$key]);
            }
        }
        return $feeds;
    }

    /**
     * Syntax Type
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php
     *
     * @return string
     */
    public function getType() {
        return 'substition';
    }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    public function getSort() {
        return 308;
    }

    /**
     * @param string $mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{\w+?feed>.+?\}\}', $mode, 'plugin_feed');
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  array Return an array with all data you want to use in render
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;

        $match = substr($match, 2, -2); // strip markup
        list($feed, $data) = explode('>', $match, 2);
        $feed = substr($feed, 0, -4);
        list($params, $title) = explode('|', $data, 2);
        list($namespace, $parameter) = explode('?', $params, 2);

        if(($namespace == '*') || ($namespace == ':')) {
            $namespace = '';
        } elseif($namespace == '.') {
            $namespace = getNS($ID);
        } else {
            $namespace = cleanID($namespace);
        }

        return array($feed, $namespace, trim($parameter), trim($title));
    }

    /**
     * Handles the actual output creation.
     *
     * @param   $mode     string        output format being rendered
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        list($feed, $namespace, $parameter, $title) = $data;

        $feeds = $this->_registeredFeeds();
        if(!isset($feeds[$feed])) {
            msg('Unknown plugin feed ' . hsc($feed) . '.', -1);
            return false;
        }

        $plugin = $feeds[$feed]['plugin'];
        if(plugin_isdisabled($plugin) || (!$po =& plugin_load('helper', $plugin))) {
            msg('Missing or invalid helper plugin for ' . hsc($feed) . '.', -1);
            return false;
        }

        $fn = 'get' . ucwords($feed);

        if(!$title) $title = ucwords(str_replace(array('_', ':'), array(' ', ': '), $namespace));
        if(!$title) $title = ucwords(str_replace('_', ' ', $parameter));

        if($mode == 'xhtml') {
            /** @var Doku_Renderer_xhtml $renderer */
            $url = DOKU_BASE . 'lib/plugins/feed/feed.php?plugin=' . $plugin .
                '&amp;fn=' . $fn .
                '&amp;' . $feeds[$feed]['params'][0] . '=' . urlencode($namespace);
            if($parameter) {
                $url .= '&amp;' . $feeds[$feed]['params'][1] . '=' . urlencode($parameter);
            }
            $url .= '&amp;title=' . urlencode($po->getLang($feed));

            $title = hsc($title);
            $renderer->doc .= '<a href="' . $url . '" class="feed" rel="nofollow"' .
                ' type="application/rss+xml" title="' . $title . '">' . $title . '</a>';

            return true;

            // for metadata renderer
        } elseif($mode == 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */
            if($renderer->capture) {
                $renderer->doc .= $title;
            }

            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
