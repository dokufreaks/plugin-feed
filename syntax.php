<?php
/**
 * Feed Plugin: creates a feed link for a given blog namespace
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_feed extends DokuWiki_Syntax_Plugin {

  /**
   * To support feeds in your plugin, add an array here
   *
   * The array key is important: $plugin->getLang($key) is used for the feed title and a
   * function 'get'.$key (for example getTopic for 'topic') must exist in your helper.php!
   *
   * The first param should eigther be 'id' or 'ns' as it will go through cleanID()
   *
   * Unless the second parameter is 'num', your plugin will have to handle it on its own
   */
  function _registeredFeeds(){
    $feeds = array(
      'blog'     => array('plugin' => 'blog', 'params' => array('ns', 'num')),
      'comments' => array('plugin' => 'discussion', 'params' => array('ns', 'num')),
      'threads'  => array('plugin' => 'discussion', 'params' => array('ns', 'num')),
      'editor'   => array('plugin' => 'editor', 'params' => array('ns', 'user')),
      'topic'    => array('plugin' => 'tag', 'params' => array('ns', 'tag')),
      'tasks'    => array('plugin' => 'task', 'params' => array('ns', 'filter')),
    );
    foreach ($feeds as $key => $value){
      if (!@file_exists(DOKU_PLUGIN.$value['plugin'].'/helper.php')) unset($feeds[$key]);
    }
    return $feeds;
  }

  function getInfo(){
    return array(
      'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
      'email'  => 'dokuwiki@chimeric.de',
      'date'   => '2007-01-16',
      'name'   => 'Feed Plugin',
      'desc'   => 'Generates feeds for other plugins',
      'url'    => 'http://wiki.splitbrain.org/plugin:feed',
    );
  }

  function getType(){ return 'substition'; }
  function getSort(){ return 308; }
  
  function connectTo($mode){
    $this->Lexer->addSpecialPattern('\{\{\w+?feed>.+?\}\}', $mode, 'plugin_feed');
  }

  function handle($match, $state, $pos, &$handler){
    global $ID;
    
    $match = substr($match, 2, -2); // strip markup
    list($feed, $data) = explode('>', $match, 2);
    $feed = substr($feed, 0, -4);
    list($params, $title) = explode('|', $data, 2);
    list($param1, $param2) = explode('?', $params, 2);
    
    if (($param1 == '*') || ($param1 == ':')) $param1 = '';
    elseif ($param1 == '.') $param1 = getNS($ID);
    else $param1 = cleanID($param1);

    return array($feed, $param1, trim($param2), trim($title));
  }

  function render($mode, &$renderer, $data){
    list($feed, $p1, $p2, $title) = $data;
    
    $feeds = $this->_registeredFeeds();
    if (!isset($feeds[$feed])){
      msg('Unknown plugin feed '.hsc($feed).'.', -1);
      return false;
    }
    
    $plugin = $feeds[$feed]['plugin'];
    if (plugin_isdisabled($plugin) || (!$po =& plugin_load('helper', $plugin))){
      msg('Missing or invalid helper plugin for '.hsc($feed).'.', -1);
      return false;
    }
    
    $fn = 'get'.ucwords($feed);
        
    if (!$title) $title = ucwords(str_replace(array('_', ':'), array(' ', ': '), $p1));
    if (!$title) $title = ucwords(str_replace('_', ' ', $p2));
  
    if($mode == 'xhtml'){
      $url = DOKU_BASE.'lib/plugins/feed/feed.php?plugin='.$plugin.'&amp;fn='.$fn.
        '&amp;'.$feeds[$feed]['params'][0].'='.urlencode($p1);
      if ($p2) $url .= '&amp;'.$feeds[$feed]['params'][1].'='.urlencode($p2);
      $url .= '&amp;title='.urlencode($po->getLang($feed));
      $title = hsc($title);
      
      $renderer->doc .= '<a href="'.$url.'" class="feed" rel="nofollow"'.
        ' type="application/rss+xml" title="'.$title.'">'.$title.'</a>';
                
      return true;
    
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      if ($renderer->capture) $renderer->doc .= $title;
      
      return true;
    }
    return false;
  }
        
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
