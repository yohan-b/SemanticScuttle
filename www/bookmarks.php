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
isset($_GET['action']) ? define('GET_ACTION', $_GET['action']): define('GET_ACTION', '');
isset($_POST['submitted']) ? define('POST_SUBMITTED', $_POST['submitted']): define('POST_SUBMITTED', '');

// define does not support arrays before PHP version 7
isset($_GET['title']) ? $TITLE = $_GET['title']: $TITLE = array();
//isset($_GET['title']) ? define('GET_TITLE', $_GET['title']): define('GET_TITLE', '');
isset($_GET['address']) ? $ADDRESS = $_GET['address']: $ADDRESS = array();
//isset($_GET['address']) ? define('GET_ADDRESS', $_GET['address']): define('GET_ADDRESS', '');
isset($_GET['description']) ? define('GET_DESCRIPTION', $_GET['description']): define('GET_DESCRIPTION', '');
isset($_GET['privateNote']) ? define('GET_PRIVATENOTE', $_GET['privateNote']): define('GET_PRIVATENOTE', '');
isset($_GET['tags']) ? define('GET_TAGS', $_GET['tags']): define('GET_TAGS', '');
isset($_GET['copyOf']) ? define('GET_COPYOF', $_GET['copyOf']): define('GET_COPYOF', '');

// define does not support arrays before PHP version 7
isset($_POST['title']) ? $TITLE = $_POST['title']: $TITLE = array();
//isset($_POST['title']) ? define('POST_TITLE', $_POST['title']): define('POST_TITLE', '');
isset($_POST['address']) ? $ADDRESS = $_POST['address']: $ADDRESS = array();
//isset($_POST['address']) ? define('POST_ADDRESS', $_POST['address']): define('POST_ADDRESS', '');
isset($_POST['description']) ? define('POST_DESCRIPTION', $_POST['description']): define('POST_DESCRIPTION', '');
isset($_POST['privateNote']) ? define('POST_PRIVATENOTE', $_POST['privateNote']): define('POST_PRIVATENOTE', '');
isset($_POST['status']) ? define('POST_STATUS', $_POST['status']): define('POST_STATUS', '');
isset($_POST['referrer']) ? define('POST_REFERRER', $_POST['referrer']): define('POST_REFERRER', '');

isset($_GET['popup']) ? define('GET_POPUP', $_GET['popup']): define('GET_POPUP', '');
isset($_POST['popup']) ? define('POST_POPUP', $_POST['popup']): define('POST_POPUP', '');

isset($_GET['page']) ? define('GET_PAGE', $_GET['page']): define('GET_PAGE', 0);
isset($_GET['sort']) ? define('GET_SORT', $_GET['sort']): define('GET_SORT', '');
isset($_GET['batch']) ? define('GET_BATCH', $_GET['batch']): define('GET_BATCH', 0);

if (!isset($_POST['tags'])) {
    $_POST['tags'] = array();
}
if (!isset($_POST['removetags'])) {
    $_POST['removetags'] = array();
}
//echo '<p>' . var_export($_POST, true) . '</p>';die();
if (! is_array($ADDRESS)) {
    $ADDRESS = array($ADDRESS);
}

if (! is_array($TITLE)) {
    $TITLE = array($TITLE);
}

if ((GET_ACTION == "add") && !$userservice->isLoggedOn()) {
	$loginqry = str_replace("'", '%27', stripslashes($_SERVER['QUERY_STRING']));
	header('Location: '. createURL('login', '?'. $loginqry));
	exit();
}

if ($userservice->isLoggedOn()) {
	$currentUser = $userservice->getCurrentObjectUser();
	$currentUserID = $currentUser->getId();
	$currentUsername = $currentUser->getUsername();
}


@list($url, $user, $cat) = isset($_SERVER['PATH_INFO']) ? explode('/', $_SERVER['PATH_INFO']) : NULL;


