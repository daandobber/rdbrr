/**
 *	js/glue.js
 *	Auxiliary hotglue frontend code
 *
 *	Copyright Gottfried Haider, Danja Vasiliev 2010.
 *	This source code is licensed under the GNU General Public License.
 *	See the file COPYING for more details.
 */

// create dummy console functions
if (!window.console) {
	console = {};
}
console.log = console.log || function(){};
console.error = console.error || function(){};
console.warn = console.warn || function(){};
console.info = console.info || function(){};

$.glue = {};

// communication with the backend
$.glue.backend = function()
{
	$(document).ready(function() {
		$(this).ajaxError(function(e, xhr, options, err) {
			if (xhr.readyState == 0 || xhr.status == 0) {
				// not really an error
				// these happen when navigating away while a ajax request is in flight
				// see http://stackoverflow.com/questions/866771/jquery-ambiguous-ajax-error
			} else {
				$.glue.error('There was a problem communicating with the server (ready state '+xhr.readyState+', status '+ xhr.status+')');
			}
		});
	});
	
	return function(param, func, print_errors) {
		// ten seconds timeout
		$.ajaxSetup({ timeout: 10000 });
		// make sure parameters are json encoded
		// otherwise we would get complaints from the php parser for empty 
		// strings, arrays and thelike
		for (p in param) {
			param[p] = JSON.stringify(param[p]);
		}
		$.post($.glue.base_url+'json.php', param, function(data) {
			if (print_errors === undefined) {
				print_errors = true;
			}
			if (data === null) {
				if (print_errors) {
					$.glue.error('There was a problem communicating with the server');
				} else if (typeof func == 'function') {
					func({ '#error': true, '#data':'There was a problem communicating with the server' });
				}
			} else if (print_errors) {
				if (data['#error']) {
					$.glue.error(data['#data']);
				} else if (typeof func == 'function') {
					func(data['#data']);
				}
			} else if (typeof func == 'function') {
				func(data);
			}
		}, 'json');
	};
}();

$.glue.error = function()
{
	return function(s) {
		if ($.glue.conf.show_frontend_errors) {
			$('.glue-error-toast').remove();
			var toast = $('<div class="glue-error-toast glue-ui"></div>');
			toast.text('rdbrr: '+s);
			toast.css({
				background: '#dedbcf',
				border: '1px solid #404040',
				boxShadow: '2px 2px 0 rgba(0, 0, 0, 0.35)',
				color: '#111',
				fontFamily: 'Verdana, Geneva, Tahoma, sans-serif',
				fontSize: '12px',
				left: '50%',
				maxWidth: '420px',
				padding: '8px 10px',
				position: 'fixed',
				top: '42px',
				transform: 'translateX(-50%)',
				zIndex: 100050
			});
			$('body').append(toast);
			setTimeout(function() {
				toast.fadeOut(150, function() {
					$(this).remove();
				});
			}, 4500);
		}
	};
}();

$.glue.confirm = function(message, ok_label, cancel_label, ok)
{
	$('.glue-confirm-panel').remove();
	var panel = $('<div class="glue-confirm-panel glue-ui"></div>');
	var head = $('<div></div>').text('rdbrr');
	var body = $('<p></p>').text(message);
	var actions = $('<div></div>');
	var cancel = $('<button type="button"></button>').text(cancel_label || 'Annuleren');
	var confirm = $('<button type="button"></button>').text(ok_label || 'OK');
	panel.append(head).append(body).append(actions.append(cancel).append(confirm));
	panel.css({
		background: '#dedbcf',
		border: '1px solid #404040',
		boxShadow: '2px 2px 0 rgba(0, 0, 0, 0.35)',
		boxSizing: 'border-box',
		color: '#111',
		fontFamily: 'Verdana, Geneva, Tahoma, sans-serif',
		fontSize: '12px',
		left: '50%',
		padding: '0 10px 10px 10px',
		position: 'fixed',
		top: '54px',
		transform: 'translateX(-50%)',
		width: '340px',
		zIndex: 100060
	});
	head.css({
		background: '#0a4fb1',
		color: '#fff',
		fontWeight: 'bold',
		margin: '0 -10px 9px -10px',
		padding: '4px 7px'
	});
	body.css({
		lineHeight: '1.35',
		margin: '0 0 10px 0'
	});
	actions.css({
		display: 'flex',
		gap: '8px',
		justifyContent: 'flex-end'
	});
	panel.find('button').css({
		background: '#f1f1f1',
		border: '1px solid #555',
		color: '#111',
		cursor: 'pointer',
		fontFamily: 'Verdana, Geneva, Tahoma, sans-serif',
		fontSize: '11px',
		padding: '3px 9px'
	});
	cancel.bind('click', function() {
		panel.remove();
		return false;
	});
	confirm.bind('click', function() {
		panel.remove();
		if (typeof ok == 'function') {
			ok();
		}
		return false;
	});
	$('body').append(panel);
};
