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
if ('@data_dir@' == '@' . 'data_dir@') {
    //non pear-install
    require_once dirname(__FILE__) . '/../src/SemanticScuttle/parse_netscape_bookmarks.php';
} else {
    //pear installation; files are in include path
    require_once 'SemanticScuttle/parse_netscape_bookmarks.php';
}

/* Service creation: only useful services are created */
$bookmarkservice =SemanticScuttle_Service_Factory::get('Bookmark');


/* Managing all possible inputs */
// First input is $_FILES
// Other inputs
isset($_POST['status']) ? define('POST_STATUS', $_POST['status']): define('POST_STATUS', $GLOBALS['defaults']['privacy']);

$countImportedBookmarks = 0;
$tplVars['msg'] = '';

if ($userservice->isLoggedOn() && sizeof($_FILES) > 0 && $_FILES['userfile']['size'] > 0) {
	$userinfo = $userservice->getCurrentObjectUser();

	if (is_numeric(POST_STATUS)) {
		$status = intval(POST_STATUS);
	} else {
		$status = $GLOBALS['defaults']['privacy'];
	}

	// File handle
	$html = file_get_contents($_FILES['userfile']['tmp_name']);
	// Create array
        $matches = parse_netscape_bookmarks($html);
        //var_dump($matches);

	$size = count($matches);
	for ($i = 0; $i < $size; $i++) {
		$bDatetime = gmdate('Y-m-d H:i:s', $matches[$i]['time']); //bDateTime optional
                $bCategories = $matches[$i]['tags']; //bCategories optional
                $bAddress = $matches[$i]['uri'];
                $bDescription = $matches[$i]['note'];
                $bTitle = $matches[$i]['title'];
                $bPrivateNote = '';

		if ($bookmarkservice->bookmarkExists($bAddress, $userservice->getCurrentUserId())) {
			//$tplVars['error'] = T_('You have already submitted some of these bookmarks.');
                        // If the bookmark exists already, edit the original
                        $bookmark = $bookmarkservice->getBookmarkByAddress($bAddress);
                        $bId = intval($bookmark['bId']);
                        $row = $bookmarkservice->getBookmark($bId, true);
                        $categories = array_unique(array_merge($row['tags'], $bCategories));
	                //var_dump('update', $bId, $bAddress, $row['bTitle'], $row['bDescription'], $row['bPrivateNote'], $row['bStatus'], $categories);
                        if ($bookmarkservice->updateBookmark($bId, $bAddress, $row['bTitle'], $row['bDescription'], $row['bPrivateNote'], $row['bStatus'], $categories)) {
                                $countImportedBookmarks++;
                        }
                        else {
                                $tplvars['error'] = T_('Error while saving this bookmark : ' + $bAddress);
                        }
		} else {
			// If bookmark is local (like javascript: or place: in Firefox3), do nothing
			if(substr($bAddress, 0, 7) == "http://" || substr($bAddress, 0, 8) == "https://") {
				// If bookmark claims to be from the future, set it to be now instead
				if (strtotime($bDatetime) > time()) {
					$bDatetime = gmdate('Y-m-d H:i:s');
				}
	                        //var_dump('add ', $bAddress, $bTitle, $bDescription, $bPrivateNote, $status, $bCategories, $bDatetime);
				if ($bookmarkservice->addBookmark($bAddress, $bTitle, $bDescription, $bPrivateNote, $status, $bCategories, null, $bDatetime, false, true)) {
					$countImportedBookmarks++;
				} else {
					$tplVars['error'] = T_('There was an error saving your bookmark : ' . $bAddress . ' Please try again or contact the administrator.');
				}
			}
		}
	}
	//header('Location: '. createURL('bookmarks', $userinfo->getUsername()));
	$templatename = 'importNetscape.tpl';
	$tplVars['msg'].= T_('Bookmarks found: ').$size.' ';
	$tplVars['msg'].= T_('Bookmarks imported: ').' '.$countImportedBookmarks;
	$tplVars['subtitle'] = T_('Import Bookmarks from Browser File');
	$tplVars['formaction'] = createURL('importNetscape');
	$templateservice->loadTemplate($templatename, $tplVars);
} else {
	$templatename = 'importNetscape.tpl';
	$tplVars['subtitle'] = T_('Import Bookmarks from Browser File');
	$tplVars['formaction'] = createURL('importNetscape');
	$templateservice->loadTemplate($templatename, $tplVars);
}

?>
