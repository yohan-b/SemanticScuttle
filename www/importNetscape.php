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


/**
 * Basically netscape bookmark files often come so badly formed, there's
 * no reliable way I could find to parse them with DOM or SimpleXML,
 * even after running HTML Tidy on them. So, this function does a bunch of
 * transformations on the general format of a netscape bookmark file, to get
 * Each bookmark and its description onto one line, and goes through line by
 * line, matching tags and attributes. It's messy, but it works better than
 * anything I could find in hours of googling, and anything that I could
 * write after hours with DOM and SimpleXML. I didn't want to pull in a big
 * DOM parsing library just to do this one thing, so this is it.
 * @todo - running Tidy before doing this might be beneficial.
 *   ?? $bkmk_str = tidy_parse_string($bkmk_str)->cleanRepair();
 *
 * Update 2013-07-08:
 *     Just tested this on an export of some bookmarks from Pinboard.in
 *     and it seems that it is still working, so good for me.
 */

/*
print '<PRE>';
var_dump(parse_netscape_bookmarks(file_get_contents('bookmarks_export.htm')));
*/

function parse_netscape_bookmarks($bkmk_str, $default_tag = null) {
    $i = 0;
    $next = false;
    $items = [];

    $current_tag = $default_tag = $default_tag ?: 'autoimported-'.date("Ymd");

    $bkmk_str = str_replace(["\r","\n","\t"], ['','',' '], $bkmk_str);

    $bkmk_str = preg_replace_callback('@<dd>(.*?)(<A|<\/|<DL|<DT|<P)@mis', function($m) {
        return '<dd>'.str_replace(["\r", "\n"], ['', '<br>'], trim($m[1])).'</';
    }, $bkmk_str);

    $bkmk_str = preg_replace('/>(\s*?)</mis', ">\n<", $bkmk_str);
    $bkmk_str = preg_replace('/(<!DOCTYPE|<META|<!--|<TITLE|<H1|<P)(.*?)\n/i', '', $bkmk_str);

    $bkmk_str = trim($bkmk_str);
    $bkmk_str = preg_replace('/\n<dd/i', '<dd', $bkmk_str);
    //best way to do it :
    $bkmk_str = preg_replace('/(?<=.)<\/DL>/', "\n</DL>", $bkmk_str);
    $lines = explode("\n", $bkmk_str);

    $str_bool = function($str, $default = false) {
        if (!$str) {
            return false;
        } elseif (!is_string($str) && $str) {
            return true;
        }

        $true  = 'y|yes|on|checked|ok|1|true|array|\+|okay|yes+|t|one';
        $false = 'n|no|off|empty|null|false|0|-|exit|die|neg|f|zero|void';

        if (preg_match("/^($true)$/i", $str)) {
            return true;
        } elseif (preg_match("/^($false)$/i", $str)) {
            return false;
        }

        return $default;
    };
    $tags = array($default_tag);
    foreach ($lines as $line_no => $line) {
        /* If we match a tag, set current tag to that, if <DL>, stop tag. */
        if (preg_match('/^<h\d(.*?)>(.*?)<\/h\d>/i', $line, $m1)) {
            $current_tag = trim(preg_replace("/\s+/", "_", strtr($m1[2], ', /+', '____')));
            $tags[] = $current_tag; 
            continue;
        } elseif (preg_match('/^<\/DL>/i', $line)) {
                $current_tag = $default_tag;
                array_pop($tags);
        }

        if (preg_match('/<a/i', $line, $m2)) {
            $items[$i]['tags'] = $tags;

            if (preg_match('/href="(.*?)"/i', $line, $m3)) {
                $items[$i]['uri'] = $m3[1];
                // $items[$i]['meta'] = meta($m3[1]);
            } else {
                $items[$i]['uri'] = '';
                // $items[$i]['meta'] = '';
            }

            if (preg_match('/<a(.*?)>(.*?)<\/a>/i', $line, $m4)) {
                $items[$i]['title'] = $m4[2];
                // $items[$i]['slug'] = slugify($m4[2]);
            } else {
                $items[$i]['title'] = 'untitled';
                // $items[$i]['slug'] = '';
            }

            if (preg_match('/note="(.*?)"<\/a>/i', $line, $m5)) {
                 $items[$i]['note'] = $m5[1];
            } elseif (preg_match('/<dd>(.*?)<\//i', $line, $m6)) {
                 $items[$i]['note'] = str_replace('<br>', "\n", $m6[1]);
            } else {
                $items[$i]['note'] = '';
            }

            if (preg_match('/(tags?|labels?|folders?)="(.*?)"/i', $line, $m7)) {
                array_unique(array_merge($items[$i]['tags'], explode(' ', trim(preg_replace("/\s+/", " ", strtr($m7[2], ',', ' '))))));
            }
            if (preg_match('/add_date="(.*?)"/i', $line, $m8)) {
                 $items[$i]['time'] = $m8[1];
            } else {
                $items[$i]['time'] = time();
            }

            if (preg_match('/(public|published|pub)="(.*?)"/i', $line, $m9)) {
                $items[$i]['pub'] = $str_bool($m9[2], false) ? 1 : 0;
            } elseif (preg_match('/(private|shared)="(.*?)"/i', $line, $m10)) {
                $items[$i]['pub'] = $str_bool($m10[2], true) ? 0 : 1;
            }

            $i++;
        }
    }
    ksort($items);

    return $items;
}

?>
