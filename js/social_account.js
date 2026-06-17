$(document).ready(function() {
	var form = $('.social-account-form');
	var file = form.find('.social-avatar-file');
	var cropper = form.find('.social-avatar-cropper');
	var stage = form.find('.social-avatar-crop-stage');
	var image = form.find('.social-avatar-crop-image');
	var box = form.find('.social-avatar-crop-box');
	var handle = box.find('span');
	var cropX = form.find('input[name="avatar_crop_x"]');
	var cropY = form.find('input[name="avatar_crop_y"]');
	var cropSize = form.find('input[name="avatar_crop_size"]');
	var drag = false;

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	function stage_size() {
		return {
			width: stage.width(),
			height: stage.height()
		};
	}

	function set_box(left, top, size) {
		var bounds = stage_size();
		size = clamp(size, 40, Math.min(bounds.width, bounds.height));
		left = clamp(left, 0, bounds.width-size);
		top = clamp(top, 0, bounds.height-size);
		box.css({
			left: left+'px',
			top: top+'px',
			width: size+'px',
			height: size+'px'
		});
		update_inputs();
	}

	function update_inputs() {
		var img = image.get(0);
		if (!img || !img.naturalWidth || !image.width()) {
			return;
		}
		var scale = img.naturalWidth/image.width();
		cropX.val(Math.round(parseFloat(box.css('left'))*scale));
		cropY.val(Math.round(parseFloat(box.css('top'))*scale));
		cropSize.val(Math.round(box.width()*scale));
	}

	function reset_box() {
		var bounds = stage_size();
		var size = Math.floor(Math.min(bounds.width, bounds.height)*0.72);
		set_box(Math.floor((bounds.width-size)/2), Math.floor((bounds.height-size)/2), size);
	}

	function start_drag(e, mode) {
		var pos = box.position();
		drag = {
			mode: mode,
			x: e.pageX,
			y: e.pageY,
			left: pos.left,
			top: pos.top,
			size: box.width()
		};
		$(document).bind('mousemove.socialAvatarCrop', move_drag);
		$(document).bind('mouseup.socialAvatarCrop', stop_drag);
		e.preventDefault();
		return false;
	}

	function move_drag(e) {
		if (!drag) {
			return false;
		}
		var dx = e.pageX-drag.x;
		var dy = e.pageY-drag.y;
		if (drag.mode == 'resize') {
			set_box(drag.left, drag.top, drag.size+Math.max(dx, dy));
		} else {
			set_box(drag.left+dx, drag.top+dy, drag.size);
		}
		return false;
	}

	function stop_drag() {
		$(document).unbind('mousemove.socialAvatarCrop');
		$(document).unbind('mouseup.socialAvatarCrop');
		drag = false;
		update_inputs();
		return false;
	}

	function load_preview(input) {
		var selected = input.files && input.files.length ? input.files[0] : false;
		if (!selected) {
			cropper.hide();
			cropX.val('');
			cropY.val('');
			cropSize.val('');
			return;
		}
		if (!selected.type || selected.type.indexOf('image/') !== 0) {
			cropper.hide();
			return;
		}
		var reader = new FileReader();
		reader.onload = function(e) {
			image.unbind('load.socialAvatarCrop').bind('load.socialAvatarCrop', function() {
				cropper.removeAttr('hidden').show();
				stage.css({
					width: image.width()+'px',
					height: image.height()+'px'
				});
				reset_box();
			});
			image.attr('src', e.target.result);
		};
		reader.readAsDataURL(selected);
	}

	file.bind('change', function() {
		load_preview(this);
	});
	box.bind('mousedown', function(e) {
		if ($(e.target).is('span')) {
			return start_drag(e, 'resize');
		}
		return start_drag(e, 'move');
	});
	handle.bind('mousedown', function(e) {
		return start_drag(e, 'resize');
	});
	form.bind('submit', function() {
		update_inputs();
	});
});
