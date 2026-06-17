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

	function refresh_messages(wall, data) {
		if (data && data.html) {
			$(wall).find('.social-wall-messages').replaceWith(data.html);
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
		$('html').unbind('mousedown.socialWallStyle');
	}

	function css_value(elem, prop) {
		return $(elem).css(prop) || '';
	}

	function schedule_save(wall, field) {
		clearTimeout($(wall).data('social-wall-save-timer'));
		$(wall).data('social-wall-save-timer', setTimeout(function() {
			if (field == 'title') {
				$.glue.backend({ method: 'glue.update_object', name: $(wall).attr('id'), 'wall-title': $(wall).find('.social-wall-title').first().text() });
			}
			$.glue.object.save(wall);
		}, 250));
	}

	function style_row(label, input) {
		return $('<label></label>').append($('<span></span>').text(label)).append(input);
	}

	function build_style_panel(wall, anchor) {
		close_style_panel();
		var panel = $('<div class="social-style-panel glue-ui"></div>');
		var head = $('<div class="social-style-panel-head"><strong>Berichten stijl</strong><button type="button" title="Sluiten">x</button></div>');
		panel.append(head);

		var title = $('<input type="text">').val($(wall).find('.social-wall-title').first().text());
		var bg = $('<input type="text">').val(css_value(wall, 'background-color'));
		var color = $('<input type="text">').val(css_value(wall, 'color'));
		var font = $('<select></select>');
		var currentFont = css_value(wall, 'font-family');
		for (var i=0; i < fonts.length; i++) {
			var opt = $('<option></option>').val(fonts[i][0]).text(fonts[i][1]);
			if (currentFont.indexOf(fonts[i][1]) != -1 || currentFont.indexOf(fonts[i][0]) != -1) {
				opt.attr('selected', 'selected');
			}
			font.append(opt);
		}
		var size = $('<input type="text">').val(css_value(wall, 'font-size'));
		var border = $('<input type="text">').val(css_value(wall, 'border-top-color'));
		var radius = $('<input type="text">').val(css_value(wall, 'border-top-left-radius'));

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
			$(wall).find('.social-wall-title').first().text($(this).val());
			schedule_save(wall, 'title');
		});
		bg.bind('keyup change', function() {
			$(wall).css('background-color', $(this).val());
			schedule_save(wall);
		});
		color.bind('keyup change', function() {
			$(wall).css('color', $(this).val());
			schedule_save(wall);
		});
		font.bind('change', function() {
			$(wall).css('font-family', $(this).val());
			schedule_save(wall);
		});
		size.bind('keyup change', function() {
			$(wall).css('font-size', $(this).val());
			schedule_save(wall);
		});
		border.bind('keyup change', function() {
			$(wall).css('border-color', $(this).val());
			schedule_save(wall);
		});
		radius.bind('keyup change', function() {
			$(wall).css('border-radius', $(this).val());
			schedule_save(wall);
		});
		setTimeout(function() {
			$('html').bind('mousedown.socialWallStyle', function(e) {
				if (!$(e.target).parents('.social-style-panel').length && !$(e.target).hasClass('social-wall-style')) {
					close_style_panel();
				}
			});
		}, 0);
	}

	$('.social_wall form.social-wall-form').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
	});

	$('.social_wall form.social-wall-form').live('submit', function(e) {
		e.preventDefault();
		var form = this;
		var wall = $(form).parents('.social_wall').first();
		var body = $.trim($(form).find('textarea[name="body"]').val());
		if (!body) {
			return false;
		}
		post({ method: 'social_wall.post', page: $(wall).attr('data-wall-page'), body: body }, function(data) {
			$(form).find('textarea[name="body"]').val('');
			refresh_messages(wall, data);
		});
		return false;
	});

	$('.social_wall .social-wall-delete').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
		if (e.type != 'click') {
			return false;
		}
		var wall = $(this).parents('.social_wall').first();
		post({ method: 'social_wall.delete', page: $(wall).attr('data-wall-page'), id: $(this).attr('data-message-id') }, function(data) {
			refresh_messages(wall, data);
		});
		return false;
	});

	$('.social_wall .social-wall-style').live('mousedown click dblclick', function(e) {
		e.stopPropagation();
		if (e.type != 'click') {
			return false;
		}
		var wall = $(this).parents('.social_wall').first();
		build_style_panel(wall, this);
		return false;
	});
});
