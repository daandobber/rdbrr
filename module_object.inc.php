<?php

/*
 *	module_object.inc.php
 *	Module for handling general object properties
 *
 *	Copyright Gottfried Haider, Danja Vasiliev 2010.
 *	This source code is licensed under the GNU General Public License.
 *	See the file COPYING for more details.
 */

@require_once('config.inc.php');
require_once('common.inc.php');
require_once('html.inc.php');
require_once('util.inc.php');


// module_image.inc.php has more information on what's going on inside modules 
// (they can be easier than that one though)

function object_shape_clip_path($shape)
{
	if ($shape == 'diamond') {
		return 'polygon(50% 0, 100% 50%, 50% 100%, 0 50%)';
	} elseif ($shape == 'hexagon') {
		return 'polygon(25% 0, 75% 0, 100% 50%, 75% 100%, 25% 100%, 0 50%)';
	} elseif ($shape == 'octagon') {
		return 'polygon(30% 0, 70% 0, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0 70%, 0 30%)';
	}
	return '';
}


function object_alter_render_early($args)
{
	$elem = &$args['elem'];
	$obj = $args['obj'];
	if (!elem_has_class($elem, 'object')) {
		return false;
	}
	
	if (!empty($obj['object-height'])) {
		elem_css($elem, 'height', $obj['object-height']);
	}
	if (!empty($obj['object-left'])) {
		elem_css($elem, 'left', $obj['object-left']);
	}
	if (!empty($obj['object-opacity'])) {
		elem_css($elem, 'opacity', $obj['object-opacity']);
	}
	elem_css($elem, 'position', 'absolute');
	if (!empty($obj['object-top'])) {
		elem_css($elem, 'top', $obj['object-top']);
	}
	if (!empty($obj['object-width'])) {
		elem_css($elem, 'width', $obj['object-width']);
	}
	if (!empty($obj['object-zindex'])) {
		elem_css($elem, 'z-index', $obj['object-zindex']);
	}
	if (!empty($obj['object-box-sizing'])) {
		elem_css($elem, 'box-sizing', $obj['object-box-sizing']);
	}
	if (!empty($obj['object-border-color'])) {
		elem_css($elem, 'border-color', $obj['object-border-color']);
	}
	if (!empty($obj['object-border-style'])) {
		elem_css($elem, 'border-style', $obj['object-border-style']);
	}
	if (!empty($obj['object-border-width'])) {
		elem_css($elem, 'border-width', $obj['object-border-width']);
	}
	if (!empty($obj['object-border-radius'])) {
		elem_css($elem, 'border-radius', $obj['object-border-radius']);
	}
	if (!empty($obj['object-shape'])) {
		elem_attr($elem, 'data-object-shape', $obj['object-shape']);
		if ($obj['object-shape'] == 'pill') {
			elem_css($elem, 'border-radius', '9999px');
		} elseif ($obj['object-shape'] == 'ellipse') {
			elem_css($elem, 'border-radius', '50%');
		} else {
			$clip_path = object_shape_clip_path($obj['object-shape']);
			if ($clip_path != '') {
				elem_css($elem, 'clip-path', $clip_path);
				elem_css($elem, '-webkit-clip-path', $clip_path);
			}
		}
	}
	if (!empty($obj['object-shape']) || !empty($obj['object-border-radius'])) {
		elem_css($elem, 'overflow', 'hidden');
	}
	
	return true;
}