$endcache = false;
if ($usecache) {
	// Generate hash for caching on
	$hash = md5($_SERVER['REQUEST_URI'] . $user);

	// Don't cache if its users' own bookmarks
	if ($userservice->isLoggedOn()) {
		if ($currentUsername != $user) {
			// Cache for 5 minutes
			$cacheservice->Start($hash);
			$endcache = true;
		}
	} else {
		// Cache for 30 minutes
		$cacheservice->Start($hash, 1800);
		$endcache = true;
	}
}

$pagetitle = $rssCat = $catTitle = '';
if ($user) {
	if (is_int($user)) {
		$userid = intval($user);
	} else {
		if (!($userinfo = $userservice->getUserByUsername($user))) {
			$tplVars['error'] = sprintf(T_('User with username %s was not found'), $user);
			$templateservice->loadTemplate('error.404.tpl', $tplVars);
			exit();
		} else {
			$userid = $userinfo['uId'];
		}
	}
	$pagetitle .= ': '. $user;
}
if ($cat) {
	$catTitle = ': '. str_replace('+', ' + ', $cat);

	$catTitleWithUrls = ': ';
	$titleTags = explode('+', filter($cat));
	for($i = 0; $i<count($titleTags);$i++) {
		$catTitleWithUrls.= $titleTags[$i].'<a href="'.createUrl('bookmarks', $user.'/'.aggregateTags($titleTags, '+', $titleTags[$i])).'" title="'.T_('Remove the tag from the selection').'">*</a> + ';
	}
	$catTitleWithUrls = substr($catTitleWithUrls, 0, strlen($catTitleWithUrls) - strlen(' + '));

	$pagetitle .= $catTitleWithUrls;
}
else
{
	$catTitleWithUrls = '';
}
$pagetitle = substr($pagetitle, 2);

// Header variables
$tplVars['loadjs'] = true;

// ADD A BOOKMARK
$saved = false;
$templatename = 'bookmarks.tpl';
if ($userservice->isLoggedOn() && POST_SUBMITTED != '') {
	if (!$TITLE || !$ADDRESS) {
		$tplVars['error'] = T_('Your bookmark must have a title and an address');
		$templatename = 'editbookmark.tpl';
        } 
        else {
		$address = array_map('trim', $ADDRESS);
                $valid = 1;
                foreach($address as $value) {        
                        if (!SemanticScuttle_Model_Bookmark::isValidUrl($value)) {
                            $tplVars['error'] = T_('This bookmark URL may not be added' + $value);
                            $templatename = 'editbookmark.tpl';
                            $valid = 0;
                            break;
                        } 
                }
                if ($valid) {
                        $title = array_map('trim', $TITLE);
                        $description = trim(POST_DESCRIPTION);
                        $privateNote = trim(POST_PRIVATENOTE);
                        $status = intval(POST_STATUS);
                        $categories = array_map('trim', explode(',', trim($_POST['tags'])));
                        $removecategories = array_map('trim', explode(',', trim($_POST['removetags'])));
                        $saved = true;
                        foreach($address as $index => $value) {        
                                if ($bookmarkservice->bookmarkExists($value, $currentUserID)) {
                                    // If the bookmark exists already, edit the original
                                    $bookmark = $bookmarkservice->getBookmarkByAddress($value);
                                    $bId = intval($bookmark['bId']);
                                    $row = $bookmarkservice->getBookmark($bId, true);
                                    $categories = array_unique(array_merge($row['tags'], $categories));
                                    // remove tags
                                    $categories = array_diff($categories, $removecategories);
                                    if (!$bookmarkservice->updateBookmark($bId, $value, $title[$index], $description, $privateNote, $status, $categories)) {
                                        $tplvars['error'] = T_('Error while saving this bookmark : ' + $value);
                                        $templatename = 'editbookmark.tpl';
                                        $saved = false;
                                        break;
                                    } 
                                }
                                // If it's new, save it
                                elseif (!$bookmarkservice->addBookmark($value, $title[$index], $description, $privateNote, $status, $categories)) {
                                        $tplVars['error'] = T_('There was an error saving this bookmark : ' + $value + ' Please try again or contact the administrator.');
                                        $templatename = 'editbookmark.tpl';
                                        $saved = false;
                                        break;
                                } 
                        }
                        if ($saved) {
                                if (POST_POPUP != '') {
                                        $tplVars['msg'] = '<script type="text/javascript">window.close();</script>';
                                } 
                                else {
                                        $tplVars['msg'] = T_('Bookmark saved') . ' <a href="javascript:history.go(-2)">'.T_('(Come back to previous page.)').'</a>';
                                }

                        }
                }
	}
}

