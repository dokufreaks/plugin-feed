<?php
/**
 * XML feed export
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/events.php');
require_once(DOKU_INC.'inc/parserutils.php');
require_once(DOKU_INC.'inc/feedcreator.class.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/pageutils.php');

//close session
session_write_close();

$plugin = $_REQUEST['plugin'];
$fn     = $_REQUEST['fn'];
$ns     = cleanID(urldecode($_REQUEST['ns']));
$num    = $_REQUEST['num'];
$other  = urldecode($_REQUEST['tag'].$_REQUEST['user']);
$title  = urldecode($_REQUEST['title']);
$type   = $_REQUEST['type'];

if ($type == '') $type = $conf['rss_type'];

switch ($type){
  case 'rss':
    $type = 'RSS0.91';
    $mime = 'text/xml';
    break;
  case 'rss2':
    $type = 'RSS2.0';
    $mime = 'text/xml';
    break;
  case 'atom':
    $type = 'ATOM0.3';
    $mime = 'application/xml';
    break;
  case 'atom1':
    $type = 'ATOM1.0';
    $mime = 'application/atom+xml';
    break;
  default:
    $type = 'RSS1.0';
    $mime = 'application/xml';
}

// the feed is dynamic - we need a cache for each combo
// (but most people just use the default feed so it's still effective)
$cache = getCacheName($plugin.$fn.$ns.$num.$other.$type.$_SERVER['REMOTE_USER'],'.feed');
$cmod = @filemtime($cache); // 0 if not exists
if ($cmod && (@filemtime(DOKU_CONF.'local.php') > $cmod
  || @filemtime(DOKU_CONF.'dokuwiki.php') > $cmod)){
  // ignore cache if feed prefs may have changed
  $cmod = 0;
}

// check cacheage and deliver if nothing has changed since last
// time or the update interval has not passed, also handles conditional requests
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Type: application/xml; charset=utf-8');
if ($cmod &&
  (($cmod + $conf['rss_update'] > time()) || ($cmod > @filemtime($conf['changelog'])))){
  http_conditionalRequest($cmod);
  if($conf['allowdebug']) header("X-CacheUsed: $cache");
  print io_readFile($cache);
  exit;
} else {
  http_conditionalRequest(time());
}

// create new feed
$rss = new DokuWikiFeedCreator();
$rss->title = $title;
if ($ns) $rss->title .= ' '.ucwords(str_replace(array('_', ':'), array(' ', ': '), $ns));
elseif ($other) $rss->title .= ' '.ucwords(str_replace('_', ' ', $other));
$rss->title .= ' · '.$conf['title'];
$rss->link  = DOKU_URL;
$rss->syndicationURL = DOKU_PLUGIN.'feed/feed.php';
$rss->cssStyleSheet  = DOKU_URL.'lib/exe/css.php?s=feed';

$image = new FeedImage();
$image->title = $conf['title'];
$image->url = DOKU_URL."lib/images/favicon.ico";
$image->link = DOKU_URL;
$rss->image = $image;

if ($po =& plugin_load('helper', $plugin)) feed_getPages($rss, $po, $ns, $num, $fn);

$feed = $rss->createFeed($type, 'utf-8');

// save cachefile
io_saveFile($cache, $feed);

// finally deliver
print $feed;

/* ---------- */

/**
 * Add pages given by plugin to feed object
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Esther Brunner <wikidesign@gmail.com>
 */
function feed_getPages(&$rss, &$po, $ns, $num, $fn){
  global $conf;
  
  if ((!$num) || (!is_numeric($num))) $num = $conf['recent'];
  
  // get the pages for our namespace
  $pages = $po->$fn($ns, $num);
  if (!$pages) return false;
   
  foreach ($pages as $page){
    $item = new FeedItem();
    
    list($id, $hash) = explode('#', $page['id'], 2);
    $meta = p_get_metadata($id);

    // title
    if ($page['title']) $item->title = $page['title'];
    elseif ($meta['title']) $item->title = $meta['title'];
    else $item->title = ucwords($id);

    // link
    $item->link = wl($page['id'], '', true, '&') . '#' . $page['anchor'];
    
    // description
    if ($page['desc']) $item->description = htmlspecialchars($page['desc']);
    else $item->description = htmlspecialchars($meta['description']['abstract']);
    
    // date
    $item->date = date('r', $page['date']);
    
    // category
    if ($page['cat']){
       $item->category = $page['cat'];
    } elseif ($meta['subject']){
      if (is_array($meta['subject'])) $item->category = $meta['subject'][0];
      else $item->category = $meta['subject'];
    }
    
    // creator
    if ($page['user']) $item->author = $page['user'];
    else $item->author = $meta['creator'];

    $rss->addItem($item);
  }
  
}

//Setup VIM: ex: et ts=2 enc=utf-8 :