function object_alter_render_late($args)
{
	$elem = $args['elem'];
	$html = &$args['html'];
	$obj = $args['obj'];
	if (!elem_has_class($args['elem'], 'object')) {
		return false;
	}
	if (!$args['edit']) {
		// add links only for viewing
		if (!empty($obj['object-link'])) {
			$link = $obj['object-link'];
			if(!empty($obj['object-target'])) {
				$target = $obj['object-target'];
			}
			// resolve any aliases
			$link = resolve_aliases($link, $obj['name']);
			if (!is_url($link) && substr($link, 0, 1) != '#') {
				// add base url for relative links that are not directed towards anchors
				if (SHORT_URLS) {
					$link = base_url().urlencode($link);
				} else {
					$link = base_url().'?'.urlencode($link);
				}
			}
			// <a> can include block elements in html5
			if (substr($html, -1) == "\n") {
				$html = substr($html, 0, -1);
			}
			// if target is specified use it in link
			if (isset($target)) {
				$html = '<a href="'.htmlspecialchars($link, ENT_COMPAT, 'UTF-8').'" target="'.htmlspecialchars($target, ENT_COMPAT, 'UTF-8').'">'."\n\t".str_replace("\n", "\n\t", $html)."\n".'</a>'."\n";
			} else {
				$html = '<a href="'.htmlspecialchars($link, ENT_COMPAT, 'UTF-8').'">'."\n\t".str_replace("\n", "\n\t", $html)."\n".'</a>'."\n";
			}
			return true;
		}
	}
	return false;
}


function object_alter_save($args)
{
	$elem = $args['elem'];
	$obj = &$args['obj'];
	if (!elem_has_class($elem, 'object')) {
		return false;
	}
	
	if (elem_css($elem, 'height') !== NULL) {
		$obj['object-height'] = elem_css($elem, 'height');
	} else {
		unset($obj['object-height']);
	}
	if (elem_css($elem, 'left') !== NULL) {
		$obj['object-left'] = elem_css($elem, 'left');
	} else {
		unset($obj['object-left']);
	}
	if (elem_css($elem, 'opacity') !== NULL) {
		$obj['object-opacity'] = elem_css($elem, 'opacity');
	} else {
		unset($obj['object-opacity']);
	}
	if (elem_css($elem, 'top') !== NULL) {
		$obj['object-top'] = elem_css($elem, 'top');
	} else {
		unset($obj['object-top']);
	}
	if (elem_css($elem, 'width') !== NULL) {
		$obj['object-width'] = elem_css($elem, 'width');
	} else {
		unset($obj['object-width']);
	}
	if (elem_css($elem, 'z-index') !== NULL) {
		$obj['object-zindex'] = elem_css($elem, 'z-index');
	} else {
		unset($obj['object-zindex']);
	}
	if (elem_css($elem, 'box-sizing') !== NULL) {
		$obj['object-box-sizing'] = elem_css($elem, 'box-sizing');
	} else {
		unset($obj['object-box-sizing']);
	}
	if (elem_css($elem, 'border-color') !== NULL) {
		$obj['object-border-color'] = elem_css($elem, 'border-color');
	} else {
		unset($obj['object-border-color']);
	}
	if (elem_css($elem, 'border-style') !== NULL) {
		$obj['object-border-style'] = elem_css($elem, 'border-style');
	} else {
		unset($obj['object-border-style']);
	}
	if (elem_css($elem, 'border-width') !== NULL) {
		$obj['object-border-width'] = elem_css($elem, 'border-width');
	} else {
		unset($obj['object-border-width']);
	}
	if (elem_css($elem, 'border-radius') !== NULL) {
		$obj['object-border-radius'] = elem_css($elem, 'border-radius');
	} else {
		unset($obj['object-border-radius']);
	}
	$shape = elem_attr($elem, 'data-object-shape');
	if (in_array($shape, array('pill', 'ellipse', 'diamond', 'hexagon', 'octagon'))) {
		$obj['object-shape'] = $shape;
	} else {
		unset($obj['object-shape']);
	}
	
	return true;
}


function object_render_page_early($args)
{
	if ($args['edit']) {
		if (USE_MIN_FILES) {
			html_add_js(base_url().'modules/object/object-edit.min.js');
		} else {
			html_add_js(base_url().'modules/object/object-edit.js');
		}
		
		// add default colors
		html_add_js_var('$.glue.conf.object.default_colors', expl(' ', OBJECT_DEFAULT_COLORS));
	}
}
