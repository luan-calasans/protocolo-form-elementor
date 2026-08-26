(function ($) {
	'use strict';

	function extractProtocolLine(response) {
		if (!response) {
			return '';
		}

		var nested = {};

		if (response.data && response.data.data && typeof response.data.data === 'object') {
			nested = response.data.data;
		} else if (response.data && typeof response.data === 'object' && !response.data.message) {
			nested = response.data;
		} else if (response.data && typeof response.data === 'object') {
			nested = response.data;
		}

		var line = nested.protocol_line || '';

		if (!line) {
			var protocol = nested.protocol || '';
			if (protocol) {
				line = 'Protocolo: ' + protocol;
			}
		}

		return line;
	}

	function extractMessage(response) {
		if (!response) {
			return '';
		}

		if (typeof response.message === 'string') {
			return response.message;
		}

		if (response.data && typeof response.data.message === 'string') {
			return response.data.message;
		}

		return '';
	}

	function findMessageNode(target) {
		var $root = $(target);
		var $message = $root.find('.elementor-message-success').first();

		if (!$message.length) {
			$message = $root.closest('.elementor-widget-form').find('.elementor-message-success').first();
		}

		if (!$message.length) {
			$message = $root.closest('.elementor-form').find('.elementor-message-success').first();
		}

		if (!$message.length && $root.is('form')) {
			$message = $root.parent().find('.elementor-message-success').first();
		}

		return $message;
	}

	function renderProtocolBelowMessage(target, response) {
		var line = extractProtocolLine(response);

		if (!line) {
			return false;
		}

		var $message = findMessageNode(target);

		if (!$message.length) {
			return false;
		}

		if ($message.text().indexOf(line) !== -1) {
			return true;
		}

		var baseMessage = extractMessage(response) || $.trim($message.text());
		var safeMessage = $('<div>').text(baseMessage).html();
		var safeLine = $('<div>').text(line).html();

		$message.html(safeMessage + (safeMessage ? '<br>' : '') + safeLine);

		return true;
	}

	$(document).on('submit_success', function (event, response) {
		if (renderProtocolBelowMessage(event.target, response)) {
			return;
		}

		// Elementor pode montar o DOM da mensagem depois do evento.
		window.setTimeout(function () {
			renderProtocolBelowMessage(event.target, response);
		}, 0);

		window.setTimeout(function () {
			renderProtocolBelowMessage(event.target, response);
		}, 50);
	});
})(jQuery);
