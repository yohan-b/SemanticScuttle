<?php
/***************************************************************************
 Copyright (C) 2004 - 2006 Scuttle project
 http://sourceforge.net/projects/scuttle/
 http://scuttle.org/

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 ***************************************************************************/

require_once 'www-header.php';
/* Service creation: only useful services are created */
$bookmarkservice =SemanticScuttle_Service_Factory::get('Bookmark');
$cacheservice =SemanticScuttle_Service_Factory::get('Cache');

/* Managing all possible inputs */
isset($_GET['page']) ? define('GET_PAGE', $_GET['page']): define('GET_PAGE', 0);
isset($_GET['sort']) ? define('GET_SORT', $_GET['sort']): define('GET_SORT', '');
isset($_GET['batch']) ? define('GET_BATCH', $_GET['batch']): define('GET_BATCH', 0);

/* Managing current logged user */
$currentUser = $userservice->getCurrentObjectUser();

/* Managing path info */
list($url, $cat) = explode('/', $_SERVER['PATH_INFO']);


if (!$cat) {
	header('Location: '. createURL('populartags'));
	exit;
}

$titleTags = explode('+', filter($cat));
$pagetitle = T_('Tags') .': ';
for($i = 0; $i<count($titleTags);$i++) {
	$pagetitle.= $titleTags[$i].'<a href="'.createUrl('tags', aggregateTags($titleTags, '+', $titleTags[$i])).'" title="'.T_('Remove the tag from the selection').'">*</a> + ';
}
$pagetitle = substr($pagetitle, 0, strlen($pagetitle) - strlen(' + ')); 


//$cattitle = str_replace('+', ' + ', $cat);

if ($usecache) {
	// Generate hash for caching on
	if ($userservice->isLoggedOn()) {
		$hash = md5($_SERVER['REQUEST_URI'] . $currentUser->getId());
	} else {
		$hash = md5($_SERVER['REQUEST_URI']);
	}

	// Cache for 30 minutes
	$cacheservice->Start($hash, 1800);
}

// We need all bookmarks (without paging) for batch tagging.
if ($userservice->isLoggedOn() && GET_BATCH) {
   $currentUsername = $currentUser->getUsername();
   $completebookmarks = $bookmarkservice->getBookmarks(0, null, null, $cat, null, getSortOrder());
   $templatename = 'editbookmark.tpl';
   $addresses2 = array();
   $titles2 = array();
   $tags2 = array();
   foreach ($completebookmarks['bookmarks'] as $key => &$row) {
      $addresses2[$row['bId']] = $row['bAddress'];
      $titles2[$row['bId']] = $row['bTitle'];
      $row = $bookmarkservice->getBookmark($row['bId'], true);
      $tags2[] = $row['tags'];
   }
   $alltags2 = array_unique(call_user_func_array('array_merge', $tags2));
   $commontags2 = call_user_func_array('array_intersect', $tags2);
   $tplVars['row'] = array(
        'bTitle' => $titles2,
        'bAddress' => $addresses2,
        'bDescription' => '',
        'bPrivateNote' => '',
        'tags' => array(),
        'bStatus' => $GLOBALS['defaults']['privacy']
   );
   $tplVars['pagetitle'] = T_('Add a Bookmark');
   $tplVars['subtitle'] = T_('Add a Bookmark');
   $tplVars['btnsubmit'] = T_('Add Bookmark');
   $tplVars['popup'] = null;
   $tplVars['batch'] = '1';
   $tplVars['alltags'] = implode(', ', $alltags2);
   $tplVars['commontags'] = implode(', ', $commontags2);
   $tplVars['formaction']  = createURL('bookmarks', $currentUsername);
   $tplVars['loadjs'] = true;
   $templateservice->loadTemplate($templatename, $tplVars);
}
else {

// Header variables
$tplVars['pagetitle'] = T_('Tags') .': '. $cat;
$tplVars['loadjs'] = true;
$tplVars['rsschannels'] = array(
    array(
        sprintf(T_('%s: tagged with "%s"'), $sitename, $cat),
        createURL('rss', 'all/' . filter($cat, 'url'))
        . '?sort='.getSortOrder()
    )
);

if ($userservice->isLoggedOn()) {
    if ($userservice->isPrivateKeyValid($currentUser->getPrivateKey())) {
        $currentUsername = $currentUser->getUsername();
        array_push(
            $tplVars['rsschannels'],
            array(
                sprintf(
                    T_('%s: tagged with "%s" (+private %s)'),
                    $sitename, $cat, $currentUsername
                ),
                createURL('rss', filter($currentUsername, 'url'))
                . '?sort=' . getSortOrder()
                . '&privateKey=' . $currentUser->getPrivateKey()
            )
        );
    }
}

// Pagination
$perpage = getPerPageCount($currentUser);
if (intval(GET_PAGE) > 1) {
	$page = intval(GET_PAGE);
	$start = ($page - 1) * $perpage;
} else {
	$page = 0;
	$start = 0;
}

$tplVars['page'] = $page;
$tplVars['start'] = $start;
$tplVars['popCount'] = 25;
$tplVars['currenttag'] = $cat;

if ($userservice->isLoggedOn() && ! empty($GLOBALS['shoulderSurfingProtectedTag']) && ! isset($_COOKIE["noshoulderSurfingProtection"])) {
        $tag2tagservice = SemanticScuttle_Service_Factory::get('Tag2Tag');
        $b2tservice     = SemanticScuttle_Service_Factory::get('Bookmark2Tag');
        $currentUserID = $currentUser->getId();
        $alltags = $b2tservice->getTags($currentUserID);
        $shoulderSurfingProtectedTags = $tag2tagservice->getAllLinkedTags($GLOBALS['shoulderSurfingProtectedTag'], '>', $currentUserID, array());
        $shoulderSurfingProtectedTags[] = $GLOBALS['shoulderSurfingProtectedTag'];
        $flag = 0;
        if (! in_array($cat, $shoulderSurfingProtectedTags, true)) {
                foreach ($alltags as $tag) {
                        if ($tag['tag'] === $cat) {
                                $flag = 1;
                                break;
                        }

                }
        }
        if ($flag) {
                $tplVars['sidebar_blocks'][] = 'tagactions';
                $tplVars['sidebar_blocks'][] = 'linked';
                $tplVars['sidebar_blocks'][] = 'related';
                $tplVars['sidebar_blocks'][] = 'menu2';
        }
}
else {
                $tplVars['sidebar_blocks'][] = 'tagactions';
                $tplVars['sidebar_blocks'][] = 'linked';
                $tplVars['sidebar_blocks'][] = 'related';
                $tplVars['sidebar_blocks'][] = 'menu2';
}

$tplVars['subtitlehtml'] = $pagetitle;
$tplVars['bookmarkCount'] = $start + 1;
$bookmarks = $bookmarkservice->getBookmarks($start, $perpage, NULL, $cat, NULL, getSortOrder());
$tplVars['total'] = $bookmarks['total'];
$tplVars['bookmarks'] = $bookmarks['bookmarks'];
$tplVars['cat_url'] = createURL('bookmarks', '%1$s/%2$s');
$tplVars['nav_url'] = createURL('tags', '%2$s%3$s');

$templateservice->loadTemplate('bookmarks.tpl', $tplVars);
}
if ($usecache) {
	// Cache output if existing copy has expired
	$cacheservice->End($hash);
}

?>
