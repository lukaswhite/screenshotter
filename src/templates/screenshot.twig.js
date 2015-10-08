var page = require('webpage').create(),
		args = require('system').args;

var filepath = args[1];

page.viewportSize = { 
	width: {{ width }}, 
	height: {{ height }}
};

{% if clipW is defined and clipH is defined %}
page.clipRect = { 
	top: 0, 
	left: 0, 
	width: {{ clipW }}, 
	height: {{ clipH }} 
};
{% endif %}

page.open('{{ url }}', function () {
	page.render(filepath);
	phantom.exit();
});