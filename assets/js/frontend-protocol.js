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
				line = 'Tire print ou anote esse protocolo de inscrição: ' + protocol;
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

	function decodeHtmlEntities(value) {
		if (!value || value.indexOf('&') === -1) {
			return value;
		}

		return $('<textarea>').html(value).text();
	}

	/**
	 * Escapa texto e permite apenas <a> com href http(s).
	 * Demais tags são convertidas em texto.
	 */
	function sanitizeMessageHtml(rawMessage) {
		var decoded = decodeHtmlEntities(rawMessage || '');
		var $wrap = $('<div>').html(decoded);

		$wrap.find('*').not('a').each(function () {
			$(this).replaceWith(document.createTextNode($(this).text()));
		});

		$wrap.find('a').each(function () {
			var $link = $(this);
			var href = $.trim($link.attr('href') || '');
			var className = $.trim($link.attr('class') || '');
			var text = $link.text();

			if (!/^https?:\/\//i.test(href)) {
				$link.replaceWith(document.createTextNode(text));
				return;
			}

			var $safe = $('<a></a>')
				.attr({
					href: href,
					target: '_blank',
					rel: 'noopener noreferrer',
					class: 'protocolo-elementor-link' + (className ? ' ' + className : ''),
				})
				.text(text);

			$link.replaceWith($safe);
		});

		return $wrap.html();
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
		var safeMessage = sanitizeMessageHtml(baseMessage);
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