if (GET_ACTION == "add" || GET_BATCH) {
	// If the bookmark exists already, edit the original
	if (count($ADDRESS) === 1) {
		if ($bookmarkservice->bookmarkExists(stripslashes($ADDRESS[0]), $currentUserID)) {		
			$bookmark =& $bookmarkservice->getBookmarks(0, NULL, $currentUserID, NULL, NULL, NULL, NULL, NULL, NULL, $bookmarkservice->getHash(stripslashes($ADDRESS[0])));
			$popup = (GET_POPUP!='') ? '?popup=1' : '';
			header('Location: '. createURL('edit', $bookmark['bookmarks'][0]['bId'] . $popup));
			exit();
		}
	}
	$templatename = 'editbookmark.tpl';
}

if ($templatename == 'editbookmark.tpl') { // Prepare to display the edit bookmark page.
	if ($userservice->isLoggedOn()) {
		$tplVars['formaction']  = createURL('bookmarks', $currentUsername);
		if (POST_SUBMITTED != '') {
			$tplVars['row'] = array(
                                'bTitle' => array_map('stripslashes', $TITLE),
                                'bAddress' => array_map('stripslashes', $ADDRESS),
                                'bDescription' => stripslashes(POST_DESCRIPTION),
                                'bPrivateNote' => stripslashes(POST_PRIVATENOTE),
                                'tags' => ($_POST['tags'] ? $_POST['tags'] : array()),
				'bStatus' => $GLOBALS['defaults']['privacy'],
			);
			$tplVars['tags'] = $_POST['tags'];
                } 
                elseif (GET_BATCH) {
                        $completebookmarks = $bookmarkservice->getBookmarks(0, null, $userid, $cat, null, getSortOrder());
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
                        $tplVars['batch'] = '1';
                        $tplVars['alltags'] = implode(', ', $alltags2);
                        $tplVars['commontags'] = implode(', ', $commontags2);
                }
                else {
			if(GET_COPYOF != '') {  //copy from bookmarks page
				$tplVars['row'] = $bookmarkservice->getBookmark(intval(GET_COPYOF), true);
				if(!$currentUser->isAdmin()) {
					$tplVars['row']['bPrivateNote'] = ''; //only admin can copy private note
				}
			}else {  //copy from pop-up bookmarklet
			 $tplVars['row'] = array(
			 	'bTitle' => array_map('stripslashes', $TITLE),
			 	'bAddress' => array_map('stripslashes', $ADDRESS),
                		'bDescription' => stripslashes(GET_DESCRIPTION),
                		'bPrivateNote' => stripslashes(GET_PRIVATENOTE),
                		'tags' => (GET_TAGS ? explode(',', stripslashes(GET_TAGS)) : array()),
                		'bStatus' => $GLOBALS['defaults']['privacy'] 
			 );
			}
				
		}
		$title = T_('Add a Bookmark');
		$tplVars['referrer'] = '';;
		if (isset($_SERVER['HTTP_REFERER'])) {
			$tplVars['referrer'] = $_SERVER['HTTP_REFERER'];
		}
		$tplVars['pagetitle'] = $title;
		$tplVars['subtitle'] = $title;
		$tplVars['btnsubmit'] = T_('Add Bookmark');
		$tplVars['popup'] = (GET_POPUP!='') ? GET_POPUP : null;
	} else {
		$tplVars['error'] = T_('You must be logged in before you can add bookmarks.');
	}
} else if ($user && GET_POPUP == '') {

	$tplVars['sidebar_blocks'] = array('watchstatus');

	if (!$cat) { //user page without tags
                $rssTitle = "My Bookmarks";
		$cat = NULL;
		$tplVars['currenttag'] = NULL;
		//$tplVars['sidebar_blocks'][] = 'menu2';
		$tplVars['sidebar_blocks'][] = 'linked';
		$tplVars['sidebar_blocks'][] = 'popular';
	} else { //pages with tags
                $rssTitle = "Tags" . $catTitle;
		$rssCat = '/'. filter($cat, 'url');
		$tplVars['currenttag'] = $cat;
		//$tplVars['sidebar_blocks'][] = 'menu2';

                if (! empty($GLOBALS['shoulderSurfingProtectedTag']) && ! isset($_COOKIE["noshoulderSurfingProtection"])) {
                        $tag2tagservice = SemanticScuttle_Service_Factory::get('Tag2Tag');
                        $b2tservice     = SemanticScuttle_Service_Factory::get('Bookmark2Tag');
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
                        }
                }
                else {
		                $tplVars['sidebar_blocks'][] = 'tagactions';
		                $tplVars['sidebar_blocks'][] = 'linked';
		                $tplVars['sidebar_blocks'][] = 'related';
                }

		/*$tplVars['sidebar_blocks'][] = 'menu';*/
	}
	$tplVars['sidebar_blocks'][] = 'menu2';
	$tplVars['popCount'] = 30;
	//$tplVars['sidebar_blocks'][] = 'popular';

	$tplVars['userid'] = $userid;
	$tplVars['userinfo'] = $userinfo;
	$tplVars['user'] = $user;
	$tplVars['range'] = 'user';

	// Pagination
	$perpage = getPerPageCount($currentUser);
	if (intval(GET_PAGE) > 1) {
		$page = intval(GET_PAGE);
		$start = ($page - 1) * $perpage;
	} else {
		$page = 0;
		$start = 0;
	}

	// Set template vars
	$tplVars['rsschannels'] = array(
        array(
            sprintf(T_('%s: %s'), $sitename, $rssTitle),
            createURL('rss', filter($user, 'url'))
            . $rssCat . '?sort='.getSortOrder()
        )
	);

        if ($userservice->isLoggedOn()) {
            $currentUsername = $currentUser->getUsername();
            if ($userservice->isPrivateKeyValid($currentUser->getPrivateKey())) {
                array_push(
                    $tplVars['rsschannels'],
                    array(
                        sprintf(
                            T_('%s: %s (+private %s)'),
                            $sitename, $rssTitle, $currentUsername
                        ),
                        createURL('rss', filter($currentUsername, 'url'))
                        . $rssCat
                        . '?sort=' . getSortOrder()
                        . '&privateKey=' . $currentUser->getPrivateKey()
                    )
                );
            }
        }

	$tplVars['page'] = $page;
	$tplVars['start'] = $start;
	$tplVars['bookmarkCount'] = $start + 1;

	$bookmarks = $bookmarkservice->getBookmarks($start, $perpage, $userid, $cat, null, getSortOrder());
	$tplVars['total'] = $bookmarks['total'];
	$tplVars['bookmarks'] = $bookmarks['bookmarks'];
	$tplVars['cat_url'] = createURL('bookmarks', '%s/%s');
	$tplVars['nav_url'] = createURL('bookmarks', '%s/%s%s');
	if ($userservice->isLoggedOn() && $user == $currentUsername) {
		$tplVars['pagetitle'] = T_('My Bookmarks') . $catTitle;
		$tplVars['subtitlehtml'] =  T_('My Bookmarks') . $catTitleWithUrls;
	} else {
		$tplVars['pagetitle']    = $user.': '.$cat;
		$tplVars['subtitlehtml'] =  $user . $catTitleWithUrls;
	}
}

$tplVars['summarizeLinkedTags'] = true;
$tplVars['pageName'] = PAGE_BOOKMARKS;


$templateservice->loadTemplate($templatename, $tplVars);

if ($usecache && $endcache) {
	// Cache output if existing copy has expired
	$cacheservice->End($hash);
}
?>
