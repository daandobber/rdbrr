<?php

/*
 *	Social wall module for profile messages.
 */

@require_once('config.inc.php');
require_once('common.inc.php');
require_once('html.inc.php');
require_once('html_parse.inc.php');
require_once('modules.inc.php');
require_once('social.inc.php');
require_once('util.inc.php');


function social_wall_file()
{
	return CONTENT_DIR.'/social_messages.json';
}


function social_wall_read()
{
	$ret = array('profiles'=>array());
	$f = social_wall_file();
	if (!is_file($f)) {
		return $ret;
	}
	$s = @file_get_contents($f);
	if ($s === false || trim($s) == '') {
		return $ret;
	}
	$data = @json_decode($s, true);
	if (!is_array($data) || !isset($data['profiles']) || !is_array($data['profiles'])) {
		return $ret;
	}
	return $data;
}


function social_wall_write($data)
{
	if (!is_dir(CONTENT_DIR)) {
		return false;
	}
	$options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
	$s = json_encode($data, $options);
	if ($s === false) {
		return false;
	}
	$m = umask(0111);
	$res = @file_put_contents(social_wall_file(), $s."\n", LOCK_EX);
	umask($m);
	return $res !== false;
}


function social_wall_page_key($page)
{
	$a = expl('.', $page);
	if (count($a) < 2) {
		return false;
	}
	$page = $a[0].'.'.$a[1];
	if (social_username_from_profile_page($page) === false) {
		return false;
	}
	return $page;
}


function social_wall_can_delete($message, $page)
{
	$current = social_current_username();
	if (!$current) {
		return false;
	}
	if (social_is_admin()) {
		return true;
	}
	if (isset($message['author']) && $message['author'] == $current) {
		return true;
	}
	return social_can_edit_page($page);
}


function social_wall_messages($page)
{
	$key = social_wall_page_key($page);
	if ($key === false) {
		return array();
	}
	$data = social_wall_read();
	if (!isset($data['profiles'][$key]) || !is_array($data['profiles'][$key])) {
		return array();
	}
	return $data['profiles'][$key];
}


function social_wall_feed_messages_by_authors($authors, $limit = 80)
{
	$data = social_wall_read();
	$allowed = array();
	foreach ($authors as $author) {
		$author = social_normalize_username($author);
		if (social_valid_username($author)) {
			$allowed[$author] = true;
		}
	}
	$ret = array();
	foreach ($data['profiles'] as $page=>$messages) {
		$profile_owner = social_username_from_profile_page($page);
		if ($profile_owner === false || !is_array($messages)) {
			continue;
		}
		foreach ($messages as $message) {
			if (!isset($message['author']) || !isset($allowed[$message['author']])) {
				continue;
			}
			$message['feed_type'] = 'wall';
			$message['profile_owner'] = $profile_owner;
			$message['profile_page'] = $page;
			$ret[] = $message;
		}
	}
	usort($ret, 'social_microblog_sort_desc');
	return array_slice($ret, 0, $limit);
}


