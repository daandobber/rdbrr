/*
 * Photoshop-style editor chrome for social profile pages.
 */

$(document).ready(function() {
	function stop_canvas_events(elem) {
		$(elem).bind('mousedown mouseup click dblclick', function(e) {
			e.stopPropagation();
		});
	}

	$.glue.social_window = {
		make_draggable: function(panel, head) {
			$(head).addClass('social-window-drag-handle');
			$(panel).bind('mousedown', function() {
				$('.social-editor-popover, .social-style-panel, .social-layers-panel').css('z-index', 100020);
				$(panel).css('z-index', 100030);
			});
			if ($.fn.draggable) {
				$(panel).draggable({
					addClasses: false,
					handle: head,
					cancel: 'button,input,select,textarea,.social-editor-menu-upload'
				});
			}
		}
	};

	function place_object(elem) {
		var x = $(window).scrollLeft()+Math.max(180, Math.round($(window).width()/2));
		var y = $(window).scrollTop()+Math.max(140, Math.round($(window).height()/2));
		$('body').append(elem);
		$(elem).css('width', $(elem).width()+'px');
		$(elem).css('height', $(elem).height()+'px');
		$(elem).css('left', (x-$(elem).outerWidth()/2)+'px');
		$(elem).css('top', (y-$(elem).outerHeight()/2)+'px');
		$.glue.object.register(elem);
		$.glue.sel.none();
		$.glue.sel.select(elem);
		snap_object_to_grid(elem);
		$.glue.object.save(elem);
	}

	function make_text() {
		$.glue.backend({ method: 'glue.create_object', page: $.glue.page }, function(data) {
			var elem = $('<div class="text resizable object" style="position: absolute; background-color: rgb(255, 255, 255); color: rgb(32, 32, 32); font-family: DejaVuSans; font-size: 24px; width: 260px; height: 120px;"><textarea class="glue-text-input" style="display: none; height: 100%; width: 100%;"></textarea><div class="glue-text-render" style="height: 100%; width: 100%;">Dubbelklik om tekst te typen</div></div>');
			$(elem).attr('id', data.name);
			$('body').append(elem);
			$(elem).css('width', '260px');
			$(elem).css('height', '120px');
			$(elem).css('left', ($(window).scrollLeft()+Math.max(180, Math.round($(window).width()/2))-130)+'px');
			$(elem).css('top', ($(window).scrollTop()+Math.max(140, Math.round($(window).height()/2))-60)+'px');
			$.glue.object.register(elem);
			$.glue.sel.none();
			$.glue.sel.select(elem);
			snap_object_to_grid(elem);
			$.glue.backend({ method: 'glue.update_object', name: $(elem).attr('id'), content: 'Dubbelklik om tekst te typen' });
			$.glue.object.save(elem);
		});
	}

	function normalize_url(url) {
		if (!url) {
			return false;
		}
		if (url.indexOf('//') == -1) {
			url = '//'+url;
		} else {
			url = '//'+url.split('//')[1];
		}
		return url;
	}

	function close_editor_panel() {
		$('.social-editor-popover').remove();
		if ($.glue.colorpicker) {
			$.glue.colorpicker.hide(true);
		}
		$('html').unbind('mousedown.socialEditorPopover');
	}

	function panel_row(label, input) {
		return $('<label></label>').append($('<span></span>').text(label)).append(input);
	}

	function open_editor_panel(anchor, title, rows, actionLabel, action) {
		close_editor_panel();
		var panel = $('<div class="social-editor-popover glue-ui"></div>');
		var head = $('<div class="social-editor-popover-head"><strong></strong><button type="button" title="Sluiten">x</button></div>');
		head.find('strong').text(title);
		panel.append(head);
		for (var i=0; i < rows.length; i++) {
			panel.append(rows[i]);
		}
		var actions = $('<div class="social-editor-popover-actions"></div>');
		var apply = $('<button type="button"></button>').text(actionLabel || 'Toepassen');
		actions.append(apply);
		panel.append(actions);
		var pos = $(anchor).offset() || { left: 80, top: 40 };
		panel.css({
			left: Math.max(8, pos.left)+'px',
			top: Math.max(38, pos.top + 24)+'px'
		});
		$('body').append(panel);
		$.glue.social_window.make_draggable(panel, head);
		panel.bind('mousedown click dblclick', function(e) {
			e.stopPropagation();
		});
		head.find('button').bind('click', function() {
			close_editor_panel();
			return false;
		});
		apply.bind('click', function() {
			if (action() !== false) {
				close_editor_panel();
			}
			return false;
		});
		panel.find('input').first().focus();
		setTimeout(function() {
			$('html').bind('mousedown.socialEditorPopover', function(e) {
				if (!$(e.target).parents('.social-editor-popover').length) {
					close_editor_panel();
				}
			});
		}, 0);
		return panel;
	}

	function open_single_input_panel(anchor, title, label, value, actionLabel, action) {
		var input = $('<input type="text">').val(value || '');
		input.bind('keydown', function(e) {
			if (e.which == 13) {
				$(this).parents('.social-editor-popover').find('.social-editor-popover-actions button').trigger('click');
				return false;
			}
		});
		open_editor_panel(anchor, title, [panel_row(label, input)], actionLabel, function() {
			return action(input.val());
		});
	}

	function make_iframe(anchor) {
		open_single_input_panel(anchor, 'Web element', 'URL', '', 'Plaats', function(value) {
			var url = normalize_url(value);
			if (!url) {
				return false;
			}
			$.glue.backend({ method: 'glue.create_object', page: $.glue.page }, function(data) {
				var elem = $('<div class="iframe resizable object" style="position: absolute; width: 420px; height: 260px;"></div>');
				var frame = $('<iframe style="background-color: transparent; border-width: 0px; height: 100%; position: absolute; width: 100%;"></iframe>');
				var shield = $('<div class="glue-iframe-shield glue-ui" style="height: 100%; position: absolute; width: 100%;" title="visitors will be able to interact with the webpage below"></div>');
				$(elem).attr('id', data.name);
				$(frame).attr('name', data.name);
				$(frame).attr('src', url);
				$(elem).append(frame);
				$(elem).append(shield);
				place_object(elem);
			});
		});
	}

	function parse_webvideo(url) {
		var match;
		match = String(url).match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
		if (match) {
			return { provider: 'youtube', id: match[1] };
		}
		match = String(url).match(/(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)([0-9]+)/);
		if (match) {
			return { provider: 'vimeo', id: match[1] };
		}
		return false;
	}

	function build_webvideo_iframe(video) {
		if (video.provider == 'youtube') {
			return $('<iframe class="youtube-player" src="https://www.youtube-nocookie.com/embed/'+video.id+'?rel=0&amp;playsinline=1" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="YouTube video player" style="border-width: 0px; height: 100%; position: absolute; width: 100%;"></iframe>');
		}
		return $('<iframe src="https://player.vimeo.com/video/'+video.id+'?title=0&amp;byline=0&amp;portrait=0&amp;color=ffffff" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen="allowfullscreen" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="Vimeo video player" style="border-width: 0px; height: 100%; position: absolute; width: 100%;"></iframe>');
	}

	function make_webvideo(anchor) {
		open_single_input_panel(anchor, 'Video', 'YouTube/Vimeo URL', '', 'Plaats', function(value) {
			var video = value ? parse_webvideo(value) : false;
			if (!video) {
				$.glue.error('Alleen YouTube en Vimeo links worden nu ondersteund.');
				return false;
			}
			$.glue.backend({ method: 'glue.create_object', page: $.glue.page }, function(data) {
				var elem = $('<div class="webvideo resizable object" style="position: absolute; width: 420px; height: 236px;"></div>');
				$(elem).attr('id', data.name);
				$(elem).append(build_webvideo_iframe(video));
				$(elem).append($('<div class="glue-webvideo-handle glue-ui" title="drag here"></div>'));
				place_object(elem);
				$.glue.backend({ method: 'glue.update_object', name: $(elem).attr('id'), 'webvideo-provider': video.provider, 'webvideo-id': video.id });
			});
		});
	}

	function make_wall() {
		$.glue.backend({ method: 'social_wall.create', page: $.glue.page }, function(data) {
			var elem = $(data.html);
			$('body').append(elem);
			$.glue.object.register(elem);
			$.glue.sel.none();
			$.glue.sel.select(elem);
			snap_object_to_grid(elem);
			$.glue.object.save(elem);
		});
	}

	function make_microblog() {
		$.glue.backend({ method: 'social_microblog.create', page: $.glue.page }, function(data) {
			var elem = $(data.html);
			$('body').append(elem);
			$.glue.object.register(elem);
			$.glue.sel.none();
			$.glue.sel.select(elem);
			snap_object_to_grid(elem);
			$.glue.object.save(elem);
		});
	}

	function set_active_tool(button, label) {
		$('.social-editor-tool').removeClass('social-editor-tool-active');
		$(button).addClass('social-editor-tool-active');
	}

	function icon_svg(name) {
		var icons = {
			select: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3l13 9-6 1.2 3.6 5.4-2.3 1.5-3.6-5.4L6 19 5 3z"/></svg>',
			text: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v3h-1.5l-.4-1H13v12h2v2H9v-2h2V7H5.9l-.4 1H4V5z"/></svg>',
			upload: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l5 5h-3v7h-4V8H7l5-5z"/><path d="M5 17h14v4H5v-4z"/></svg>',
			web: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5zm2 4h12V7H6v2zm0 2v6h12v-6H6z"/></svg>',
			video: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h11v12H4V6zm13 4l4-3v10l-4-3v-4z"/></svg>',
			wall: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4V5zm3 3v2h10V8H7zm0 4v2h7v-2H7z"/></svg>',
			microblog: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v3H5V4zm0 6h14v3H5v-3zm0 6h9v3H5v-3z"/><path d="M17 15l4 2-4 2v-4z"/></svg>'
		};
		return icons[name] || icons.select;
	}

	function tool_button(label, icon, action) {
		var button = $('<div class="social-editor-tool" role="button" tabindex="0" title="'+label+'">'+icon_svg(icon)+'<span>'+label+'</span></div>');
		stop_canvas_events(button);
		button.bind('click', function() {
			set_active_tool(this, label);
			action(button);
			return false;
		});
		button.bind('keydown', function(e) {
			if (e.which == 13 || e.which == 32) {
				$(this).trigger('click');
				return false;
			}
		});
		return button;
	}

	function upload_tool_button(label, icon) {
		var button = $('<div class="social-editor-tool social-editor-upload-tool" title="'+label+'">'+icon_svg(icon)+'<span>'+label+'</span></div>');
		stop_canvas_events(button);
		return button;
	}

	function snap_object_to_grid(obj) {
		if (($.glue.grid.mode() & 6) == 0 || $(obj).hasClass('locked')) {
			return false;
		}
		var pos = $(obj).position();
		var gridX = $.glue.grid.x();
		var gridY = $.glue.grid.y();
		if (gridX <= 0 || gridY <= 0) {
			return false;
		}
		var left = Math.round(pos.left/gridX)*gridX;
		var top = Math.round(pos.top/gridY)*gridY;
		if (left == pos.left && top == pos.top) {
			return false;
		}
		$(obj).css('left', left+'px');
		$(obj).css('top', top+'px');
		return true;
	}

	function page_object_name() {
		return $.glue.page+'.page';
	}

	function encode_custom_css(value) {
		return window.btoa(unescape(encodeURIComponent(value || '')));
	}

	function decode_custom_css(value) {
		if (!value) {
			return '';
		}
		try {
			return decodeURIComponent(escape(window.atob(value)));
		} catch (e) {
			return '';
		}
	}

	function apply_page_custom_css(css) {
		var style = $('#rdbrr-page-custom-css');
		if (!style.length) {
			style = $('<style id="rdbrr-page-custom-css"></style>').appendTo('head');
		}
		style.text(css || '');
	}

	function open_css_editor_panel(anchor, title, initialValue, action) {
		close_editor_panel();
		var panel = $('<div class="social-editor-popover social-css-editor-panel glue-ui"></div>');
		var head = $('<div class="social-editor-popover-head"><strong></strong><button type="button" title="Sluiten">x</button></div>');
		var textarea = $('<textarea spellcheck="false" placeholder="Schrijf hier je CSS"></textarea>').val(initialValue || '');
		var actions = $('<div class="social-editor-popover-actions"></div>');
		var clear = $('<button type="button">Wissen</button>');
		var apply = $('<button type="button">Opslaan</button>');
		head.find('strong').text(title);
		actions.append(clear).append(apply);
		panel.append(head).append(textarea).append(actions);
		var pos = $(anchor).offset() || { left: 80, top: 40 };
		panel.css({
			left: Math.max(8, pos.left)+'px',
			top: Math.max(38, pos.top + 24)+'px'
		});
		$('body').append(panel);
		$.glue.social_window.make_draggable(panel, head);
		panel.bind('mousedown click dblclick', function(e) {
			e.stopPropagation();
		});
		head.find('button').bind('click', function() {
			close_editor_panel();
			return false;
		});
		clear.bind('click', function() {
			textarea.val('').focus();
			return false;
		});
		apply.bind('click', function() {
			action(textarea.val());
			close_editor_panel();
			return false;
		});
		textarea.focus();
		setTimeout(function() {
			$('html').bind('mousedown.socialEditorPopover', function(e) {
				if (!$(e.target).parents('.social-editor-popover').length) {
					close_editor_panel();
				}
			});
		}, 0);
		return panel;
	}

	function open_page_css_panel(anchor) {
		$.glue.backend({ method: 'glue.load_object', name: page_object_name() }, function(data) {
			open_css_editor_panel(anchor, 'Pagina CSS', decode_custom_css(data && data['page-custom-css-b64']), function(css) {
				var payload = { method: 'glue.update_object', name: page_object_name() };
				payload['page-custom-css-b64'] = encode_custom_css(css);
				$.glue.backend(payload, function() {
					apply_page_custom_css(css);
				});
			});
		});
	}

	function set_page_title(anchor) {
		open_single_input_panel(anchor, 'Pagina', 'Titel', $('title').text(), 'Opslaan', function(title) {
			$('title').text(title);
			$.glue.backend({ method: 'glue.update_object', name: page_object_name(), 'page-title': title });
		});
	}

	function save_page_background(attrs) {
		attrs.method = 'glue.update_object';
		attrs.name = page_object_name();
		$.glue.backend(attrs);
	}

	function remove_page_background_attrs(attrs) {
		$.glue.backend({ method: 'glue.object_remove_attr', name: page_object_name(), attr: attrs });
	}

	function apply_background_position(x, y) {
		var value = (x || 'center')+' '+(y || 'center');
		$('html').css('background-position', value);
		save_page_background({ 'page-background-image-position': value });
	}

	function open_background_panel(anchor) {
		close_editor_panel();
		var panel = $('<div class="social-editor-popover social-background-panel glue-ui"></div>');
		var head = $('<div class="social-editor-popover-head"><strong>Achtergrond</strong><button type="button" title="Sluiten">x</button></div>');
		panel.append(head);

		var color = $('<input type="hidden">').val($('html').css('background-color') || '');
		var colorSwatch = $('<button type="button" class="social-background-color-swatch" title="Kleur kiezen"></button>');
		var colorPick = $('<button type="button" class="social-color-button">Kies kleur</button>');
		var colorWrap = $('<div class="social-background-color-row"></div>').append(color).append(colorSwatch).append(colorPick);
		var size = $('<select></select>')
			.append('<option value="cover">Cover/crop</option>')
			.append('<option value="contain">Contain</option>')
			.append('<option value="auto">Origineel</option>')
			.append('<option value="100% auto">Breedte 100%</option>')
			.append('<option value="auto 100%">Hoogte 100%</option>');
		var currentSize = $('html').css('background-size');
		if (currentSize == 'auto auto') {
			currentSize = 'auto';
		}
		if (currentSize && currentSize != 'auto') {
			size.val(currentSize);
		} else {
			size.val('cover');
		}
		var pos = ($('html').css('background-position') || 'center center').split(' ');
		var posX = $('<input type="text">').val(pos[0] || 'center');
		var posY = $('<input type="text">').val(pos[1] || 'center');
		var repeat = $('<select></select>')
			.append('<option value="no-repeat">Niet herhalen</option>')
			.append('<option value="repeat">Herhalen</option>')
			.append('<option value="repeat-x">Horizontaal</option>')
			.append('<option value="repeat-y">Verticaal</option>');
		var currentRepeat = $('html').css('background-repeat');
		if (currentRepeat && currentRepeat != 'repeat') {
			repeat.val(currentRepeat);
		} else {
			repeat.val('no-repeat');
		}
		var attachment = $('<select></select>')
			.append('<option value="scroll">Scrollt mee</option>')
			.append('<option value="fixed">Vast</option>');
		attachment.val($('html').css('background-attachment') == 'fixed' ? 'fixed' : 'scroll');
		var uploadItem = $('<div class="social-editor-menu-item social-editor-menu-upload social-background-upload">Afbeelding uploaden</div>');
		var clearImage = $('<button type="button">Afbeelding wissen</button>');
		var clearColor = $('<button type="button">Kleur wissen</button>');
		var updateColor = function(value, save) {
			color.val(value || '');
			colorSwatch.css({
				'background-color': value || 'transparent',
				'background-image': value ? 'none' : ''
			});
			if (value) {
				$('html').css('background-color', value);
				$.glue.grid.update(true);
				if (save) {
					save_page_background({ 'page-background-color': value });
				}
			}
		};
		updateColor(color.val(), false);

		panel.append(panel_row('Kleur', colorWrap));
		panel.append(panel_row('Afbeelding', uploadItem));
		panel.append(panel_row('Weergave', size));
		panel.append(panel_row('Positie X', posX));
		panel.append(panel_row('Positie Y', posY));
		panel.append(panel_row('Herhalen', repeat));
		panel.append(panel_row('Scroll', attachment));
		panel.append($('<div class="social-background-actions"></div>').append(clearImage).append(clearColor));

		var anchorPos = $(anchor).offset() || { left: 80, top: 40 };
		panel.css({
			left: Math.max(8, anchorPos.left)+'px',
			top: Math.max(38, anchorPos.top + 24)+'px'
		});
		$('body').append(panel);
		$.glue.social_window.make_draggable(panel, head);

		panel.bind('mousedown click dblclick', function(e) {
			e.stopPropagation();
		});
		head.find('button').bind('click', function() {
			close_editor_panel();
			$.glue.colorpicker.hide(true);
			return false;
		});
		colorPick.add(colorSwatch).bind('click', function(e) {
			e.stopPropagation();
			$.glue.colorpicker.show(color.val(), false, function(value) {
				updateColor(value, false);
			}, function(value) {
				if (value) {
					updateColor(value, true);
				}
			});
			return false;
		});
		size.bind('change', function() {
			$('html').css('background-size', $(this).val());
			save_page_background({ 'page-background-size': $(this).val() });
		});
		posX.add(posY).bind('keyup change', function() {
			apply_background_position(posX.val(), posY.val());
		});
		repeat.bind('change', function() {
			$('html').css('background-repeat', $(this).val());
			save_page_background({ 'page-background-repeat': $(this).val() });
		});
		attachment.bind('change', function() {
			$('html').css('background-attachment', $(this).val());
			save_page_background({ 'page-background-attachment': $(this).val() });
		});
		clearImage.bind('click', function() {
			$('html').css('background-image', '');
			$.glue.backend({ method: 'page.clear_background_img', page: $.glue.page });
			remove_page_background_attrs(['page-background-size', 'page-background-repeat', 'page-background-image-position']);
			return false;
		});
		clearColor.bind('click', function() {
			updateColor('', false);
			$('html').css('background-color', '');
			$.glue.grid.update(true);
			remove_page_background_attrs('page-background-color');
			return false;
		});

		var upload = {
			error: function(e) {
				if (e && e.target && e.target.status) {
					$.glue.error('Upload mislukt (status '+e.target.status+').');
				} else {
					$.glue.error('Upload mislukt. Controleer de bestandsgrootte en het bestandsformaat.');
					console.error(e);
				}
			},
			finish: function(data) {
				if (!data) {
					$.glue.error('Er was een probleem met de server.');
				} else if (data['#error']) {
					$.glue.error('Upload mislukt ('+data['#data']+').');
				} else {
					$('html').css('background-image', 'url('+$.glue.base_url+encodeURIComponent(page_object_name())+'?'+(new Date().getTime())+')');
					$('html').css('background-size', size.val());
					$('html').css('background-repeat', repeat.val());
					apply_background_position(posX.val(), posY.val());
					save_page_background({
						'page-background-size': size.val(),
						'page-background-repeat': repeat.val(),
						'page-background-image-position': (posX.val() || 'center')+' '+(posY.val() || 'center')
					});
				}
			},
			tooltip: 'achtergrondafbeelding uploaden'
		};
		$.glue.upload.button(uploadItem, { method: 'glue.upload_files', page: $.glue.page, preferred_module: 'page' }, upload);

		setTimeout(function() {
			$('html').bind('mousedown.socialEditorPopover', function(e) {
				if (!$(e.target).parents('.social-editor-popover').length && !$(e.target).parents('#glue-colorpicker').length) {
					close_editor_panel();
					$.glue.colorpicker.hide(true);
				}
			});
		}, 0);
	}

	function set_grid_size(anchor) {
		var current = $.glue.grid.x() == $.glue.grid.y() ? String($.glue.grid.x()) : $.glue.grid.x()+' '+$.glue.grid.y();
		open_single_input_panel(anchor, 'Raster', 'Grootte', current, 'Opslaan', function(value) {
			var parts = value.replace(',', ' ').split(/\s+/);
			var x = parseInt(parts[0], 10);
			var y = parts.length > 1 ? parseInt(parts[1], 10) : x;
			if (isNaN(x) || isNaN(y) || x < 4 || y < 4) {
				$.glue.error('Gebruik een rastergrootte van minimaal 4 pixels.');
				return false;
			}
			$.glue.grid.x(x);
			$.glue.grid.y(y);
			$.glue.grid.mode($.glue.grid.mode() | 1);
			$.glue.grid.update(true);
		});
	}

	function selected_text_elements() {
		return $('.glue-selected').filter('.text, .social_wall, .social_microblog');
	}

	function require_text_selection() {
		var elems = selected_text_elements();
		if (!elems.length) {
			$.glue.error('Selecteer eerst een tekstobject of berichtenblok.');
			return false;
		}
		return elems;
	}

	function set_selected_text_css(prop, value) {
		var elems = require_text_selection();
		if (!elems) {
			return;
		}
		elems.each(function() {
			$(this).css(prop, value);
			$(this).find('.glue-text-input').css(prop, value);
			$.glue.object.save(this);
		});
	}

	function set_selected_font_family(font) {
		set_selected_text_css('font-family', font);
	}

	function prompt_selected_font_size(anchor) {
		var elems = require_text_selection();
		if (!elems) {
			return;
		}
		var current = $(elems[0]).css('font-size') || '24px';
		open_single_input_panel(anchor, 'Tekst', 'Grootte', current, 'Opslaan', function(size) {
			if (size) {
				set_selected_text_css('font-size', size);
			}
		});
	}

	function prompt_selected_text_color(anchor) {
		var elems = require_text_selection();
		if (!elems) {
			return;
		}
		open_single_input_panel(anchor, 'Tekst', 'Kleur', $(elems[0]).css('color'), 'Opslaan', function(color) {
			if (color) {
				set_selected_text_css('color', color);
			}
		});
	}

	function layer_label(obj) {
		if ($(obj).hasClass('text')) {
			var text = $.trim($(obj).find('.glue-text-render').text() || $(obj).text());
			return text ? 'Tekst: '+text.substring(0, 22) : 'Tekst';
		}
		if ($(obj).hasClass('social_wall')) {
			return 'Berichten';
		}
		if ($(obj).hasClass('social_microblog')) {
			return 'Updates';
		}
		if ($(obj).hasClass('webvideo')) {
			return 'Video';
		}
		if ($(obj).hasClass('iframe')) {
			return 'Web';
		}
		if ($(obj).hasClass('image')) {
			return 'Afbeelding';
		}
		return 'Object';
	}

	function layer_preview(obj) {
		var preview = $('<span class="social-layer-preview"></span>');
		var bg = $(obj).css('background-image');
		if (bg && bg != 'none') {
			preview.addClass('social-layer-preview-image').css('background-image', bg);
			return preview;
		}
		if ($(obj).find('img').length) {
			preview.addClass('social-layer-preview-image').css('background-image', 'url('+$(obj).find('img').first().attr('src')+')');
			return preview;
		}
		if ($(obj).hasClass('text')) {
			return preview.addClass('social-layer-preview-text').text('T');
		}
		if ($(obj).hasClass('social_wall')) {
			return preview.addClass('social-layer-preview-icon').text('B');
		}
		if ($(obj).hasClass('social_microblog')) {
			return preview.addClass('social-layer-preview-icon').text('U');
		}
		if ($(obj).hasClass('webvideo')) {
			return preview.addClass('social-layer-preview-icon').text('>');
		}
		if ($(obj).hasClass('iframe')) {
			return preview.addClass('social-layer-preview-icon').text('W');
		}
		return preview.addClass('social-layer-preview-icon').text('O');
	}

	function object_z(obj) {
		var z = parseInt($(obj).css('z-index'), 10);
		return isNaN(z) ? 0 : z;
	}

	function sorted_layer_objects() {
		var objects = $('.object').get();
		objects.sort(function(a, b) {
			var diff = object_z(b)-object_z(a);
			if (diff !== 0) {
				return diff;
			}
			return $(b).index()-$(a).index();
		});
		return objects;
	}

	function select_layer_item(item, e) {
		var obj = $(document.getElementById($(item).attr('data-object-id')));
		if (!obj.length) {
			refresh_layers_panel();
			return false;
		}
		if (!e.shiftKey) {
			$.glue.sel.none();
		}
		$.glue.sel.select(obj);
		refresh_layers_panel();
		return false;
	}

	function refresh_layers_panel() {
		var list = $('#social-editor-layers-panel .social-layers-list');
		if (!list.length) {
			return;
		}
		list.empty();
		var objects = sorted_layer_objects();
		if (!objects.length) {
			list.append($('<div class="social-layers-empty">Geen objecten</div>'));
			return;
		}
		for (var i=0; i < objects.length; i++) {
			var obj = objects[i];
			var item = $('<button type="button" class="social-layer-item"></button>');
			item.attr('data-object-id', $(obj).attr('id'));
			if ($(obj).hasClass('glue-selected')) {
				item.addClass('social-layer-selected');
			}
			item.append(layer_preview(obj));
			item.append($('<span class="social-layer-name"></span>').text(layer_label(obj)));
			item.append($('<span class="social-layer-z"></span>').text(object_z(obj)));
			item.bind('mousedown click dblclick', function(e) {
				e.stopPropagation();
				if (e.type == 'click') {
					return select_layer_item(this, e);
				}
				return false;
			});
			list.append(item);
		}
	}

	function selected_layer_objects() {
		var selected = $('.glue-selected').not('.locked');
		if (!selected.length) {
			$.glue.error('Selecteer eerst een laag.');
			return false;
		}
		return selected;
	}

	function move_selected_layers(direction) {
		var selected = selected_layer_objects();
		if (!selected) {
			return;
		}
		var selectedObjects = selected.get();
		selectedObjects.sort(function(a, b) {
			return direction == 'up' ? object_z(a)-object_z(b) : object_z(b)-object_z(a);
		});
		var maxZ = 0;
		var minZ = 9999;
		$('.object').not(selected).each(function() {
			var z = object_z(this);
			maxZ = Math.max(maxZ, z);
			minZ = Math.min(minZ, z);
		});
		if (minZ == 9999) {
			minZ = 100;
		}
		for (var i=0; i < selectedObjects.length; i++) {
			if (direction == 'up') {
				$(selectedObjects[i]).css('z-index', maxZ+i+1);
			} else {
				$(selectedObjects[i]).css('z-index', minZ-i-1);
			}
		}
		$.glue.stack.compress();
		selected.each(function() {
			$.glue.object.save(this);
		});
		refresh_layers_panel();
	}

	function bring_selected_layers_into_view() {
		var selected = selected_layer_objects();
		if (!selected) {
			return;
		}
		var centerX = $(window).scrollLeft()+Math.round($(window).width()/2);
		var centerY = $(window).scrollTop()+Math.round($(window).height()/2);
		selected.each(function(index) {
			var width = $(this).outerWidth() || 120;
			var height = $(this).outerHeight() || 80;
			var offset = index*24;
			$(this).css({
				left: Math.max(0, Math.round(centerX-(width/2)+offset))+'px',
				top: Math.max(0, Math.round(centerY-(height/2)+offset))+'px'
			});
			$.glue.object.save(this);
		});
		refresh_layers_panel();
	}

	function show_layers_panel() {
		var existing = $('#social-editor-layers-panel');
		if (existing.length) {
			existing.show();
			refresh_layers_panel();
			return existing;
		}
		var panel = $('<div id="social-editor-layers-panel" class="social-layers-panel glue-ui"></div>');
		var head = $('<div class="social-layers-panel-head"><strong>Lagen</strong><button type="button" title="Sluiten">x</button></div>');
		var actions = $('<div class="social-layers-actions"></div>');
		var up = $('<button type="button" title="Naar voren">Boven</button>');
		var down = $('<button type="button" title="Naar achteren">Onder</button>');
		var refresh = $('<button type="button" title="Vernieuwen">Ververs</button>');
		var inView = $('<button type="button" title="Zet geselecteerde laag terug in beeld">In beeld</button>');
		actions.append(up).append(down).append(inView).append(refresh);
		panel.append(head).append(actions).append($('<div class="social-layers-list"></div>'));
		panel.css({
			left: Math.max(64, $(window).width()-250)+'px',
			top: '84px'
		});
		$('body').append(panel);
		panel.bind('mousedown click dblclick', function(e) {
			e.stopPropagation();
		});
		$.glue.social_window.make_draggable(panel, head);
		head.find('button').bind('click', function() {
			panel.hide();
			return false;
		});
		up.bind('click', function() {
			move_selected_layers('up');
			return false;
		});
		down.bind('click', function() {
			move_selected_layers('down');
			return false;
		});
		inView.bind('click', function() {
			bring_selected_layers_into_view();
			return false;
		});
		refresh.bind('click', function() {
			refresh_layers_panel();
			return false;
		});
		refresh_layers_panel();
		return panel;
	}

	if ($.glue.menu && $.glue.menu.show) {
		var original_menu_show = $.glue.menu.show;
		$.glue.menu.show = function(name) {
			if (name == 'new' || name == 'page') {
				return false;
			}
			return original_menu_show.apply($.glue.menu, arguments);
		};
	}

	var topPane = $('<div id="social-editor-top-pane" class="glue-ui"></div>');
	var menuBar = $('<div class="social-editor-menu-bar"></div>');
	menuBar.append($('<div class="social-editor-brand">rdbrr</div>'));

	function menu_item(label, action) {
		var item = $('<button type="button" class="social-editor-menu-item"></button>').text(label);
		stop_canvas_events(item);
		item.bind('click', function() {
			action(item);
			$('.social-editor-menu').removeClass('social-editor-menu-open social-editor-menu-hover');
			$('.social-editor-submenu').removeClass('social-editor-submenu-hover');
			return false;
		});
		return item;
	}

	function menu_link(label, href) {
		return menu_item(label, function() {
			window.location = href;
		});
	}

	function menu_separator() {
		return $('<div class="social-editor-menu-separator"></div>');
	}

	function submenu(label, items) {
		var sub = $('<div class="social-editor-submenu"></div>');
		var trigger = $('<button type="button" class="social-editor-menu-item social-editor-submenu-trigger"></button>').text(label);
		var panel = $('<div class="social-editor-submenu-panel"></div>');
		var closeTimer = false;
		stop_canvas_events(sub);
		sub.bind('mouseenter', function() {
			if (closeTimer) {
				clearTimeout(closeTimer);
				closeTimer = false;
			}
			$('.social-editor-submenu').not(sub).removeClass('social-editor-submenu-hover');
			sub.addClass('social-editor-submenu-hover');
		});
		sub.bind('mouseleave', function() {
			closeTimer = setTimeout(function() {
				sub.removeClass('social-editor-submenu-hover');
				closeTimer = false;
			}, 450);
		});
		for (var i=0; i < items.length; i++) {
			panel.append(items[i]);
		}
		sub.append(trigger);
		sub.append(panel);
		return sub;
	}

	function dropdown(label, items) {
		var menu = $('<div class="social-editor-menu"></div>');
		var trigger = $('<button type="button" class="social-editor-menu-trigger"></button>').text(label);
		var panel = $('<div class="social-editor-menu-panel"></div>');
		var closeTimer = false;
		stop_canvas_events(menu);
		trigger.bind('click', function(e) {
			e.stopPropagation();
			$('.social-editor-menu').not(menu).removeClass('social-editor-menu-open social-editor-menu-hover');
			menu.toggleClass('social-editor-menu-open');
			return false;
		});
		menu.bind('mouseenter', function() {
			if (closeTimer) {
				clearTimeout(closeTimer);
				closeTimer = false;
			}
			$('.social-editor-menu').not(menu).removeClass('social-editor-menu-hover');
			menu.addClass('social-editor-menu-hover');
		});
		menu.bind('mouseleave', function() {
			closeTimer = setTimeout(function() {
				menu.removeClass('social-editor-menu-hover social-editor-menu-open');
				closeTimer = false;
			}, 450);
		});
		for (var i=0; i < items.length; i++) {
			panel.append(items[i]);
		}
		menu.append(trigger);
		menu.append(panel);
		return menu;
	}

	var viewGridItem = menu_item('Raster tonen/verbergen', function() {
		var mode = $.glue.grid.mode();
		if ((mode & 1) == 1) {
			mode = mode & ~1;
		} else {
			mode = mode | 1;
		}
		$.glue.grid.mode(mode);
		$.glue.grid.update(true);
	});

	var viewSnapItem = menu_item('Magnetisch uitlijnen', function() {
		var mode = $.glue.grid.mode();
		if ((mode & 6) != 0) {
			mode = mode & ~6;
		} else {
			mode = mode | 7;
		}
		$.glue.grid.mode(mode);
		$.glue.grid.update(true);
	});

	function toggle_mobile_layout() {
		if ($.glue.mobile_layout) {
			$.glue.mobile_layout.toggle();
		}
	}

	menuBar.append(dropdown('Bestand', [
		menu_link('Profielen', $.glue.base_url+'profiles'),
		menu_link('Feed', $.glue.base_url+'feed'),
		menu_link('Mijn profiel', $.glue.base_url+'me'),
		menu_link('Uitloggen', $.glue.base_url+'logout')
	]));

	menuBar.append(dropdown('Weergave', [
		viewGridItem,
		viewSnapItem,
		menu_item('Mobiele weergave', toggle_mobile_layout),
		menu_item('Lagen', show_layers_panel),
		submenu('Voorkeuren', [
			menu_item('Titel wijzigen...', set_page_title),
			menu_separator(),
			menu_item('Achtergrond...', open_background_panel),
			menu_item('CSS...', open_page_css_panel),
			menu_separator(),
			menu_item('Rastergrootte...', set_grid_size)
		])
	]));

	menuBar.append(dropdown('Tekst', [
		submenu('Lettertype', [
			menu_item('DejaVu Sans', function() { set_selected_font_family('DejaVuSans'); }),
			menu_item('DejaVu Serif', function() { set_selected_font_family('DejaVuSerif'); }),
			menu_item('DejaVu Mono', function() { set_selected_font_family('DejaVuSansMono'); }),
			menu_item('Latin Modern', function() { set_selected_font_family('LatinModern'); }),
			menu_separator(),
			menu_item('Verdana', function() { set_selected_font_family('Verdana, Geneva, Tahoma, sans-serif'); }),
			menu_item('Arial', function() { set_selected_font_family('Arial, Helvetica, sans-serif'); }),
			menu_item('Georgia', function() { set_selected_font_family('Georgia, serif'); }),
			menu_item('Courier New', function() { set_selected_font_family('"Courier New", Courier, monospace'); })
		]),
		menu_item('Lettergrootte...', prompt_selected_font_size),
		menu_item('Tekstkleur...', prompt_selected_text_color)
	]));

	function create_profile_page(anchor) {
		open_single_input_panel(anchor, 'Nieuwe profielpagina', 'Naam', '', 'Maak', function(value) {
			$.glue.backend({ method: 'social_profile.create_page', slug: value }, function(data) {
				if (data && data.edit_url) {
					window.location = data.edit_url;
				}
			});
			return false;
		});
	}

	function suggested_duplicate_slug() {
		var pages = $.glue.social_profile_pages || [];
		var taken = {};
		for (var i=0; i < pages.length; i++) {
			taken[pages[i].slug] = true;
		}
		var current = $.glue.social_profile_slug || 'home';
		var base = current == 'home' ? 'kopie' : current+'-kopie';
		if (!taken[base]) {
			return base;
		}
		for (var n=2; n < 100; n++) {
			if (!taken[base+'-'+n]) {
				return base+'-'+n;
			}
		}
		return '';
	}

	function duplicate_profile_page(anchor) {
		open_single_input_panel(anchor, 'Pagina dupliceren', 'Nieuwe naam', suggested_duplicate_slug(), 'Dupliceer', function(value) {
			$.glue.backend({ method: 'social_profile.duplicate_page', source: $.glue.page, slug: value }, function(data) {
				if (data && data.edit_url) {
					window.location = data.edit_url;
				}
			});
			return false;
		});
	}

	function profile_page_items() {
		var pages = $.glue.social_profile_pages || [];
		var items = [];
		for (var i=0; i < pages.length; i++) {
			var label = (pages[i].slug == $.glue.social_profile_slug ? '* ' : '')+(pages[i].title || pages[i].slug || 'Pagina');
			items.push(menu_link(label, pages[i].edit_url));
		}
		if (items.length) {
			items.push(menu_separator());
		}
		var limit = $.glue.social_profile_page_limit || 5;
		if (pages.length < limit) {
			items.push(menu_item('Dupliceer huidige...', duplicate_profile_page));
			items.push(menu_item('Nieuwe pagina...', create_profile_page));
		} else {
			items.push(menu_item('Maximaal '+limit+' pagina\'s', function() {
				$.glue.error('Je hebt het maximum van '+limit+' profielpagina\'s bereikt.');
			}));
		}
		return items;
	}

	var profileItems = [];
	if ($.glue.social_profile_url) {
		profileItems.push(menu_link('Bekijk pagina', $.glue.social_profile_url));
		profileItems.push(submenu('Pagina\'s', profile_page_items()));
	}
	profileItems.push(menu_link('Profielenlijst', $.glue.base_url+'profiles'));
	profileItems.push(menu_link('Feed', $.glue.base_url+'feed'));
	menuBar.append(dropdown('Profiel', profileItems));

	if ($.glue.social_is_admin) {
		menuBar.append(dropdown('Beheer', [
			menu_link('Gebruikers beheren', $.glue.base_url+'admin')
		]));
	}

	$('html').bind('click', function(e) {
		if ($(e.target).parents('#social-editor-top-pane').length == 0) {
			$('.social-editor-menu').removeClass('social-editor-menu-open social-editor-menu-hover');
			$('.social-editor-submenu').removeClass('social-editor-submenu-hover');
		}
	});

	topPane.append(menuBar);
	stop_canvas_events(topPane);
	$('body').append(topPane);

	var toolbar = $('<div id="social-editor-edit-panel" class="glue-ui"></div>');
	toolbar.append($('<div class="social-editor-panel-title">Tools</div>'));
	toolbar.append(tool_button('Select', 'select', function() {
		$.glue.sel.none();
	}).addClass('social-editor-tool-active'));
	toolbar.append(tool_button('Tekst', 'text', make_text));

	var upload_holder = upload_tool_button('Upload', 'upload');
	toolbar.append(upload_holder);
	var upload = $.glue.upload.default_upload_handling($(window).scrollLeft()+Math.round($(window).width()/2), $(window).scrollTop()+Math.round($(window).height()/2));
	upload.multiple = true;
	upload_holder.bind('mousedown', function() {
		upload.x = $(window).scrollLeft()+Math.round($(window).width()/2);
		upload.y = $(window).scrollTop()+Math.round($(window).height()/2);
	});
	$.glue.upload.button(upload_holder, { method: 'glue.upload_files', page: $.glue.page }, upload);

	toolbar.append(tool_button('Web', 'web', make_iframe));
	toolbar.append(tool_button('Video', 'video', make_webvideo));
	toolbar.append(tool_button('Berichten', 'wall', make_wall));
	toolbar.append(tool_button('Updates', 'microblog', make_microblog));
	stop_canvas_events(toolbar);
	$('body').append(toolbar);
	show_layers_panel();

	$('.object').live('glue-movestop', function() {
		if (snap_object_to_grid(this)) {
			$.glue.object.save(this);
			$.glue.canvas.update(this);
		}
		refresh_layers_panel();
	});
	$('.object').live('glue-select glue-deselect glue-register glue-unregister', function() {
		refresh_layers_panel();
	});
	$('html').bind('keyup.socialLayers', function(e) {
		if ((e.which == 33 || e.which == 34 || e.which == 46) && $('#social-editor-layers-panel').length) {
			setTimeout(refresh_layers_panel, 0);
		}
	});
});
