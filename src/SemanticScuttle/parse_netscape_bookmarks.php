<?php
/***************************************************************************
 The MIT License (MIT)
 
 Copyright (c) 2015 kafene
 https://github.com/kafene/netscape-bookmark-parser
 http://kafene.org

 Slightly modified by yohan.

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 SOFTWARE.
 ***************************************************************************/

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