function social_wall_render_messages($page)
{
	$messages = social_wall_messages($page);
	$current = social_current_username();
	$ret = '<div class="social-wall-messages">'.nl();
	if (!count($messages)) {
		$ret .= tab().'<div class="social-wall-empty">Nog geen berichten.</div>'.nl();
	}
	foreach ($messages as $message) {
		$id = isset($message['id']) ? $message['id'] : '';
		$author = isset($message['author']) ? $message['author'] : '';
		$body = isset($message['body']) ? $message['body'] : '';
		$created = isset($message['created_at']) ? $message['created_at'] : '';
		$ret .= tab().'<article class="social-wall-message" data-message-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'">'.nl();
		$ret .= tab(2).'<header>'.nl();
		$ret .= tab(3).'<a class="social-wall-author" href="'.htmlspecialchars(social_profile_url($author), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars($author, ENT_NOQUOTES, 'UTF-8').'</a>'.nl();
		$ret .= tab(3).'<time>'.htmlspecialchars(substr($created, 0, 16), ENT_NOQUOTES, 'UTF-8').'</time>'.nl();
		if (social_wall_can_delete($message, $page)) {
			$ret .= tab(3).'<button type="button" class="social-wall-delete" data-message-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'" title="Verwijder bericht">x</button>'.nl();
		}
		$ret .= tab(2).'</header>'.nl();
		$ret .= tab(2).'<div class="social-wall-body">'.nl2br(htmlspecialchars($body, ENT_NOQUOTES, 'UTF-8')).'</div>'.nl();
		$ret .= tab().'</article>'.nl();
	}
	$ret .= '</div>'.nl();
	return $ret;
}


function social_wall_render_form($page)
{
	if (!social_current_username()) {
		return '<div class="social-wall-login"><a href="'.htmlspecialchars(social_login_url('u/'.social_username_from_profile_page($page)), ENT_COMPAT, 'UTF-8').'">Log in om een bericht te plaatsen</a></div>'.nl();
	}
	return '<form class="social-wall-form">'.nl().
		tab().'<textarea name="body" maxlength="1000" placeholder="Schrijf een bericht..."></textarea>'.nl().
		tab().'<button type="submit">Plaats</button>'.nl().
	'</form>'.nl();
}


function social_wall_render_object($args)
{
	$obj = $args['obj'];
	if (!isset($obj['type']) || $obj['type'] != 'social_wall') {
		return false;
	}
	$page = implode('.', array_slice(expl('.', $obj['name']), 0, 2));
	$title = isset($obj['wall-title']) && $obj['wall-title'] != '' ? $obj['wall-title'] : 'Berichten';
	$e = elem('div');
	elem_attr($e, 'id', $obj['name']);
	elem_attr($e, 'data-wall-page', $page);
	elem_add_class($e, 'social_wall');
	elem_add_class($e, 'resizable');
	elem_add_class($e, 'object');
	if (!empty($obj['wall-background-color'])) {
		elem_css($e, 'background-color', $obj['wall-background-color']);
	}
	elem_css($e, 'color', !empty($obj['wall-font-color']) ? $obj['wall-font-color'] : '#202020');
	elem_css($e, 'font-family', !empty($obj['wall-font-family']) ? $obj['wall-font-family'] : 'DejaVuSans');
	elem_css($e, 'font-size', !empty($obj['wall-font-size']) ? $obj['wall-font-size'] : '13px');
	if (!empty($obj['wall-border-color'])) {
		elem_css($e, 'border-color', $obj['wall-border-color']);
	}
	if (!empty($obj['wall-border-radius'])) {
		elem_css($e, 'border-radius', $obj['wall-border-radius']);
	}
	invoke_hook_first('alter_render_early', 'social_wall', array('obj'=>$obj, 'elem'=>&$e, 'edit'=>$args['edit']));
	$html = elem_finalize($e);
	$inner = '<div class="social-wall-inner">'.nl();
	if ($args['edit']) {
		$inner .= tab().'<div class="social-wall-tools glue-ui"><button type="button" class="social-wall-style">Stijl</button></div>'.nl();
	}
	$inner .= tab().'<h2 class="social-wall-title">'.htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8').'</h2>'.nl();
	$inner .= social_wall_render_messages($page);
	$inner .= social_wall_render_form($page);
	$inner .= '</div>'.nl();
	$html = preg_replace('/<\/div>\s*$/', $inner.'</div>', $html, 1);
	invoke_hook_last('alter_render_late', 'social_wall', array('obj'=>$obj, 'html'=>&$html, 'elem'=>$e, 'edit'=>$args['edit']));
	return $html;
}


function social_wall_save_state($args)
{
	$elem = $args['elem'];
	$obj = $args['obj'];
	if (!elem_has_class($elem, 'social_wall')) {
		return false;
	}
	$obj['type'] = 'social_wall';
	$obj['module'] = 'social_wall';
	$styles = array(
		'background-color'=>'wall-background-color',
		'color'=>'wall-font-color',
		'font-family'=>'wall-font-family',
		'font-size'=>'wall-font-size',
		'border-color'=>'wall-border-color',
		'border-radius'=>'wall-border-radius'
	);
	foreach ($styles as $css=>$attr) {
		if (elem_css($elem, $css) !== NULL) {
			$obj[$attr] = elem_css($elem, $css);
		} else {
			unset($obj[$attr]);
		}
	}
	invoke_hook('alter_save', array('obj'=>&$obj, 'elem'=>$elem));
	load_modules('glue');
	$ret = save_object($obj);
	return $ret['#error'] ? false : true;
}


function social_wall_post($args)
{
	$page = isset($args['page']) ? $args['page'] : '';
	$key = social_wall_page_key($page);
	if ($key === false) {
		return response('Ongeldige profielpagina.', 400);
	}
	$author = social_current_username();
	if (!$author) {
		return response('Log in om een bericht te plaatsen.', 403);
	}
	$body = isset($args['body']) ? trim($args['body']) : '';
	if ($body == '') {
		return response('Bericht is leeg.', 400);
	}
	if (1000 < strlen($body)) {
		return response('Bericht is te lang.', 400);
	}
	$data = social_wall_read();
	if (!isset($data['profiles'][$key]) || !is_array($data['profiles'][$key])) {
		$data['profiles'][$key] = array();
	}
	$data['profiles'][$key][] = array(
		'id'=>md5(uniqid('', true)),
		'author'=>$author,
		'body'=>$body,
		'created_at'=>gmdate('c')
	);
	if (200 < count($data['profiles'][$key])) {
		$data['profiles'][$key] = array_slice($data['profiles'][$key], -200);
	}
	if (!social_wall_write($data)) {
		return response('Kon bericht niet opslaan.', 500);
	}
	drop_cache('page', $key);
	return response(array('html'=>social_wall_render_messages($key)));
}


function social_wall_delete($args)
{
	$page = isset($args['page']) ? $args['page'] : '';
	$id = isset($args['id']) ? $args['id'] : '';
	$key = social_wall_page_key($page);
	if ($key === false || $id == '') {
		return response('Ongeldige aanvraag.', 400);
	}
	$data = social_wall_read();
	if (!isset($data['profiles'][$key]) || !is_array($data['profiles'][$key])) {
		return response(array('html'=>social_wall_render_messages($key)));
	}
	foreach ($data['profiles'][$key] as $i=>$message) {
		if (isset($message['id']) && $message['id'] == $id) {
			if (!social_wall_can_delete($message, $key)) {
				return response('Je mag dit bericht niet verwijderen.', 403);
			}
			array_splice($data['profiles'][$key], $i, 1);
			social_wall_write($data);
			drop_cache('page', $key);
			return response(array('html'=>social_wall_render_messages($key)));
		}
	}
	return response(array('html'=>social_wall_render_messages($key)));
}


function social_wall_create($args)
{
	$page = isset($args['page']) ? $args['page'] : '';
	if (!social_can_edit_page($page)) {
		return response('Je kunt alleen je eigen profiel aanpassen.', 403);
	}
	load_modules('glue');
	$new = create_object(array('page'=>$page));
	if ($new['#error']) {
		return $new;
	}
	$name = $new['#data']['name'];
	$ret = update_object(array(
		'name'=>$name,
		'type'=>'social_wall',
		'module'=>'social_wall',
		'wall-title'=>'Berichten',
		'wall-background-color'=>'#ffffff',
		'wall-font-color'=>'#202020',
		'wall-font-family'=>'DejaVuSans',
		'wall-font-size'=>'13px',
		'wall-border-color'=>'#202020',
		'wall-border-radius'=>'0px',
		'object-left'=>'120px',
		'object-top'=>'260px',
		'object-width'=>'420px',
		'object-height'=>'360px'
	));
	if ($ret['#error']) {
		return $ret;
	}
	$rendered = render_object(array('name'=>$name, 'edit'=>true));
	return $rendered['#error'] ? $rendered : response(array('name'=>$name, 'html'=>$rendered['#data']));
}


function social_wall_render_page_early($args)
{
	if (function_exists('_woff_fonts')) {
		foreach (_woff_fonts() as $font=>$styles) {
			_include_woff_font($font);
		}
	}
	if (!$args['edit']) {
		$jquery = JQUERY;
		if (is_url($jquery)) {
			html_add_js($jquery, 1);
		} else {
			html_add_js(base_url().$jquery, 1);
		}
		if (USE_MIN_FILES) {
			html_add_js(base_url().'js/glue.min.js', 3);
		} else {
			html_add_js(base_url().'js/glue.js', 3);
		}
		html_add_js_var('$.glue.base_url', base_url());
		html_add_js_var('$.glue.conf.show_frontend_errors', SHOW_FRONTEND_ERRORS);
	}
	html_add_css(base_url().'css/social_wall.css', 6);
	html_add_js(base_url().'js/social_wall.js', 6);
}


register_service('social_wall.post', 'social_wall_post', array('auth'=>true));
register_service('social_wall.delete', 'social_wall_delete', array('auth'=>true));
register_service('social_wall.create', 'social_wall_create', array('auth'=>true));
