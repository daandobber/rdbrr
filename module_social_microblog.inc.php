<?php

/*
 *	Social microblog module for profile updates and the global feed.
 */

@require_once('config.inc.php');
require_once('common.inc.php');
require_once('html.inc.php');
require_once('html_parse.inc.php');
require_once('modules.inc.php');
require_once('social.inc.php');
require_once('util.inc.php');


function social_microblog_file()
{
	return CONTENT_DIR.'/social_updates.json';
}


function social_microblog_read()
{
	$ret = array('updates'=>array());
	$f = social_microblog_file();
	if (!is_file($f)) {
		return $ret;
	}
	$s = @file_get_contents($f);
	if ($s === false || trim($s) == '') {
		return $ret;
	}
	$data = @json_decode($s, true);
	if (!is_array($data) || !isset($data['updates']) || !is_array($data['updates'])) {
		return $ret;
	}
	return $data;
}


function social_microblog_write($data)
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
	$res = @file_put_contents(social_microblog_file(), $s."\n", LOCK_EX);
	umask($m);
	return $res !== false;
}


function social_microblog_page_key($page)
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


function social_microblog_page_owner($page)
{
	$key = social_microblog_page_key($page);
	return $key === false ? false : social_username_from_profile_page($key);
}


function social_microblog_can_post($page)
{
	$owner = social_microblog_page_owner($page);
	$current = social_current_username();
	if (!$owner || !$current) {
		return false;
	}
	return social_is_admin() || $owner == $current;
}


function social_microblog_can_delete($update)
{
	$current = social_current_username();
	if (!$current) {
		return false;
	}
	if (social_is_admin()) {
		return true;
	}
	return isset($update['author']) && $update['author'] == $current;
}


function social_microblog_updates($username = '', $limit = 30)
{
	if (is_array($username)) {
		return social_microblog_updates_by_authors($username, $limit);
	}
	$data = social_microblog_read();
	$ret = array();
	foreach ($data['updates'] as $update) {
		if ($username != '' && (!isset($update['author']) || $update['author'] != $username)) {
			continue;
		}
		$ret[] = $update;
	}
	usort($ret, 'social_microblog_sort_desc');
	return array_slice($ret, 0, $limit);
}


function social_microblog_updates_by_authors($authors, $limit = 80)
{
	$data = social_microblog_read();
	$allowed = array();
	foreach ($authors as $author) {
		$author = social_normalize_username($author);
		if (social_valid_username($author)) {
			$allowed[$author] = true;
		}
	}
	$ret = array();
	foreach ($data['updates'] as $update) {
		if (!isset($update['author']) || !isset($allowed[$update['author']])) {
			continue;
		}
		$ret[] = $update;
	}
	usort($ret, 'social_microblog_sort_desc');
	return array_slice($ret, 0, $limit);
}


function social_microblog_sort_desc($a, $b)
{
	$at = isset($a['created_at']) ? $a['created_at'] : '';
	$bt = isset($b['created_at']) ? $b['created_at'] : '';
	if ($at == $bt) {
		return 0;
	}
	return $at < $bt ? 1 : -1;
}


