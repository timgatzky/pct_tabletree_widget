
/**
 * Toggle the table tree input field
 *
 * @param object	DOM Element
 * @param integer	Id of the node
 * @param string	Name of the dca field
 * @param string  	The Ajax field name
 * @param string  	The source table name
 * @param string	Name of the value Field
 * @param string	Name of the key Field
 * @param integer	Level The indentation level
 *
 * @returns {boolean}
 */
AjaxRequest.toggleTabletree = function (el, id, field, name, source, valueField, keyField, conditions, level) 
{
	el.blur();
	Backend.getScrollOffset();

	var item = $(id),
		image = $(el).getFirst('img');

	// toggleTabletree
	if (item) 
	{
		if (item.getStyle('display') == 'none') 
		{
			item.setStyle('display', 'inline');
			image.src = image.src.replace('folPlus', 'folMinus');
			$(el).store('tip:title', Contao.lang.collapse);
			new Request.Contao({field:el}).post({'action':'toggleTabletree', 'id':id, 'state':1, 'REQUEST_TOKEN':Contao.request_token});
		} 
		else 
		{
			item.setStyle('display', 'none');
			image.src = image.src.replace('folMinus', 'folPlus');
			$(el).store('tip:title', Contao.lang.expand);
			new Request.Contao({field:el}).post({'action':'toggleTabletree', 'id':id, 'state':0, 'REQUEST_TOKEN':Contao.request_token});
		}
		return false;
	}

	// loadTabletree
	new Request.Contao({
		field: el,
		evalScripts: true,
		onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
		onSuccess: function(txt) 
		{
			var li = new Element('li', {
				'id': id,
				'class': 'parent',
				'styles': {
					'display': 'inline'
				}
			});

			var ul = new Element('ul', {
				'class': 'level_' + level,
				'html': txt
			}).inject(li, 'bottom');

			li.inject($(el).getParent('li'), 'after');

			// Update the referer ID
			li.getElements('a').each(function(el) {
				el.href = el.href.replace(/&ref=[a-f0-9]+/, '&ref=' + Contao.referer_id);
			});

			$(el).store('tip:title', Contao.lang.collapse);
			image.src = image.src.replace('folPlus', 'folMinus');
			AjaxRequest.hideBox();

			// HOOK
			window.fireEvent('ajax_change');
		}
	}).post({'action':'loadTabletree', 'loadCustomElementFields':1, 'id':id, 'level':level, 'field':field, 'name':name, 'source':source, 'valueField':valueField, 'keyField':keyField,'state':1,'conditions':conditions, 'REQUEST_TOKEN':Contao.request_token});
	return false;
}


/**
 * Open a selector page in a modal window
 * @param object	An optional options object
 */
Backend.openModalTabletreeSelector = function(options) 
{
	var opt = options || {},
		max = (window.getSize().y-180).toInt();
	if (!opt.height || opt.height > max) opt.height = max;
	
	var M = new SimpleModal({
		'width': opt.width,
		'btn_ok': Contao.lang.close,
		'draggable': false,
		'overlayOpacity': .5,
		'onShow': function() { document.body.setStyle('overflow', 'hidden'); },
		'onHide': function() { document.body.setStyle('overflow', 'auto'); }
	});
	M.addButton(Contao.lang.close, 'btn', function() {
		this.hide();
	});
	M.addButton(Contao.lang.apply, 'btn primary', function() 
	{
		var val = [],
			frm = null,
			frms = window.frames;
		for (i=0; i<frms.length; i++) {
			if (frms[i].name == 'simple-modal-iframe') {
				frm = frms[i];
				break;
			}
		}
		if (frm === null) {
			alert('Could not find the SimpleModal frame');
			return;
		}
		
		var inp = frm.document.getElementById('tl_listing').getElementsByTagName('input');
		for (var i=0; i<inp.length; i++) {
			if (!inp[i].checked || inp[i].id.match(/^check_all_/)) continue;
			if (!inp[i].id.match(/^reset_/)) val.push(inp[i].get('value'));
		}
		
		if (opt.tag) 
		{
			$(opt.tag).value = val.join(',');
			if (opt.url.match(/page\.php/)) {
				$(opt.tag).value = '{{link_url::' + $(opt.tag).value + '}}';
			}
			opt.self.set('href', opt.self.get('href').replace(/&value=[^&]*/, '&value='+val.join(',')));
		} 
		else 
		{
			$$('#ctrl_'+opt.id)[0].value = val.join("\t");
			var act = 'reloadTabletree';
			new Request.Contao({
				field: $$('#ctrl_'+opt.id)[0],
				evalScripts: false,
				onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
				onSuccess: function(txt, json) {
					$$('#ctrl_'+opt.id)[0].getParent('div').set('html', json.content);
					json.javascript && Browser.exec(json.javascript);
					AjaxRequest.hideBox();
					window.fireEvent('ajax_change');
				}
			}).post({'action':act, 'loadCustomElementFields':1, 'name':opt.id,'field':opt.id,'source':options.source,'table':options.table, 'valueField':options.valueField, 'keyField':options.keyField,'translationField':options.translationField,'rootsField':options.rootsField,'roots':options.roots,'conditions':options.conditions,'conditionsField':options.conditionsField, 'value':$$('#ctrl_'+opt.id)[0].value, 'REQUEST_TOKEN':Contao.request_token});
		}
		this.hide();
	});
	M.show({
		'title': opt.title,
		'contents': '<iframe src="' + opt.url + '" name="simple-modal-iframe" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
		'model': 'modal'
	});
}
