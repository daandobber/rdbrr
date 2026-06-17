$(document).ready(function() {
	function post(data, callback) {
		for (var key in data) {
			data[key] = JSON.stringify(data[key]);
		}
		$.post($.glue.base_url+'json.php', data, function(resp) {
			if (!resp || resp['#error']) {
				$.glue.error(resp && resp['#data'] ? resp['#data'] : 'Er ging iets mis.');
				return;
			}
			if (typeof callback == 'function') {
				callback(resp['#data']);
			}
		}, 'json').error(function() {
			$.glue.error('Je moet ingelogd zijn om dit te doen.');
		});
	}

	function refresh_microblog(microblog, data) {
		if (data && data.html && microblog && $(microblog).length) {
			$(microblog).find('.social-microblog-updates').replaceWith(data.html);
		}
		if (data && data.feed_html && $('.social-feed-items').length) {
			$('.social-feed-items').replaceWith(data.feed_html);
		}
	}

	var fonts = [
		['DejaVuSans', 'DejaVu Sans'],
		['DejaVuSerif', 'DejaVu Serif'],
		['DejaVuSansMono', 'DejaVu Mono'],
		['LatinModern', 'Latin Modern'],
		['Verdana, Geneva, Tahoma, sans-serif', 'Verdana'],
		['Arial, Helvetica, sans-serif', 'Arial'],
		['Georgia, serif', 'Georgia'],
		['"Courier New", Courier, monospace', 'Courier New']
	];

	function close_style_panel() {
		$('.social-style-panel').remove();
		$('html').unbind('mousedown.socialMicroblogStyle');
	}

	function style_row(label, input) {
		return $('<label></label>').append($('<span></span>').text(label)).append(input);
	}

	function schedule_save(microblog, field) {
		clearTimeout($(microblog).data('social-microblog-save-timer'));
		$(microblog).data('social-microblog-save-timer', setTimeout(function() {
			if (field == 'title') {
				$.glue.backend({ method: 'glue.update_object', name: $(microblog).attr('id'), 'microblog-title': $(microblog).find('.social-microblog-title').first().text() });
			}
			$.glue.object.save(microblog);
		}, 250));
	}

	function build_style_panel(microblog, anchor) {
		close_style_panel();
		var panel = $('<div class="social-style-panel glue-ui"></div>');
		var head = $('<div class="social-style-panel-head"><strong>Updates stijl</strong><button type="button" title="Sluiten">x</button></div>');
		panel.append(head);

		var title = $('<input type="text">').val($(microblog).find('.social-microblog-title').first().text());
		var bg = $('<input type="text">').val($(microblog).css('background-color'));
		var color = $('<input type="text">').val($(microblog).css('color'));
		var font = $('<select></select>');
		var currentFont = $(microblog).css('font-family') || '';
		for (var i=0; i < fonts.length; i++) {
			var opt = $('<option></option>').val(fonts[i][0]).text(fonts[i][1]);
			if (currentFont.indexOf(fonts[i][1]) != -1 || currentFont.indexOf(fonts[i][0]) != -1) {
				opt.attr('selected', 'selected');
			}
			font.append(opt);
		}
		var size = $('<input type="text">').val($(microblog).css('font-size'));
		var border = $('<input type="text">').val($(microblog).css('border-top-color'));
		var radius = $('<input type="text">').val($(microblog).css('border-top-left-radius'));

		panel.append(style_row('Titel', title));
		panel.append(style_row('Achtergrond', bg));
		panel.append(style_row('Tekst', color));
		panel.append(style_row('Lettertype', font));
		panel.append(style_row('Grootte', size));
		panel.append(style_row('Rand', border));
		panel.append(style_row('Hoeken', radius));

		var pos = $(anchor).offset();
		panel.css({
			left: Math.max(8, pos.left - 190)+'px',
			top: Math.max(38, pos.top + 24)+'px'
		});
		$('body').append(panel);
		if ($.glue.social_window) {
			$.glue.social_window.make_draggable(panel, head);
		}
		panel.bind('mousedown click dblclick', function(e) {
			e.stopPropagation();
		});
		head.find('button').bind('click', function() {
			close_style_panel();
			return false;
		});
		title.bind('keyup change', function() {
			$(microblog).find('.social-microblog-title').first().text($(this).val());
			schedule_save(microblog, 'title');
		});
		bg.bind('keyup change', function() {
			$(microblog).css('background-color', $(this).val());
			schedule_save(microblog);
		});
		color.bind('keyup change', function() {
			$(microblog).css('color', $(this).val());
			schedule_save(microblog);
		});
		font.bind('change', function() {
			$(microblog).css('font-family', $(this).val());
			schedule_save(microblog);
		});
		size.bind('keyup change', function() {
			$(microblog).css('font-size', $(this).val());
			schedule_save(microblog);
		});
		border.bind('keyup change', function() {
			$(microblog).css('border-color', $(this).val());
			schedule_save(microblog);
		});
		radius.bind('keyup change', function() {
			$(microblog).css('border-radius', $(this).val());
			schedule_save(microblog);
		});
		setTimeout(function() {
			$('html').bind('mousedown.socialMicroblogStyle', function(e) {
				if (!$(e.target).parents('.social-style-panel').length && !$(e.target).hasClass('social-microblog-style')) {
					close_style_panel();
				}
			});
		}, 0);
	}

	$('.social-timeline-form').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
	});

	$('.social-timeline-form').live('submit', function(e) {
		e.preventDefault();
		var form = this;
		var body = $.trim($(form).find('textarea[name="body"]').val());
		if (!body) {
			return false;
		}
		post({ method: 'social_microblog.timeline_post', body: body }, function(data) {
			$(form).find('textarea[name="body"]').val('');
			refresh_microblog(null, data);
		});
		return false;
	});

	$('.social_microblog .social-microblog-delete, .social-feed-items .social-microblog-delete').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
		if (e.type != 'click') {
			return false;
		}
		var microblog = $(this).parents('.social_microblog').first();
		post({ method: 'social_microblog.delete', id: $(this).attr('data-update-id') }, function(data) {
			refresh_microblog(microblog, data);
		});
		return false;
	});

	$('.social_microblog .social-microblog-style').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
		if (e.type != 'click') {
			return false;
		}
		var microblog = $(this).parents('.social_microblog').first();
		build_style_panel(microblog, this);
		return false;
	});
});