function social_microblog_render_updates($username = '', $page = '')
{
	$updates = social_microblog_updates($username, 40);
	$ret = '<div class="social-microblog-updates">'.nl();
	if (!count($updates)) {
		$ret .= tab().'<div class="social-microblog-empty">Nog geen updates.</div>'.nl();
	}
	foreach ($updates as $update) {
		$id = isset($update['id']) ? $update['id'] : '';
		$author = isset($update['author']) ? $update['author'] : '';
		$body = isset($update['body']) ? $update['body'] : '';
		$created = isset($update['created_at']) ? $update['created_at'] : '';
		$ret .= tab().'<article class="social-microblog-update" data-update-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'">'.nl();
		$ret .= tab(2).social_avatar_html($author, 'small').nl();
		$ret .= tab(2).'<div class="social-microblog-update-content">'.nl();
		$ret .= tab(3).'<header>'.nl();
		$ret .= tab(4).'<a class="social-microblog-author" href="'.htmlspecialchars(social_profile_url($author), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars(social_display_name($author), ENT_NOQUOTES, 'UTF-8').'</a>'.nl();
		$ret .= tab(4).'<time>'.htmlspecialchars(substr($created, 0, 16), ENT_NOQUOTES, 'UTF-8').'</time>'.nl();
		if (social_microblog_can_delete($update)) {
			$ret .= tab(4).'<button type="button" class="social-microblog-delete" data-update-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'" title="Verwijder update">x</button>'.nl();
		}
		$ret .= tab(3).'</header>'.nl();
		$ret .= tab(3).'<div class="social-microblog-body">'.nl2br(htmlspecialchars($body, ENT_NOQUOTES, 'UTF-8')).'</div>'.nl();
		$ret .= tab(2).'</div>'.nl();
		$ret .= tab().'</article>'.nl();
	}
	$ret .= '</div>'.nl();
	return $ret;
}


function social_microblog_render_object($args)
{
	$obj = $args['obj'];
	if (!isset($obj['type']) || $obj['type'] != 'social_microblog') {
		return false;
	}
	$page = implode('.', array_slice(expl('.', $obj['name']), 0, 2));
	$username = social_microblog_page_owner($page);
	$title = isset($obj['microblog-title']) && $obj['microblog-title'] != '' ? $obj['microblog-title'] : 'Updates';
	$e = elem('div');
	elem_attr($e, 'id', $obj['name']);
	elem_attr($e, 'data-microblog-page', $page);
	elem_attr($e, 'data-microblog-user', $username);
	elem_add_class($e, 'social_microblog');
	elem_add_class($e, 'resizable');
	elem_add_class($e, 'object');
	elem_css($e, 'background-color', !empty($obj['microblog-background-color']) ? $obj['microblog-background-color'] : '#ffffff');
	elem_css($e, 'color', !empty($obj['microblog-font-color']) ? $obj['microblog-font-color'] : '#202020');
	elem_css($e, 'font-family', !empty($obj['microblog-font-family']) ? $obj['microblog-font-family'] : 'DejaVuSans');
	elem_css($e, 'font-size', !empty($obj['microblog-font-size']) ? $obj['microblog-font-size'] : '13px');
	elem_css($e, 'border-color', !empty($obj['microblog-border-color']) ? $obj['microblog-border-color'] : '#202020');
	elem_css($e, 'border-radius', !empty($obj['microblog-border-radius']) ? $obj['microblog-border-radius'] : '0px');
	invoke_hook_first('alter_render_early', 'social_microblog', array('obj'=>$obj, 'elem'=>&$e, 'edit'=>$args['edit']));
	$html = elem_finalize($e);
	$inner = '<div class="social-microblog-inner">'.nl();
	if ($args['edit']) {
		$inner .= tab().'<div class="social-microblog-tools glue-ui"><button type="button" class="social-microblog-style">Stijl</button></div>'.nl();
	}
	$inner .= tab().'<h2 class="social-microblog-title">'.htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8').'</h2>'.nl();
	$inner .= social_microblog_render_updates($username, $page);
	$inner .= tab().'<a class="social-microblog-feed-link" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'feed">Alle updates</a>'.nl();
	$inner .= '</div>'.nl();
	$html = preg_replace('/<\/div>\s*$/', $inner.'</div>', $html, 1);
	invoke_hook_last('alter_render_late', 'social_microblog', array('obj'=>$obj, 'html'=>&$html, 'elem'=>$e, 'edit'=>$args['edit']));
	return $html;
}


function social_microblog_save_state($args)
{
	$elem = $args['elem'];
	$obj = $args['obj'];
	if (!elem_has_class($elem, 'social_microblog')) {
		return false;
	}
	$obj['type'] = 'social_microblog';
	$obj['module'] = 'social_microblog';
	$styles = array(
		'background-color'=>'microblog-background-color',
		'color'=>'microblog-font-color',
		'font-family'=>'microblog-font-family',
		'font-size'=>'microblog-font-size',
		'border-color'=>'microblog-border-color',
		'border-radius'=>'microblog-border-radius'
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


function social_microblog_post($args)
{
	$page = isset($args['page']) ? $args['page'] : '';
	$key = social_microblog_page_key($page);
	if ($key === false) {
		return response('Ongeldige profielpagina.', 400);
	}
	if (!social_microblog_can_post($key)) {
		return response('Je kunt alleen updates op je eigen profiel plaatsen.', 403);
	}
	$author = social_current_username();
	$body = isset($args['body']) ? trim($args['body']) : '';
	if ($body == '') {
		return response('Update is leeg.', 400);
	}
	if (280 < strlen($body)) {
		return response('Update is te lang.', 400);
	}
	$data = social_microblog_read();
	$data['updates'][] = array(
		'id'=>md5(uniqid('', true)),
		'author'=>$author,
		'body'=>$body,
		'created_at'=>gmdate('c')
	);
	if (2000 < count($data['updates'])) {
		$data['updates'] = array_slice($data['updates'], -2000);
	}
	if (!social_microblog_write($data)) {
		return response('Kon update niet opslaan.', 500);
	}
	drop_cache('page', $key);
	return response(array(
		'html'=>social_microblog_render_updates($author, $key),
		'feed_html'=>social_microblog_render_feed_items()
	));
}


function social_microblog_delete($args)
{
	$id = isset($args['id']) ? $args['id'] : '';
	if ($id == '') {
		return response('Ongeldige aanvraag.', 400);
	}
	$data = social_microblog_read();
	foreach ($data['updates'] as $i=>$update) {
		if (isset($update['id']) && $update['id'] == $id) {
			if (!social_microblog_can_delete($update)) {
				return response('Je mag deze update niet verwijderen.', 403);
			}
			$author = isset($update['author']) ? $update['author'] : '';
			array_splice($data['updates'], $i, 1);
			social_microblog_write($data);
			if ($author != '') {
				drop_cache('page', social_profile_page($author).'.head');
			}
			return response(array(
				'html'=>social_microblog_render_updates($author, social_profile_page($author).'.head'),
				'feed_html'=>social_microblog_render_feed_items()
			));
		}
	}
	return response(array('feed_html'=>social_microblog_render_feed_items()));
}


function social_microblog_create($args)
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
		'type'=>'social_microblog',
		'module'=>'social_microblog',
		'microblog-title'=>'Updates',
		'microblog-background-color'=>'#ffffff',
		'microblog-font-color'=>'#202020',
		'microblog-font-family'=>'DejaVuSans',
		'microblog-font-size'=>'13px',
		'microblog-border-color'=>'#202020',
		'microblog-border-radius'=>'0px',
		'object-left'=>'580px',
		'object-top'=>'80px',
		'object-width'=>'420px',
		'object-height'=>'360px'
	));
	if ($ret['#error']) {
		return $ret;
	}
	$rendered = render_object(array('name'=>$name, 'edit'=>true));
	return $rendered['#error'] ? $rendered : response(array('name'=>$name, 'html'=>$rendered['#data']));
}


