(function ($) {
	'use strict';

	function getNestedData(response) {
		if (!response) {
			return {};
		}

		if (response.data && response.data.data && typeof response.data.data === 'object') {
			return response.data.data;
		}

		if (response.data && typeof response.data === 'object') {
			return response.data;
		}

		return {};
	}

	function extractProtocol(response) {
		var nested = getNestedData(response);
		var protocol = nested.protocol || '';

		if (protocol) {
			return String(protocol);
		}

		var line = nested.protocol_line || '';
		var match = String(line).match(/\d{18}/);

		return match ? match[0] : '';
	}

	function extractProtocolLine(response) {
		var nested = getNestedData(response);
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

	function stripProtocolFromMessage(message, protocol, line) {
		var cleaned = String(message || '');

		if (line) {
			cleaned = cleaned.split(line).join(' ');
		}

		if (protocol) {
			cleaned = cleaned.split(protocol).join(' ');
		}

		cleaned = cleaned.replace(/Tire print ou anote esse protocolo de inscrição:?\s*/gi, ' ');

		return $.trim(cleaned.replace(/\s+/g, ' '));
	}

	function buildProtocolHighlight(protocol, line) {
		var label = String(line || '')
			.replace(protocol, '')
			.replace(/:\s*$/, '')
			.trim();

		if (!label) {
			label = 'Tire print ou anote esse protocolo de inscrição';
		}

		var safeLabel = $('<div>').text(label).html();
		var safeProtocol = $('<div>').text(protocol).html();

		return (
			'<div class="protocolo-elementor-protocol" role="status">' +
				'<span class="protocolo-elementor-protocol-label">' + safeLabel + '</span>' +
				'<code class="protocolo-elementor-protocol-code">' + safeProtocol + '</code>' +
			'</div>'
		);
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
		var protocol = extractProtocol(response);
		var line = extractProtocolLine(response);

		if (!protocol) {
			return false;
		}

		var $message = findMessageNode(target);

		if (!$message.length) {
			return false;
		}

		if ($message.find('.protocolo-elementor-protocol').length) {
			return true;
		}

		var baseMessage = extractMessage(response) || $.trim($message.text());
		baseMessage = stripProtocolFromMessage(baseMessage, protocol, line);

		var safeMessage = sanitizeMessageHtml(baseMessage);
		var highlight = buildProtocolHighlight(protocol, line);

		$message.html(safeMessage + highlight);

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