function social_microblog_timeline_authors($username = false)
{
	if ($username === false) {
		$username = social_current_username();
	}
	if (!$username) {
		return array();
	}
	$following = social_following($username);
	return array_values(array_unique(array_merge(array($username), $following)));
}


function social_microblog_render_timeline_composer($username)
{
	$ret = '<div class="social-timeline-composer">'.nl();
	$ret .= tab().social_avatar_html($username).nl();
	$ret .= tab().'<form class="social-timeline-form">'.nl();
	$ret .= tab(2).'<textarea name="body" maxlength="280" placeholder="Wat gebeurt er?"></textarea>'.nl();
	$ret .= tab(2).'<div class="social-timeline-actions"><span>Max 280 tekens</span><button type="submit">Plaats update</button></div>'.nl();
	$ret .= tab().'</form>'.nl();
	$ret .= '</div>'.nl();
	return $ret;
}


function social_microblog_render_feed_items($authors = false, $empty = 'Nog geen updates.')
{
	$updates = $authors === false ? social_microblog_updates('', 80) : social_microblog_updates_by_authors($authors, 80);
	foreach ($updates as $i=>$update) {
		$updates[$i]['feed_type'] = 'update';
	}
	if ($authors !== false) {
		load_modules('social_wall', true);
		if (function_exists('social_wall_feed_messages_by_authors')) {
			$updates = array_merge($updates, social_wall_feed_messages_by_authors($authors, 80));
			usort($updates, 'social_microblog_sort_desc');
			$updates = array_slice($updates, 0, 80);
		}
	}
	$ret = '<div class="social-feed-items">'.nl();
	if (!count($updates)) {
		$ret .= tab().'<p>'.htmlspecialchars($empty, ENT_NOQUOTES, 'UTF-8').'</p>'.nl();
	}
	foreach ($updates as $update) {
		$id = isset($update['id']) ? $update['id'] : '';
		$author = isset($update['author']) ? $update['author'] : '';
		$body = isset($update['body']) ? $update['body'] : '';
		$created = isset($update['created_at']) ? $update['created_at'] : '';
		$type = isset($update['feed_type']) ? $update['feed_type'] : 'update';
		$ret .= tab().'<article class="social-feed-update social-feed-'.$type.'" data-update-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'">'.nl();
		$ret .= tab(2).social_avatar_html($author).nl();
		$ret .= tab(2).'<div class="social-feed-content">'.nl();
		$ret .= tab(3).'<header><a href="'.htmlspecialchars(social_profile_url($author), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars(social_display_name($author), ENT_NOQUOTES, 'UTF-8').'</a>';
		if ($type == 'wall' && !empty($update['profile_owner'])) {
			$ret .= ' <span class="social-feed-context">&rarr; <a href="'.htmlspecialchars(social_profile_url($update['profile_owner']), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars(social_display_name($update['profile_owner']), ENT_NOQUOTES, 'UTF-8').'</a></span>';
		}
		$ret .= ' <time>'.htmlspecialchars(substr($created, 0, 16), ENT_NOQUOTES, 'UTF-8').'</time>';
		if ($type == 'update' && social_microblog_can_delete($update)) {
			$ret .= ' <button type="button" class="social-microblog-delete" data-update-id="'.htmlspecialchars($id, ENT_COMPAT, 'UTF-8').'" title="Verwijder update">x</button>';
		}
		$ret .= '</header>'.nl();
		$ret .= tab(3).'<div class="social-feed-body">'.nl2br(htmlspecialchars($body, ENT_NOQUOTES, 'UTF-8')).'</div>'.nl();
		$ret .= tab(2).'</div>'.nl();
		$ret .= tab().'</article>'.nl();
	}
	$ret .= '</div>'.nl();
	return $ret;
}


function social_microblog_timeline_post($args)
{
	$current = social_current_username();
	if (!$current) {
		return response('Log in om een update te plaatsen.', 403);
	}
	$args['page'] = social_profile_page($current).'.head';
	$ret = social_microblog_post($args);
	if (isset($ret['#error']) && $ret['#error']) {
		return $ret;
	}
	return response(array(
		'feed_html'=>social_microblog_render_feed_items(social_microblog_timeline_authors($current), 'Nog geen updates of berichten van jou of mensen die je volgt.')
	));
}


function social_microblog_feed_page()
{
	html_flush();
	default_html(false);
	html_add_css(base_url().'css/hotglue_error.css');
	html_add_css(base_url().'css/social.css');
	html_add_css(base_url().'css/social_microblog.css');
	$jquery = JQUERY;
	html_add_js(is_url($jquery) ? $jquery : base_url().$jquery, 1);
	html_add_js(base_url().(USE_MIN_FILES ? 'js/glue.min.js' : 'js/glue.js'), 3);
	html_add_js_var('$.glue.base_url', base_url());
	html_add_js_var('$.glue.conf.show_frontend_errors', SHOW_FRONTEND_ERRORS);
	html_add_js(base_url().'js/social_microblog.js', 6);
	$bdy = &body();
	elem_attr($bdy, 'id', 'social');
	body_append(social_top_nav('feed'));
	body_append(tab(1).'<div id="paper">'.nl());
	body_append(tab(2).'<div id="wrapper">'.nl());
	body_append(tab(3).'<div id="content">'.nl());
	body_append(tab(4).'<div id="left-nav">'.nl());
	body_append(tab(5).'<a class="rdbrr-logo" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'">rdbrr</a>'.nl());
	body_append(tab(4).'</div>'.nl());
	body_append(tab(4).'<div id="main">'.nl());
	body_append(tab(5).'<h1 id="error-title">Alles</h1>'.nl());
	body_append(tab(5).social_microblog_render_feed_items());
	body_append(tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">Tijdlijn</a> - <a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?profiles">Profielen</a></p>'.nl());
	body_append(tab(4).'</div>'.nl());
	body_append(tab(3).'</div>'.nl());
	body_append(tab(2).'</div>'.nl());
	body_append(tab(1).'</div>'.nl());
	echo html_finalize();
}


function social_microblog_timeline_page()
{
	$current = social_current_username();
	if (!$current) {
		header('Location: '.social_login_url('timeline'));
		die();
	}
	$following = social_following($current);
	$authors = social_microblog_timeline_authors($current);
	html_flush();
	default_html(false);
	html_add_css(base_url().'css/hotglue_error.css');
	html_add_css(base_url().'css/social.css');
	html_add_css(base_url().'css/social_microblog.css');
	$jquery = JQUERY;
	html_add_js(is_url($jquery) ? $jquery : base_url().$jquery, 1);
	html_add_js(base_url().(USE_MIN_FILES ? 'js/glue.min.js' : 'js/glue.js'), 3);
	html_add_js_var('$.glue.base_url', base_url());
	html_add_js_var('$.glue.conf.show_frontend_errors', SHOW_FRONTEND_ERRORS);
	html_add_js(base_url().'js/social_microblog.js', 6);
	$bdy = &body();
	elem_attr($bdy, 'id', 'social');
	body_append(social_top_nav('timeline'));
	body_append(tab(1).'<div id="paper">'.nl());
	body_append(tab(2).'<div id="wrapper">'.nl());
	body_append(tab(3).'<div id="content">'.nl());
	body_append(tab(4).'<div id="left-nav">'.nl());
	body_append(tab(5).'<a class="rdbrr-logo" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">rdbrr</a>'.nl());
	body_append(tab(4).'</div>'.nl());
	body_append(tab(4).'<div id="main">'.nl());
	body_append(tab(5).'<h1 id="error-title">Tijdlijn</h1>'.nl());
	body_append(tab(5).social_microblog_render_timeline_composer($current));
	if (!count($following)) {
		body_append(tab(5).'<p class="social-notice">Je volgt nog niemand. Voeg mensen toe via Profielen; daarna verschijnen hun updates en berichten hier chronologisch.</p>'.nl());
	}
	body_append(tab(5).social_microblog_render_feed_items($authors, 'Nog geen updates of berichten van jou of mensen die je volgt.').nl());
	body_append(tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?profiles">Mensen zoeken</a> - <a id="home" href="'.htmlspecialchars(social_profile_url($current), ENT_COMPAT, 'UTF-8').'/edit">Mijn profiel bewerken</a></p>'.nl());
	body_append(tab(4).'</div>'.nl());
	body_append(tab(3).'</div>'.nl());
	body_append(tab(2).'</div>'.nl());
	body_append(tab(1).'</div>'.nl());
	echo html_finalize();
}


function social_microblog_render_page_late($args)
{
	if ($args['edit']) {
		return;
	}
	$username = social_username_from_profile_page($args['page']);
	if ($username === false) {
		return;
	}
	body_append(social_top_nav('profile', $username));
}


function social_microblog_render_page_early($args)
{
	if (function_exists('_woff_fonts')) {
		foreach (_woff_fonts() as $font=>$styles) {
			_include_woff_font($font);
		}
	}
	if (!$args['edit']) {
		$jquery = JQUERY;
		html_add_js(is_url($jquery) ? $jquery : base_url().$jquery, 1);
		html_add_js(base_url().(USE_MIN_FILES ? 'js/glue.min.js' : 'js/glue.js'), 3);
		html_add_js_var('$.glue.base_url', base_url());
		html_add_js_var('$.glue.conf.show_frontend_errors', SHOW_FRONTEND_ERRORS);
	}
	html_add_css(base_url().'css/social.css', 5);
	html_add_css(base_url().'css/social_microblog.css', 6);
	html_add_js(base_url().'js/social_microblog.js', 6);
}


register_service('social_microblog.post', 'social_microblog_post', array('auth'=>true));
register_service('social_microblog.delete', 'social_microblog_delete', array('auth'=>true));
register_service('social_microblog.create', 'social_microblog_create', array('auth'=>true));
register_service('social_microblog.timeline_post', 'social_microblog_timeline_post', array('auth'=>true));
